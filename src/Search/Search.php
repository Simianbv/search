<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Search;

use Illuminate\Support\Facades\Log;
use Simianbv\Search\Contracts\FilterInterface;
use Simianbv\Search\FilterGenerator;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;

/**
 * @class   Search
 * @package Simianbv\Search\Search
 */
class Search implements FilterInterface
{

    /**
     * @var array
     */
    protected static $positives = [
        'Ja',
        'ja',
        'Yes',
        'yes',
        'True',
        'true',
        '1',
    ];

    /**
     * @var array
     */
    protected static $negatives = [
        'Nee',
        'nee',
        'No',
        'no',
        'False',
        'false',
        '0',
    ];

    protected static $operandRegex = '/\(([\-\_\=\<\>\*\%INBETWEENNOT\~\!]{1,})\)/m';

    protected static $operands = [
        '<',
        '<=',
        '=',
        '>',
        '>=',
        '!=',
        '%*',
        '*%',
        '*',
        'IN',
        'NIN',
        'BETWEEN',
        'NOTBETWEEN',
        '~',
        '!~',
    ];

    protected static $types = [
        'string' => ['string', 'text', 'varchar', 'char'],
        'bool'   => ['bool', 'boolean'],
        'number' => ['number', 'int', 'float', 'integer', 'double', 'bigInteger', 'smallInteger', 'mediumInteger'],
        'date'   => ['date', 'datetime', 'timestamp'],
    ];

    /**
     * @var string
     */
    static $separator = '|';

    /**
     * Apply the filter on the Builder object provided and add the value to match onto the query.
     *
     * @param Builder $builder
     * @param         $value
     *
     * @return Builder
     * @throws \Exception
     * @internal implement the fields you want to query on in the method itself.
     *
     */
    public function apply (Builder $builder, $value)
    {
        $sets = [];
        $filters = [];


        // if the value is a valid string ( defaults to array )
        if (is_string($value)) {
            $sets = explode(self::$separator, $value);
        }

        // might as well return instantly
        if (count($sets) == 0 || strlen($sets[0]) == 0) {
            return $builder;
        }

        try {
            $columns = (new FilterGenerator($builder->getModel()))->getFilters();

            $baseTable = $builder->getModel()->getTable();

            if (is_array($builder->getQuery()->columns)) {

                $selectScopes = array_unique($builder->getQuery()->columns);

                $includeBaseTable = false;
                foreach ($selectScopes as $scope) {
                    if (Str::startsWith($scope, $baseTable . '.')) {
                        $includeBaseTable = true;
                    }
                }

                if (!$includeBaseTable) {
                    $selectScopes[] = $builder->getModel()->getTable() . '.*';
                }
            
            } else {
                $selectScopes = [$builder->getModel()->getTable() . '.*'];
            }

            foreach ($sets as $column => $set) {
                [$set, $operandToUse] = static::getOperand($set);
                [$column, $query] = explode('=', $set);

                $query = rtrim(trim($query));

                // a fix to also allow prefixed columns to be filtered, just to get the filter information
                // but also allow the searching of related table columns.

                $originalColumn = null;
                $table = null;

                if (strpos($column, '.')) {
                    $originalColumn = $column;
                    $parts = explode('.', $column);
                    $table = $parts[0];
                    $column = end($parts);
                }
                $filter = $columns[$column];

                if ($filter['relation']) {
                    $relation = $builder->getModel()->filters['relations'][$filter['name']];

                    if ($originalColumn && $table && $table !== $baseTable) {
                        if (self::isDeepRelationSearch($relation)) {
                            $deep = (new $relation['target_model'])->newQuery();

                            foreach ($relation['target_model_columns'] as $column) {
                                $deep->where($column, 'LIKE', '%' . $query . '%');
                            }
                            $ids = $deep->limit($relation['target_model_limit'] ?? 20)
                                ->get()
                                ->pluck($relation['target_model_pk'] ?? 'id')
                                ->toArray();

                            $selectScopes[] = $baseTable . '.*';
                            foreach ($relation['on'] as $first => $second) {
                                $builder->join($table, $baseTable . '.' . $first, '=', $table . '.' . $second);
                            }
                            $selectScopes[] = $table . '.' . $relation['column'];
                            $builder->whereIn($table . '.' . $relation['column'], $ids);
                        } else {
                            $selectScopes[] = $baseTable . '.*';
                            foreach ($relation['on'] as $first => $second) {
                                $builder->join($table, $baseTable . '.' . $first, '=', $table . '.' . $second);
                            }
                            $selectScopes[] = $table . '.' . $relation['column'];
                            self::addClause($builder, $filter['type'], $table . '.' . $relation['column'], $query, $filter, $operandToUse);
                        }
                    } else {
                        if (isset($relation['has_many_trough'])) {
                            $baseTable = $relation['has_many_trough'];
                        }
                        $builder->where($baseTable . '.' . $relation['column'], $operandToUse ?? '=', $query);
                        if ($originalColumn) {
                            $filter['name'] = $originalColumn;
                        }
                    }
                } else {
                    self::addClause($builder, $filter['type'], $baseTable . '.' . $filter['name'], $query, $filter, $operandToUse);
                }
            }
        } catch (Exception $e) {
            Log::error("Unable to process filter request, the error given is '" . $e->getMessage() . "'");
        }


        $builder->select($selectScopes);

        return $builder;
    }

    /**
     * @param array $relation
     * @return bool
     */
    public static function isDeepRelationSearch (array $relation): bool
    {
        return isset($relation['target_model']) && isset($relation['target_model_columns']);
    }

    /**
     * Get Operand
     *
     * Get the operand to use on this search query.
     *
     * @param string $input
     *
     * @static
     * @return array
     */
    public static function getOperand (string $input)
    {
        $operandToUse = '=';
        $input = preg_replace_callback(
            static::$operandRegex,
            function ($matchedOperand) use (&$operandToUse) {
                $operandToUse = $matchedOperand[1];
                return '';
            },
            $input
        );

        if (!in_array($operandToUse, static::$operands) || $operandToUse === '=') {
            $operandToUse = null;
        }

        return [$input, $operandToUse];
    }

    /**
     * @param Builder $builder
     * @param string $type
     * @param string $where
     * @param         $query
     * @param null $operandToUse
     *
     * @return Builder
     */
    protected static function addCustomWhereClause (Builder $builder, string $type, string $where, $query, $operandToUse)
    {
        if (in_array($type, static::$types['string'])) {
            switch ($operandToUse) {
                case '%*': // ends with
                    return $builder->where($where, 'LIKE', '%' . $query);
                    break;
                case '*%': // starts with
                    return $builder->where($where, 'LIKE', $query . '%');
                    break;
                case '=':
                case 'LIKE':
                case '*':
                default:
                    return $builder->where($where, 'LIKE', '%' . $query . '%');
                    break;
            }
        }

        if (in_array($type, static::$types['bool'])) {
            switch ($operandToUse) {
                default:
                    return $builder->where($where, $operandToUse, $query);
                    break;
            }
        }

        if (in_array($type, static::$types['number'])) {
            switch ($operandToUse) {
                case '~':
                    $args = explode(',', $query);
                    return (count($args) > 1) ? $builder->whereBetween($where, explode(',', $query)) : $builder;
                case '!~':
                    $args = explode(',', $query);
                    return (count($args) > 1) ? $builder->whereNotBetween($where, explode(',', $query)) : $builder;
                case 'IN':
                    return $builder->whereIn($where, $query);
                case 'NIN':
                    return $builder->whereIn($where, $query, 'and', true);
                default:
                    return $builder->where($where, $operandToUse, $query);
                    break;
            }
        }

        if (in_array($type, static::$types['date'])) {
            // @todo: add operands to use on the date fields?
            switch ($operandToUse) {
                default:
                    $dates = explode(',', $query);
                    return self::filterByDate($builder, $where, $dates);
            }
        }

        return $builder;
    }

    /**
     * @param Builder $builder
     * @param string $type
     * @param string $where
     * @param         $query
     * @param null $operandToUse
     *
     * @return Builder
     */
    protected static function addClause (Builder $builder, string $type, string $where, $query, array $column, $operandToUse = null)
    {
        if ($operandToUse !== null) {
            return static::addCustomWhereClause($builder, $type, $where, $query, $operandToUse);
        } else {
            switch ($type) {
                case 'string':
                case 'text':
                    $builder->where($where, 'LIKE', '%' . $query . '%');
                    break;
                case 'bool':
                case 'boolean':

                    // in case you want to cast the value to a null/not null value
                    if (isset($column['column_type']) && $column['column_type'] !== 'bool') {
                        $query = self::castBooleanValue($query);
                        if ($query === 1 || $query === true) {
                            $builder->whereNotNull($where);
                        } else {
                            $builder->whereNull($where);
                        }
                        break;
                    }

                    $query = self::castBooleanValue($query);
                    if ($query !== null) {
                        $builder->where($where, '=', $query);
                    }
                    break;
                case 'number':
                case 'integer':
                case 'int':
                    $builder->where($where, '=', $query);
                    break;
                case 'datetime':
                case 'date':
                    $dates = explode(',', $query);
                    self::filterByDate($builder, $where, $dates);
            }

            return $builder;
        }
    }

    /**
     * Add a date range filter to the query builder, either provide an array of multiple dates or a simple array of only 1 date.
     * If 2 dates are provided, we're gonna search in between those dates, otherwise, we'll search on the date specific.
     *
     * @param Builder $builder
     * @param string $column
     * @param array $dates
     *
     * @return Builder
     */
    public static function filterByDate (Builder $builder, string $column, array $dates)
    {
        /** @var Carbon[] $validDates */
        $validDates = [];

        foreach ($dates as $date) {
            $validDates[] = Carbon::createFromTimestamp(strtotime($date));
        }

        if (count($validDates) > 1) {
            $start = $validDates[0];
            $end = $validDates[1];

            $builder->whereBetween(
                $column, [
                           $start->format("Y-m-d") . ' 00:00:00',
                           $end->format('Y-m-d') . ' 23:59:59',
                       ]
            );
        } else {
            if (count($validDates) == 1) {
                $builder->where($column, $validDates[0]->format('Y-m-d'));
            }
        }

        return $builder;
    }

    /**
     * Cast the input given, evaluate whether or not it's a "boolean" value and return either 1 for a truefy value, or a 0 for a falsey value.
     *
     * @param string|bool|mixed $input
     *
     * @return int|null
     */
    public static function castBooleanValue ($input)
    {
        if (in_array($input, self::$positives)) {
            return 1;
        }
        if (in_array($input, self::$negatives)) {
            return 0;
        }
        return null;
    }

}
