<?php



/**
 * The SearchFilter class should be used to filter trough tabular data like offset, pagination and sorting
 * Copyright (c) 2019.
 */

namespace Simianbv\Search;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Simianbv\Search\Contracts\RelationGuardInterface;
use Simianbv\Search\Contracts\SearchResultInterface;
use Simianbv\Search\Exceptions\SearchException;

/**
 * Class SearchResult
 *
 * @method static apply($target, $request = null)
 * @package Simianbv\Search
 */
class SearchResult implements SearchResultInterface
{
    /**
     * @var QueryFilter
     */
    private $queryFilter;

    /**
     * @var Builder
     */
    private $builder;

    /**
     * @var Request
     */
    private $request;

    /**
     * The name of the model
     * @var string
     */
    private $modelName = null;

    /**
     * @var bool
     */
    protected $has_evaluated = false;


    /**
     * SearchFilter constructor.
     */
    public function __construct()
    {
        $this->queryFilter = new QueryFilter();
    }

    /**
     * Extrude all the filterable fields from the GET request and start applying them
     *
     * @param Builder|Model|string $target
     * @param array                $meta
     * @param Request|null         $request
     *
     * @return SearchResult
     * @throws SearchException
     * @throws Exception
     */
    public function _apply($target, array $meta = [], $request = null)
    {
        if ($request === null) {
            $request = request();
        }

        if ($target instanceof Collection) {
            throw new Exception("Unable to determine the class, you've passed in a Collection object, therefore the results have already been loaded.");
        }

        $this->target = $target;
        $this->request = $request;

        if ($target instanceof Builder) {
            $this->builder = $target;
        }

        if ($target instanceof Model) {
            $this->builder = $target->newQuery();
        }

        if (is_string($target)) {
            if (!class_exists($target)) {
                throw new SearchException("Unable to determine that the class exists.");
            }

            $model = new $target;
            if (!$model instanceof Model) {
                throw new SearchException("Unable to perform search filters on a non-model object.");
            }
            $this->builder = $model->newQuery();
        }

        return $this;
    }

    /**
     * Evaluate the request, parse the filters found in the GET request and apply them to the query filter, once applied, return
     * the resulting query builder filters found in the /search/ directory.
     *
     * @param $request
     *
     * @return $this
     */
    public function evaluate($request = null)
    {
        if ($request === null) {
            $request = request();
        }

        // apply the filters from the request and whatever you've set up
        $this->queryFilter->apply($request);

        // notify that we've evaluated
        $this->has_evaluated = true;

        $this->builder = $this->relatedWith($this->builder);

        // apply the (custom) filters to the query builder
        $this->builder = $this->applyDecoratorsFromRequest();

        // return
        return $this;
    }

    /**
     * Include the related with parameters found in the query's GET parameters ( if any )
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function relatedWith($builder)
    {
        $modelName = $this->getModelName(true);
        if ($withRequest = $this->request->get('with')) {
            if (in_array(RelationGuardInterface::class, class_implements($modelName))) {
                $parts = array_map('trim', explode(',', $withRequest));
                foreach ($parts as $with) {
                    $guardedRelations = $modelName::getGuardedRelations();

                    // @todo: add a way to include ACL verification in here
                    if (array_key_exists($with, $guardedRelations)) {
                        $builder->with($with);
                    }
                }
            }
        }

        return $builder;
    }

    /**
     * Returns true if an evaluation pass has been made already.
     *
     * @return bool
     */
    public function hasEvaluated()
    {
        return $this->has_evaluated;
    }

    /**
     * Overrides and / or adds the filter to the query.
     *
     * @param        $filter
     * @param        $value
     * @param string $mode
     *
     * @return SearchResult
     */
    public function with($filter, $value, $mode = 'after')
    {
        $this->has_evaluated = false;
        $this->queryFilter->extend($mode, $filter, $value);
        return $this;
    }

    /**
     * Determine the base class of the model.
     *
     * @param bool $namespace
     *
     * @return mixed
     */
    protected function getModelName($namespace = false)
    {
        if ($this->builder) {
            if ($namespace) {
                return get_class($this->builder->getModel());
            }

            $parts = explode('\\', get_class($this->builder->getModel()));
            $this->modelName = end($parts);
        }
        return $this->modelName;
    }

    /**
     * Apply the decorator filters from the /Filters/ directory if need-be.
     *
     * @return Builder
     */
    private function applyDecoratorsFromRequest()
    {
        $namespace = $this->getModelName();

        foreach ($this->queryFilter->all() as $filterName => $value) {
            if ($value === null || empty($value)) {
                continue;
            }
            // the name of the decorator class
            $decorator = static::createFilterDecorator($filterName);

            // if immediately a valid class, apply the filter directly
            if (static::isValidDecorator($decorator)) {
                $decoratorFilter = new $decorator($this);
                
                $this->builder = $decoratorFilter->apply($this->builder, $value);
            } else {
                // if a custom filter decorator needs to be created
                $decorator = static::createCustomFilterDecorator($namespace, $filterName);
                if (static::isValidDecorator($decorator)) {
                    $this->builder = $decorator::apply($this->builder, $value);
                }
            }
        }

        return $this->builder;
    }

    /**
     * @return QueryFilter
     */
    public function getQueryFilter(): QueryFilter
    {
        return $this->queryFilter;
    }

    /**
     * @return Builder|LengthAwarePaginator
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @param $name
     *
     * @return string
     */
    private static function createFilterDecorator($name)
    {
        return __NAMESPACE__ . '\\Search\\' . Str::studly($name);
    }

    /**
     * @param $namespace
     * @param $name
     *
     * @return string
     */
    private static function createCustomFilterDecorator($namespace, $name)
    {
        return '\\App\\Lightning\\Search\\Filters\\' . $namespace . '\\' . Str::studly($name);
    }

    /**
     * @param $decorator
     *
     * @return bool
     */
    private static function isValidDecorator($decorator)
    {
        return class_exists($decorator);
    }

    /**
     * Magic __call override, which is used to map the static calling of methods to their dynamic counter parts.
     *
     * @param string $name
     * @param mixed  $arguments
     *
     * @return mixed|SearchResult
     */
    public static function __callStatic($name, $arguments)
    {
        $searchFilter = new self();
        return call_user_func_array([$searchFilter, '_' . $name], $arguments);
    }

    /**
     * Magic __call override, which is used to map the static calling of methods to their dynamic counter parts.
     *
     * @param string $name
     * @param mixed  $arguments
     *
     * @return mixed|SearchResult
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, '_' . $name)) {
            return call_user_func_array([$this, '_' . $name], $arguments);
        }
    }
}
