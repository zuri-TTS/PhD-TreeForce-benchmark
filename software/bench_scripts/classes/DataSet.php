<?php

final class DataSet
{

    private \Data\ILoader $loader;

    private string $group;

    private string $rules;

    private string $queriesId = '';

    private array $stats;

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

    // ========================================================================
    public function setQueriesId(string $id): self
    {
        $this->queriesId = $id;
        return $this;
    }

    public function getQueries(): array
    {
        return DataSets::allQueries($this->group, $this->queriesId);
    }

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

    public function stats(): array
    {
        $path = $this->stats_filePath();

        $def = [
            'documents.nb' => - 1
        ];
        if (\is_file($path))
            return (include $path) + $def;

        return $def;
    }

    public function config(): array
    {
        return include $this->config_filePath();
    }

    private function config_filePath(): string
    {
        return DataSets::getGroupConfigPath($this->group);
    }

    private function stats_filePath(): string
    {
        return $this->groupPath() . '/stats.php';
    }

    public function getGroupLoader(): \Data\ILoader
    {
        if (isset($this->loader))
            return $this->loader;

        return $this->loader = DataSets::getGroupLoader($this->group);
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
        $path = $this->rulesPath();

        if (\is_file($path))
            return (array) $path;

        return wdOp($path, fn () => \glob("*.txt"));
    }

    public function hasQueryingVocabulary(): bool
    {
        return \in_array("querying.txt", $this->rulesFilesPath());
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