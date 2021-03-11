<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Search;

use Simianbv\Search\Contracts\FilterInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @class   Sort
 * @package Simianbv\Search\Search
 */
class Sort implements FilterInterface
{

    /**
     * Apply the filter on the Builder object provided and add the value to match onto the query.
     *
     * @internal implement the fields you want to query on in the method itself.
     *
     * @param Builder $builder
     * @param         $sorts
     *
     * @return Builder
     */
    public function apply(Builder $builder, $sorts): Builder
    {
        $table = $builder->getModel()->getTable() . '.';

        foreach ($sorts as $sort) {

            if (isset($builder->getModel()->filters['relations'][$sort['name']])) {
                $relation = $builder->getModel()->filters['relations'][$sort['name']];

                // fall back to regular sort fields
                $sort['name'] = $table . $sort['name'];

                // if an filter override column value exists, apply that right here
                if (isset($relation['column'])) {
                    $sort['name'] = $table . $relation['column'];
                }

            } else {

                if (strpos($sort['name'], '.') === false) {
                    $sort['name'] = $table . $sort['name'];
                }
            }

            $builder = self::sort($builder, $sort['name'], $sort['direction']);

        }
        return $builder;
    }


    /**
     * The allowed directions to sort by.
     * @var array
     */
    protected static $sortableDirections = [
        'default' => 'DESC',
        'ASC',
    ];

    /**
     * @param Builder $builder
     * @param string $column
     * @param string $direction
     *
     * @return Builder
     */
    public static function sort(Builder $builder, string $column, string $direction)
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, self::$sortableDirections)) {
            $direction = self::$sortableDirections['default'];
        }

        return $builder->orderBy($column, $direction);
    }
}
