<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Search;

use Simianbv\Search\Contracts\FilterInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @class   Paginator
 * @package Simianbv\Search\Search
 */
class Paginator implements FilterInterface
{

    /**
     * Apply the filter on the Builder object provided and add the value to match onto the query.
     *
     * @param Builder $builder
     * @param         $paging
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @internal implement the fields you want to query on in the method itself.
     */
    public static function apply(Builder $builder, $paging)
    {
        return $builder->paginate($paging['per_page']);
    }
}
