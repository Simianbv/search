<?php

namespace Simianbv\Search\Search\Types\Facades;

use Illuminate\Support\Facades\Facade;

class Condition extends Facade
{
    protected static function getFacadeAccessor (): \Simianbv\Search\Search\Types\Condition
    {
        return new \Simianbv\Search\Search\Types\Condition();
    }
}
