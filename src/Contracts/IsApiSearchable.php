<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Contracts;

/**
 * @interface IsApiSearchable
 * @package   Simianbv\Search\Contracts
 */
interface IsApiSearchable
{

    /**
     * Return an array of columns that are searchable in the wild card search.
     *
     * @return array
     */
    public function getSearchableColumns(): array;

}
