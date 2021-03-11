<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;
use Simianbv\Search\Contracts\SearchResultInterface;


/**
 * @description The Base class to use for resource collections, should implement 2 fields,
 * i.e. the current_page and the per_page filters
 *
 * @class       ApiCollection
 * @package     App\Http\Resources
 */
class ApiCollection extends ResourceCollection
{
    /**
     * @var SearchResult
     */
    protected $searchResult = null;

    /**
     * @var array
     */
    public $resource = null;

    /**
     * GroupCollection constructor.
     *
     * @param SearchResult|mixed $result
     * @param callable|null      $formatter
     */
    public function __construct($result, $formatter = null)
    {
        if ($result instanceof SearchResultInterface) {
            $this->searchResult = $result;
            if (!$this->searchResult->hasEvaluated()) {
                $this->searchResult->evaluate();
            }
            $this->resource = $this->searchResult->getBuilder();
        } else {
            $this->resource = $result;
        }

        if ($formatter && is_callable($formatter)) {
            $this->rowFormat($formatter);
        }

        parent::__construct($this->resource);
    }

    /**
     * @param callable $callback
     *
     * @return ApiCollection
     */
    protected function rowFormat(callable $callback)
    {
        if (is_array($this->resource)) {
            foreach ($this->resource as $item) {
                $callback($item);
            }
        }
        return $this;
    }
}
