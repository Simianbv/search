<?php
/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search;

use Illuminate\Support\ServiceProvider;

/**
 * @class   SearchServiceProvider
 * @package Simianbv\Search
 */
class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/api.php');

        $this->publishes(
            [
                __DIR__ . '/search-api.php' => config_path('search-api.php'),
            ], "search-api"
        );
    }
}
