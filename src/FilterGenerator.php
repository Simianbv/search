<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @class   FilterGenerator
 * @package Simianbv\Search
 */
class FilterGenerator
{
    /**
     * @var array
     */
    private mixed $filters = [];

    /**
     * The string representation of the model used.
     *
     * @var string
     */
    protected mixed $model_class = null;

    /**
     * @var bool
     */
    protected bool $enableCache = false;

    /**
     * The model used for generating the filters on.
     *
     * @var Model
     */
    protected mixed $model = null;

    /**
     * Valid filter types to render inc. aliases
     * @var array
     */
    public static $validTypes = [
        'select'   => ['multiselect', 'combobox'],
        'string'   => ['text', 'email'],
        'number'   => ['int', 'integer', 'double', 'bigInt', 'float', 'smallint', 'mediumInt'],
        'datetime' => ['datetime', 'timestamp'],
        'date'     => ['date'],
        'bool'     => ['boolean'],
    ];


    /**
     * Generator constructor.
     *
     * @param $model
     *
     * @throws Exception
     */
    public function __construct ($model)
    {
        if ($model instanceof Model) {
            $this->model_class = get_class($model);
            $this->model = $model;
        } else {
            if (!class_exists($model)) {
                throw new Exception("Model $model doesn't exist.");
            }

            $this->model_class = $model;
            $this->model = $model::query()->getModel();
        }
    }

    /**
     * Retrieve the filters used for the provided model assigned in the constructor.
     *
     * @return array
     * @throws Exception
     */
    public function getFilters ()
    {
        $generator = $this;

        if (!$this->filters) {
            if ($this->enableCache) {
                $this->filters = persist_cache(
                    $this->getCacheKey(),
                    function () use ($generator) {
                        return $generator->build();
                    },
                    60 * 24,
                    ['filter']
                );
            } else {
                return $generator->build();
            }
        }

        return $this->filters;
    }

    /**
     * Returns the cache key used to store the filter in cache to.
     *
     * @return string
     */
    private function getCacheKey ()
    {
        return md5('filters.search.' . Str::slug($this->model_class));
    }

    /**
     * Build up the relations and corresponding models associated with the relation.
     *
     * @return array $filters
     * @throws Exception
     */
    public function build ()
    {
        $filters = [];
        $relationColumns = $this->getRelations();
        $columns = $this->getTableColumns();
        $relatedTableNamePrefix = request()->get('as') ? request()->get('as') . '.' : '';

        foreach ($columns as $name => $column) {
            if (is_array($column)) {
                $filters[$name] = $column;
                $filters[$name]['name'] = $relatedTableNamePrefix . $name;
            } else {
                $filters[$name] = [
                    'relation' => false,
                    'name'     => $relatedTableNamePrefix . $name,
                    'type'     => self::getValidFilterType($column, 'string'),
                ];
            }

            if (!isset($filters[$name]['label'])) {
                $filters[$name]['label'] = __('filters.' . $name) !== 'filters.' . $name
                    ? __('filters.' . $name)
                    : ucfirst(implode(' ', explode('_', $name)));
            }
        }

        foreach ($filters as $idx => $filter) {
            if (isset($filter['type']) && $filter['type'] == 'hidden') {
                unset($filters[$idx]);
            }
        }

        $filters = array_merge($filters, $this->createRelationOptions($relationColumns));


        $this->filters = $filters;

        return $this->filters;
    }

    /**
     * Create the relations options for the relation columns found in the model.
     *
     * @param array $relationColumns
     *
     * @return array
     * @throws Exception
     */
    private function createRelationOptions (array $relationColumns)
    {
        $relations = [];

        foreach ($relationColumns as $relationName => $relationColumn) {
            $label = $relationColumn['label'] ?? __($relationName);

            // if the column should be a normal searchable column, instead of a relation
            $searchable = $relationColumn['searchable'] ?? false;

            // Set up the correct field type
            $type = $relationColumn['type'] ?? 'select';
            $component = $type;

            if ($searchable || in_array($component, ['autocomplete', 'search'])) {
                $searchable = true;
                $type = 'string';
            }

            $relations[$relationName] = [
                'type'     => self::getValidFilterType($type),
                'name'     => $relationName,
                'relation' => true,
                'label'    => $label,
            ];

            if (isset($relationColumn['as']) && $relationColumn['as'] !== '') {
                $relations[$relationName]['as'] = $relationColumn['as'];
            }

            if (isset($relationColumn['properties'])) {
                $relations[$relationName]['component'] = $component;
                $relations[$relationName]['column'] = $relationColumn['column'] ?? null;
                $relations[$relationName]['properties'] = $relationColumn['properties'];
                $relations[$relationName]['properties']['field'] = 'id';
                if (config('app.env') == 'local' && isset($relationColumn['properties']['dev_url'])) {
                    $relations[$relationName]['properties']['url'] = $relationColumn['properties']['dev_url'];
                    unset($relations[$relationName]['properties']['dev_url']);
                }
                if (isset($relationColumn['properties']['field'])) {
                    $relations[$relationName]['properties']['field'] = $relationColumn['properties']['field'];
                }
            }

            if (!$searchable) {
                $relations[$relationName]['options'] = [];
            } else {
                $class = $relationColumn['model'];
                $relations[$relationName]['prefix'] = (new $class)->getTable();
            }


            $class = $relationColumn['model'];

            if (!$relationColumn['select']) {
                throw new Exception("No select fields present in the filters array, we need something to display in the options array for this relation filter.");
            }

            $keyName = (new $class)->getKeyName();
            $originalSelect = $relationColumn['select'];

            if (!in_array($keyName, $relationColumn['select'])) {
                array_unshift($relationColumn['select'], $keyName);
            }

            if (!$searchable) {
                // actually perform the search query and retrieve the options
                $builder = $class::select($relationColumn['select']);

                if (isset($relationColumn['order-by'])) {
                    $direction = 'asc';
                    if (is_array($relationColumn['order-by'])) {
                        $field = $relationColumn['order-by'][0];
                        $direction = $relationColumn['order-by'][1];
                    } else {
                        $field = $relationColumn['order-by'];
                    }
                    $builder->orderBy($field, $direction);
                }

                if (isset($relationColumn['conditions']) && count($relationColumn['conditions']) > 0) {
                    foreach ($relationColumn['conditions'] as $column => $condition) {
                        if (!is_array($condition)) {
                            $builder->where($column, $condition);
                        } else {
                            if (count($condition) == 2) {
                                $builder->where($column, $condition[0], $condition[1]);
                            }
                        }
                    }
                }
                $relationOptions = $builder->get();
                foreach ($relationOptions as $i => $option) {
                    if (isset($relationColumn['as'])) {
                        $relations[$relationName]['options'][$i] = $option;
                        $relations[$relationName]['options'][$i]['id'] = $option->{$option->getKeyName()};
                        $relations[$relationName]['options'][$i]['label'] = $this->getRelationSelectAsText($option, $originalSelect);
                    } else {
                        $relations[$relationName]['options'][] = [
                            'id'    => $option->{$option->getKeyName()},
                            'label' => $this->getRelationSelectAsText($option, $originalSelect),
                        ];
                    }
                }
            }
        }

        return $relations;
    }

    /**
     * @param $option
     * @param $select
     *
     * @return string
     */
    private function getRelationSelectAsText ($option, $select = ['select'])
    {
        $value = [];
        foreach ($select as $field) {
            if ($option->{$field}) {
                $value[] = $option->{$field};
            }
        }

        return implode(' ', $value);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getRelations ()
    {
        $relations = [];
        if (!$this->model) {
            throw new Exception("No model was specified, unable to determine if the model provided requires relational filter columns.");
        }
        if (isset($this->model->filters) &&
            isset($this->model->filters['relations']) &&
            is_array($this->model->filters['relations'])) {
            foreach ($this->model->filters['relations'] as $name => $relation) {
                if (
                    isset($relation['model']) && isset($relation['select'])) {
                    $relations[$name] = $relation;
                }
                if (isset($relation['model'])) {
                    $relations[$name] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * Returns an array containing all the table columns for this model, sorts them and if a column is actually a hidden column, remove it
     *
     * @return array
     */
    public function getTableColumns ()
    {
        /** @var Builder $builder */

        $builder = $this->model->getConnection()->getSchemaBuilder();
        $columns = $builder->getColumnListing($this->model->getTable());


        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $columnsWithType = collect($columns)->mapWithKeys(
            function ($item, $key) use ($builder) {
                return [$item => $builder->getColumnType($this->model->getTable(), $item)];
            }
        );
        $columns = $columnsWithType->toArray();

        // this should clean up relations by unsetting the *_id fields
        foreach ($columns as $index => $column) {
            if (Str::endsWith($index, ['_id'])) {
                unset($columns[$index]);
            }
            if (in_array($index, $this->model->getHidden())) {
                unset($columns[$index]);
            }
            if (isset($this->model->filters[$index]) && isset($this->model->filters[$index]['type'])) {
                $columns[$index] = $this->model->filters[$index];
            }
        }

        return $columns;
    }

    /**
     * Returns a valid filter, given the type. If no valid filter was found for the provided $type, it returns either the default,
     * or the default you specified.
     *
     * @param        $type
     * @param string $default
     *
     * @return int|string
     */
    public static function getValidFilterType ($type, $default = 'select')
    {
        if (in_array($type, array_keys(self::$validTypes))) {
            return $type;
        }
        foreach (self::$validTypes as $proponent => $aliases) {
            if (in_array($type, $aliases)) {
                return $proponent;
            }
        }

        return $default;
    }
}
