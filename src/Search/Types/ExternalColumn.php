<?php

namespace Simianbv\Search\Search\Types;

use Illuminate\Database\Eloquent\Model;

class ExternalColumn extends Column
{

    protected string $related_model;

    protected array $search_columns = [];

    public function make (string $column): self
    {
        return $this->column($column);
    }

    /**
     * @param string|Model $relatedModel
     * @param string $relatedTable
     * @param array $relatedSearchableFields
     * @return $this
     */
    public function relatedTo (string $model, array $searchableColumns = []): self
    {
        if (!class_exists($model)) {
            throw new Exception("No class found based on the model properties given in the relatedTo method in the ExternalColumn");
        }

        $this->related_model = $model;

        if (!empty($searchableColumns)) {
            $this->searchInColumns($searchableColumns);
        }
        return $this;
    }

    public function getRelatedModel (): Model
    {
        return $this->related_model;
    }

    /**
     * Define how the data from the related model has to be joined in on the base model.
     *
     * @param string $localField
     * @param Condition $condition
     * @return $this
     */
    public function joinsOn (Condition $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    public function searchInColumns (array $columns): ExternalColumn
    {
        $this->search_columns = $columns;
        return $this;
    }

    public function hasRelation (): bool
    {
        return $this->related_model !== null;
    }

    public function getSearchableColumns ()
    {
        return $this->search_columns;
    }

}
