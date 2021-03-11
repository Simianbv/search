<?php


namespace Simianbv\Search\Search;


use Simianbv\Search\SearchResult;

class BaseSearch
{

    /**
     * @var mixed
     */
    protected $search_value;

    /**
     * @var SearchResult
     */
    protected $search_result;

    public function __construct (SearchResult $searchResult)
    {
        $this->search_result = $searchResult;
    }

    public function setSearchValue ($value)
    {
        $this->search_value = $value;
    }

    public function getSearchValue ()
    {
        return $this->search_value;
    }

}
