<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * The Filter interface should be used to implement custom filters for each specific use case you want to use a filter for.
 * As we want to limit the scope of our queries, we want to allow custom filters as well specific for i.e. tabular data.
 *
 * @interface Filter
 * @package   Simianbv\Search\Contracts
 */
interface FilterInterface
{
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
    public static function apply(Builder $builder, $value);

}
