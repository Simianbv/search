<?php


namespace Simianbv\Search\Search\Types;


class Condition
{
    protected string $base_field;

    protected string $other_field;

    protected string $condition;

    public function make (string $baseField, string $condition, string $otherField = null): Condition
    {
        if ($otherField === null) {
            $otherField = $condition;
            $condition = '=';
        }

        $this->setBaseField($baseField);
        $this->setOtherField($otherField);
        $this->setCondition($condition);

        return $this;
    }

    public function setBaseField (string $baseField): Condition
    {
        $this->base_field = $baseField;
        return $this;
    }

    public function setOtherField (string $otherField): Condition
    {
        $this->other_field = $otherField;
        return $this;
    }

    public function setCondition (string $condition): Condition
    {
        $this->condition = $condition;
        return $this;
    }

    public function getBaseField (): string
    {
        return $this->base_field;
    }

    public function getOtherField (): string
    {
        return $this->other_field;
    }

    public function getCondition (): string
    {
        return $this->condition;
    }
}
