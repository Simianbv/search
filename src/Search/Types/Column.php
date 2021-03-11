<?php


namespace Simianbv\Search\Search\Types;

use Illuminate\Database\Eloquent\Model;

abstract class Column
{

    protected string $join_table;

    protected string $column_name;

    protected array $fields = [];
    
    /**
     * @param string|array $field the fields you want to display in the result set
     * @param bool $concat set to true if the fields should be concatenated
     * @return JoinableColumn
     */
    public function fields ($field): self
    {
        if (is_array($field)) {
            foreach ($field as $subfield) {
                $this->fields($subfield);
            }
        } else {
            if (is_string($field)) {
                $this->fields[] = $field;
            }
        }

        if (count($this->fields) > 1) {
            $this->has_concat_fields = true;
        }

        return $this;
    }

    /**
     * Returns the fields to be used in the selection of the concat fields.
     * @return array
     */
    public function getFields (): array
    {
        return $this->fields;
    }

    /**
     * Set the name of the column to be added
     *
     * @param string $column
     * @return $this
     */
    public function column (string $column): self
    {
        $this->column_name = $column;
        return $this;
    }

    /**
     * Returns the name of the column ( or the concat AS label )
     *
     * @return string
     */
    public function getColumn (): string
    {
        return $this->column_name;
    }

    /**
     * set the join table, either accepts a string of a class or a model which has a table.
     *
     * @param string|Model $relation
     * @param string $default_table
     */
    protected function setJoinTable ($relation, string $default_table = ''): void
    {
        if ($default_table && $default_table !== '') {
            $this->join_table = $default_table;
            return;
        }

        if ($relation instanceof Model) {
            $this->join_table = $relation->getTable();
            return;
        }

        if (class_exists($relation)) {
            $model = new $relation;
            if ($model instanceof Model) {
                $this->join_table = $model->getTable();
            } else {
                throw new Exception("Unable to determine the join table, no valid model was given in the JoinableColumn argument");
            }
        } else {
            if ($relation instanceof Model) {
                $this->join_table = $relation->getTable();
            } else {
                if (is_string($relation)) {
                    $this->join_table = $relation;
                } else {
                    throw new Exception("Unable to determine what the join table is going to be, no model or table name given");
                }
            }
        }
    }

    public function getJoinTable (): string
    {
        return $this->join_table;
    }
}
