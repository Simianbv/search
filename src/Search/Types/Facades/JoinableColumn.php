<?php

namespace Simianbv\Search\Search\Types\Facades;

use \Simianbv\Search\Search\Types\JoinableColumn as JoinableColumnAcccessor;
use Illuminate\Support\Facades\Facade;

class JoinableColumn extends Facade
{
    protected static function getFacadeAccessor (): JoinableColumnAcccessor
    {
        return new JoinableColumnAcccessor();
    }

}

