<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Search;

use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Simianbv\Search\Contracts\FilterInterface;
use Simianbv\Search\Contracts\IsApiSearchable;
use Simianbv\Search\Search\Types\JoinableColumn;
use Simianbv\Search\SearchResult;

/**
 * @class   Wildcard
 * @package Simianbv\Search\Search
 */
class Wildcard extends BaseSearch implements FilterInterface
{
    /**
     * @var string
     */
    protected static $fieldSeparator = '|';

    /**
     * @var string
     */
    protected static $keySeparator = '~';

    /**
     * @var string
     */
    protected static $wildcardPrefix = 'wildcard.';

    /**
     * @var array
     */
    protected $scopes = [];

    /**
     * Apply the filter on the Builder object provided and add the value to match onto the query.
     *
     * @param Builder $builder
     * @param         $value
     *
     * @return Builder
     * @internal implement the fields you want to query on in the method itself.
     *
     */
    public function apply (Builder $builder, $value): Builder
    {
        $this->setSearchValue($value);

        // If the model is not searchable, just return the default builder instance
        if (!$builder->getModel() instanceof IsApiSearchable) {
            return $builder;
        }

        // first, get the searchable columns and the optional fields the query wants to search in
        [$searchableColumns, $wildcardColumns] = $this->getSearchableColumns($builder);
        $scopes = $this->getSelectScopes($builder);

        $builder->where(

            function ($query) use ($wildcardColumns, $searchableColumns, $builder, $value) {
                $baseTable = $builder->getModel()->getTable();

                foreach ($wildcardColumns as $field) {
                    if ($field instanceof JoinableColumn) {
                        $this->addJoinableColumn($field, $builder);
                        continue;
                    }

                    // if the searchable field is an array, it means we want a concatenated field
                    if (is_array($field)) {
                        $this->addConcatenatedFields($query, $field, $baseTable, $value);
                        continue;
                    }

                    // else if the field given is not one of the valid fields we're allowed to search in, skip it
                    if (!in_array($field, $searchableColumns)) {
                        continue;
                    }

                    // if the field contains a dot, we probably want to join a related table to match results
                    if (strpos($field, '.') !== false) {
                        [$table, $columns] = $this->getJoinColumns($builder, $field);
                        foreach ($columns as $column) {
                            $scopes[] = $table . '.' . $column;
                            $query->orWhere($table . '.' . $column, 'like', '%' . $value . '%');
                        }
                        continue;
                    }

                    // if no joinable columns were set, just use a 'regular and plain' where clause
                    $query->orWhere($baseTable . '.' . $field, 'like', "%" . $value . "%");
                }
            }
        );

        $builder->select($scopes);

        return $builder;
    }

    /**
     * Add a Joinable column to the result set to be included in the search space.
     *
     * @param JoinableColumn $field
     * @param Builder $builder
     * @return void
     */
    protected function addJoinableColumn (JoinableColumn $column, $builder): void
    {
        $concatenated = [];

        $builder->{$column->getJoinType()}(
            $column->getJoinTable(),
            $column->getJoinTable() . '.' . $column->getCondition()->getBaseField(),
            $column->getCondition()->getCondition(),
            $builder->getModel()->getTable() . '.' . $column->getCondition()->getOtherField(),
        );

        $len = count($column->getFields()) - 1;
        foreach ($column->getFields() as $i => $field) {
            $concatenated[] = $column->getJoinTable() . '.' . $field;
            if ($i < $len) {
                $concatenated[] = ' ';
            }
        }
        if (count($concatenated) > 1) {
            $this->scopes[] = 'CONCAT(' . implode(',', $concatenated) . ') AS ' . $column->getColumn();
        } else {
            $this->scopes[] = $concatenated[0] . ' AS ' . $column->getColumn();
        }

        foreach ($concatenated as $set) {
            $builder->orWhere($set, 'LIKE', "%" . $this->getSearchValue() . "%");
        }
    }

    /**
     * Return the searchable columns based on the model and the second will be the fields to search for
     *
     * @param Builder $builder
     * @return array
     */
    protected function getSearchableColumns (Builder $builder): array
    {
        // first, get the searchable columns for this model
        $wildcardColumns = $searchableColumns = $builder->getModel()->getSearchableColumns();

        // if the optional fields parameter was g$scopeziven in the request, add them to the wildcard columns array
        if (request()->input('fields')) {
            $wildcardColumns = explode(static::$fieldSeparator, request()->input('fields'));
        }

        return [$searchableColumns, $wildcardColumns];
    }

    /**
     * Return an array for all the `select` scopes you want added to the query builder ( and the query )
     *
     * @param Builder $builder
     * @return array|string[]
     */
    protected function getSelectScopes (Builder $builder)
    {
        // start with the base table and then all columns
        $this->scopes = [$builder->getModel()->getTable() . '.*'];

        // if there's specific columns, merge those with the origin
        if (is_array($builder->getQuery()->columns)) {
            foreach($builder->getQuery()->columns as $column) {
                if(str_contains($column, '.')) {
                    $table = explode('.', $column)[0];
                    if($table == $builder->getModel()->getTable()){
                        $this->scopes = [];
                        break;
                    }
                }
            }
            $this->scopes = array_unique(array_merge($builder->getQuery()->columns, $this->scopes));
        }
        return $this->scopes;
    }

    /**
     * Add a concatenated field to the query
     *
     * @param Builder $builder
     * @param array $fields
     * @param string $baseTable
     * @param string $value
     */
    protected function addConcatenatedFields (Builder $builder, array $fields, string $baseTable, string $value)
    {
        $tableColumns = $this->getTableColumns($builder);

        $as = 'concat_result';
        if (!in_array(end($fields), $tableColumns)) {
            $as = array_pop($fields);
        }

        $targetFields = [];

        foreach ($fields as $field) {
            if (in_array($field, $tableColumns)) {
                $table = $baseTable;
                if (strpos($field, '.') !== false) {
                    [$table, $columns] = $this->getJoinColumns($builder, $field);
                }

                $targetFields[] = $table . '.' . $field;
            } else {
                $targetFields[] = '" "';
            }
        }
        $concat = 'CONCAT(' . implode(',', $targetFields) . ')';

        $builder->orWhere(DB::raw($concat), 'like', "%" . $value . '%');
    }

    /**
     * Join field
     *
     * Get the joined Table and associated columns to append to the query.
     *
     * @param Builder $builder
     * @param string $field
     *
     * @return array
     */
    protected function getJoinColumns (Builder $builder, string $field)
    {
        // get the relation ( the joinable table ) and the column on that table
        [$relation, $joinIdentifier] = explode('.', $field);
        // get the primary key to join on and its corresponding column to select
        [$key, $columnField] = explode('|', $joinIdentifier);

        // default to $key, which in most cases will be <table>_id and the foreigns local key will be id
        $foreignKey = $key;
        $localKey = 'id';

        // if the $key has a tilde (~), we can explode those fields as well, overriding the default behaviour
        if (strpos($key, static::$keySeparator) !== false) {
            [$foreignKey, $localKey] = explode(static::$keySeparator, $key);
        }

        // we know have all the fields we want to join on, now its time to actually prepare the Join
        $columns = explode(',', $columnField);
        $table = $builder->getRelation($relation)->getModel()->getTable();

        $builder->leftJoin(
            $table,
            $table . '.' . $foreignKey,
            '=',
            $builder->getModel()->getTable() . '.' . $localKey
        );

        return [$table, $columns];
    }

    /**
     * Get all table columns for a model.
     *
     * Get the table columns for a given model and cache them, but always return an array containing all the listings
     *
     * @param Builder $builder
     *
     * @return array|Repository
     */
    protected function getTableColumns (Builder $builder)
    {
        try {
            return persist_cache(
                static::$wildcardPrefix . $builder->getModel()->getTable(), function () use ($builder) {
                return $builder->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($builder->getModel()->getTable());
            },  60 * 60 * 24 * 7
            );
        } catch (Exception $e) {
            return $builder->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($builder->getModel()->getTable());
        }
    }

}

