<?php

namespace Simianbv\Search\Search\Types\Facades;

use \Simianbv\Search\Search\Types\SearchableColumn as SearchableColumnAccessor;
use Illuminate\Support\Facades\Facade;

class SearchableColumn extends Facade
{
    protected static function getFacadeAccessor () : SearchableColumnAccessor
    {
        return new SearchableColumnAccessor();
    }

}

