<?php


namespace Simianbv\Search\Search\Types;


use Illuminate\Database\Eloquent\Model;

class JoinableColumn extends Column
{

    protected string $as_column;

    protected bool $has_concat_fields = false;

    protected array $select_fields = [];

    protected array $conditions = [];

    private string $join_type = 'left';

    private array $join_types = ['left', 'right', 'inner'];

    public function make (string $column, string $joinType = 'left'): JoinableColumn
    {
        $this->setJoinType($joinType);
        return $this->column($column);
    }

    /**
     * @param $relation Model|string Can be a model or the class path of the model
     * @param $condition Condition|Condition[] Can be an array of conditions or a Condition
     * @return JoinableColumn
     */
    public function joinBy ($relation, $condition): JoinableColumn
    {
        $this->setJoinTable($relation);

        if (is_array($condition)) {
            foreach ($condition as $ground) {
                $this->addCondition($ground);
            }
        } else {
            $this->addCondition($condition);
        }

        return $this;
    }

    public function setJoinType (string $type): self
    {
        if (in_array($type, $this->join_types)) {
            $this->join_type = $type;
        } else {
            $this->join_type = $this->join_types[0];
        }


        return $this;
    }

    public function getJoinType (): string
    {
        return $this->join_type . 'Join';
    }

    public function getCondition (): Condition
    {
        if (isset($this->conditions[0]) && $this->conditions[0] instanceof Condition) {
            return $this->conditions[0];
        }
        throw new Exception("No valid Condition argument found in the JoinableColumn, make sure you have at least 1 condition given to the joinableColumn instance");
    }

    protected function addCondition ($condition): void
    {
        if ($condition instanceof $condition) {
            $this->conditions[] = $condition;
        } else {
            if (is_array($condition) && count($condition) == 2) {
                $this->condition = Condition::make($condition[0], $condition[1]);
            }
        }
    }

}
