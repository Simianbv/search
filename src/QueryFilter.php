<?php
/**
 * Extrudes the filterable data from the request query and sorts and labels them accordingly.
 */

namespace App\Lightning;

use Illuminate\Http\Request;

/**
 * Class QueryFilter
 *
 * @package App\Lightning
 */
class QueryFilter
{

    /**
     * Stores all the defaults we want to use in building up our Query in the Builder instance.
     * @var array
     */
    private static $filterDefaults = [
        'per_page' => 20,
        'page' => 1,
    ];

    protected $defaults = [
        'separator' => '|',
    ];

    /**
     * Store the filters we want to apply
     * @var array
     */
    protected $filters = [
        'search' => [],
        'wildcard' => [],
        'match' => [],
        'sort' => [],

        // make sure the paginator runs last, as it will return a LengthAwarePaginator
        'paginator' => [
            'per_page' => null,
            'page' => null,
        ],
    ];

    /**
     * Add some common aliases to the commonly used filters
     * @var array
     */
    protected $alias_filters = [
        'wildcard' => ['q', 'query'],
        'search' => ['search', 'filter'],
        'per_page' => ['per_page', 'limit'],
        'page' => ['page', 'current'],
        'sort' => ['sort', 'dir'],
    ];

    /**
     * Additional filters you can set before evaluating the query parameters.
     *
     * @var array
     */
    protected $before = [];

    /**
     * Additional filters you can set after evaluating the query parameters.
     *
     * @var array
     */
    protected $after = [];

    /**
     * @var Request|null
     */
    protected $request = null;

    /**
     * Sift trough all the get parameters and check if we need to apply filters to the result set.
     *
     * @param Request $request
     */
    public function apply(Request $request = null)
    {
        if ($request === null) {
            $request = request();
        }

        $this->runFilter('before');

        foreach ($this->alias_filters as $filter => $aliases) {
            $this->applyAliases($request, $filter, $aliases);
        }

        $this->runFilter('after');
    }

    /**
     * Apply the aliases found to the filter provided and check the aliases in the Request object to see if we have a match.
     * If we have a match for any alias, apply it to the filter provided.
     *
     * @param Request $request
     * @param string  $filter
     * @param array   $aliases
     */
    public function applyAliases(Request $request, string $filter, array $aliases)
    {
        foreach ($aliases as $alias) {
            if ($filterValue = $request->get($alias)) {
                if ($filter === 'sort') {
                    $this->filters['sort'] = $this->extrudeSortFields($filterValue);
                } else if (in_array($filter, array_keys($this->filters['paginator']))) {
                    $this->filters['paginator'][$filter] = $filterValue;
                } else {
                    $this->filters[$filter] = $filterValue;
                }
            }
        }
    }

    /**
     * Extend the Query Filter object by adding filters either to the before or after stack which run before and
     * after applying the filters found in the query.
     *
     * @param string $mode
     * @param        $filter
     * @param        $value
     *
     * @return QueryFilter
     */
    public function extend(string $mode, $filter, $value)
    {
        if ($mode === 'after') {
            $this->after[] = [
                'filter' => $filter,
                'value' => $value,
            ];
        } else if ($mode === 'before') {
            $this->before[] = [
                'filter' => $filter,
                'value' => $value,
            ];
        }
        return $this;
    }

    /**
     * Run the before and after filters to apply the custom filters set in the back-end code.
     *
     * @param string $mode
     *
     * @return $this
     */
    private function runFilter(string $mode)
    {
        $filters = [];

        if ($mode == 'before') {
            $filters = $this->before;
        } else if ($mode == 'after') {
            $filters = $this->after;
        }

        if (count($filters) == 0) {
            return $this;
        }

        foreach ($filters as $toRun) {

            $filter = $toRun['filter'];
            $value = $toRun['value'];

            if (strpos($filter, '.') !== false) {
                $parts = explode('.', $filter);

                if (isset($this->filters[$parts[0]])) {
                    if (isset($parts[1]) && isset($this->filters[$parts[0]])) {
                        if (isset($parts[2]) && isset($this->filters[$parts[0]][$parts[1]])) {
                            $this->filters[$parts[0]][$parts[1]][$parts[2]] = $value;
                        } else {
                            $this->filters[$parts[0]][$parts[1]] = $value;
                        }
                    } else {
                        $this->filters[$parts[0]] = $value;
                    }
                }
            } else {
                $this->filters[$filter] = $value;
            }
        }

        return $this;
    }


    /**
     * Extrude the sorting fields from the GET parameters and check what fields we should be sorting on.
     *
     * @param mixed|string|array $value
     *
     * @return array
     */
    public function extrudeSortFields($value)
    {
        $sortingFields = [];
        if (strpos($value, ',') !== false) {
            $sorts = explode(',', $value);
        } else {
            $sorts = [$value];
        }

        foreach ($sorts as $sort) {
            list($field, $value) = explode($this->defaults['separator'], $sort);
            $sortingFields[] = [
                'name' => $field,
                'direction' => $value,
            ];
        }

        return $sortingFields;
    }

    /**
     * @param $filter
     *
     * @return mixed
     */
    public function get($filter)
    {
        if (array_key_exists($filter, $this->filters)) {
            return $this->filters[$filter];
        }

        if (isset($this->filters['paginator'][$filter])) {
            return $this->filters['paginator'][$filter];
        }
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->filters;
    }
}
