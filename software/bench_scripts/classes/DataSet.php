<?php

final class DataSet
{

    private string $group;

    private string $rules;

    private array $qualifiers = [];

    // ========================================================================
    private function __construct()
    {}

    public static function create(string $group, string $rules, array $qualifiers): DataSet
    {
        $ret = new DataSet();
        $ret->group = $group;
        $ret->rules = $rules;
        $ret->qualifiers = $qualifiers;
        return $ret;
    }

    public static function getAll(): array
    {
        $ret = [];

        foreach (DataSet::getAllGroups() as $group) {
            $ret[] = new DataSet($group);
        }

        return $ret;
    }

    // ========================================================================
    public function id(): string
    {
        return DataSets::idOf($this);
    }

    public function group(): string
    {
        return $this->group;
    }

    public function rules(): string
    {
        return $this->rules;
    }

    public function qualifiers(): array
    {
        return $this->qualifiers;
    }

    public function qualifiersString(): string
    {
        return DataSets::getQualifiersString($this->qualifiers());
    }

    public function isSimplified(): bool
    {
        return \array_intersect([
            'simplified',
            'simplified.all'
        ], $this->qualifiers()) !== [];
    }

    public function exists(): bool
    {
        return DataSets::exists($this);
    }

    // ========================================================================
    public function groupPath(): string
    {
        return DataSets::getGroupPath($this->group);
    }

    public function rulesBasePath(): string
    {
        return DataSets::getRulesBasePath($this->group);
    }

    public function rulesPath(): string
    {
        return DataSets::getRulesPath($this->group, $this->rules);
    }

    public function rulesFilesPath(): array
    {
        return wdOp($this->rulesPath(), fn () => \glob("*.txt"));
    }

    public function path(): string
    {
        return DataSets::pathOf($this);
    }

    // ========================================================================
    public function __toString(): string
    {
        return $this->id();
    }
}