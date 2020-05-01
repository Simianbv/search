<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Search;

use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Simianbv\Search\Contracts\FilterInterface;
use Simianbv\Search\Contracts\IsApiSearchable;

/**
 * @class   Wildcard
 * @package Simianbv\Search\Search
 */
class Wildcard implements FilterInterface
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
     * Apply the filter on the Builder object provided and add the value to match onto the query.
     *
     * @param Builder $builder
     * @param         $value
     *
     * @return Builder
     * @internal implement the fields you want to query on in the method itself.
     *
     */
    public static function apply(Builder $builder, $value): Builder
    {
        Log::debug("checker de check: " . $value);
        if ($builder->getModel() instanceof IsApiSearchable) {
            $searchableColumns = $builder->getModel()->getSearchableColumns();

            $wildcardFields = request()->input('fields')
                ? explode(self::$fieldSeparator, request()->input('fields'))
                : $searchableColumns;

            if (is_array($builder->getQuery()->columns)) {
                $selectScopes = array_unique(array_merge($builder->getQuery()->columns, [$builder->getModel()->getTable() . '.*']));
            } else {
                $selectScopes = [$builder->getModel()->getTable() . '.*'];
            }

            $builder->where(
                function ($query) use ($wildcardFields, $searchableColumns, $builder, $value) {
                    $baseTable = $builder->getModel()->getTable();

                    foreach ($wildcardFields as $field) {
                        // if the searchable field is an array, it means we want a concatenated field
                        if (is_array($field)) {
                            self::addConcatenatedFields($query, $field, $baseTable, $value, $selectScopes);
                            continue;
                        }

                        // else if the field given is not one of the valid fields we're allowed to search in, skip it
                        if (!in_array($field, $searchableColumns)) {
                            continue;
                        }

                        // if the field contains a dot, we probably want to join a related table to match results
                        if (strpos($field, '.') !== false) {
                            [$table, $columns] = self::getJoinColumns($builder, $field);
                            foreach ($columns as $column) {
                                $selectScopes[] = $table . '.' . $column;
                                $query->orWhere($table . '.' . $column, 'like', '%' . $value . '%');
                            }
                            continue;
                        }

                        // if no joinable columns were set, just use a 'regular and plain' where clause
                        $query->orWhere($baseTable . '.' . $field, 'like', "%" . $value . "%");
                    }
                }
            );

            $builder->select($selectScopes);
        }

        return $builder;
    }

    /**
     * Add a concatenated field to the query
     *
     *
     *
     * @param Builder $builder
     * @param array   $fields
     * @param string  $baseTable
     * @param string  $value
     * @param array   $selectScopes
     */
    protected static function addConcatenatedFields(Builder $builder, array $fields, string $baseTable, string $value, &$selectScopes)
    {
        $tableColumns = self::getTableColumns($builder);

        $as = 'concat_result';
        if (!in_array(end($fields), $tableColumns)) {
            $as = array_pop($fields);
        }

        $targetFields = [];

        foreach ($fields as $field) {
            if (in_array($field, $tableColumns)) {
                $table = $baseTable;
                if (strpos($field, '.') !== false) {
                    [$table, $columns] = self::getJoinColumns($builder, $field);
                }

                $targetFields[] = $table . '.' . $field;
            } else {
                $targetFields[] = '" "';
            }
        }
        $concat = 'CONCAT(' . implode(',', $targetFields) . ')';
        $selectScopes[] = $concat . ' as ' . $as;

        $builder->orWhere(DB::raw($concat), 'like', "%" . $value . '%');
    }

    /**
     * Join field
     *
     * Get the joined Table and associated columns to append to the query.
     *
     * @param Builder $builder
     * @param string  $field
     *
     * @return array
     */
    protected static function getJoinColumns(Builder $builder, string $field)
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

        return [$table, $columns,];
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
    protected static function getTableColumns(Builder $builder)
    {
        try {
            return persist_cache(
                self::$wildcardPrefix . $builder->getModel()->getTable(), function () use ($builder) {
                return $builder->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($builder->getModel()->getTable());
            }, 60 * 60 * 24 * 7
            );
        } catch (Exception $e) {
            return $builder->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($builder->getModel()->getTable());
        }
    }
}

