<?php

namespace Simianbv\Search\Search\Types\Facades;

use \Simianbv\Search\Search\Types\ExternalColumn as ExternalColumnAccessor;
use Illuminate\Support\Facades\Facade;

class ExternalColumn extends Facade
{
    protected static function getFacadeAccessor () : ExternalColumnAccessor
    {
        return new ExternalColumnAccessor();
    }

}

