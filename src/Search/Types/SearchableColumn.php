<?php

namespace Simianbv\Search\Search\Types;

// @todo: needs fixin in the future, no need for it now
class SearchableColumn
{

    protected string $column_name;

    protected string $related_model;

    protected string $related_table;

    protected array $related_searchable_fields = [];

    public function make (string $column): self
    {
        return $this->column($column);
    }

    public function column (string $columnName): self
    {
        $this->column_name = $columnName;
        return $this;
    }

    public function relatedTo (string $relatedModel, string $relatedTable, array $relatedSearchableFields = []): self
    {
        $this->related_model = $relatedModel;
        $this->related_table = $relatedTable;
        if (!empty($fields)) {
            $this->fields($relatedSearchableFields);
        }
        return $this;
    }

    public function fields (array $fields): self
    {
        $this->related_searchable_fields = $fields;
        return $this;
    }

    public function hasRelation (): bool
    {
        return $this->related_model !== null;
    }

    public function getSearchableFields ()
    {
        return $this->searchable_fields;
    }

    public function getColumn (): string
    {
        return $this->column_name;
    }

}
