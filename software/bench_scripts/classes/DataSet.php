<?php

final class DataSet
{

    private \Data\ILoader $loader;

    private string $group;

    private string $rules;

    private string $queriesId = '';

    private string $collectionsId = '';

    private \Data\IDataLocation $dataLocation;

    private string $locationId = '';

    private array $stats;

    private array $qualifiers = [];

    private const qsimple = [
        'simplified',
        'simplified.all'
    ];

    // ========================================================================
    private function __construct()
    {}

    public static function create(string $group, string $rules, array $qualifiers): DataSet
    {
        \sort($qualifiers);
        $ret = new DataSet();
        $ret->group = $group;
        $ret->rules = $rules;
        $ret->qualifiers = $qualifiers;
        $ret->setDataLocation();

        $ret->processQualifiers($qualifiers);
        return $ret;
    }

    private function setDataLocation(string $locationId = '')
    {
        $this->locationId = $locationId;
        $this->dataLocation = \Data\DataLocations::getLocationFor($this, $locationId);
    }

    private function processQualifiers(array $qualifiers): void
    {
        $remaining = [];

        foreach ($qualifiers as $q) {
            if (\in_array($q, self::qsimple))
                continue;
            $remaining[] = $q;
        }
        $c = \count($remaining);

        if ($c > 1)
            throw new \Exception("More than one dataLocation qualifier: " . implode(',', $remaining));
        if ($c == 0)
            return;

        $this->setDataLocation(\array_pop($remaining));
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

    public function setCollectionsId(string $id): self
    {
        $this->collectionsId = $id;
        return $this;
    }

    public function getCollections(): array
    {
        return DataSets::allCollections($this, $this->collectionsId);
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

    public function dataLocation(): \Data\IDataLocation
    {
        return $this->dataLocation;
    }

    public function qualifiersString(string $default = ''): string
    {
        $ret = DataSets::getQualifiersString($this->qualifiers());

        if (empty($ret))
            return $default;

        return $ret;
    }

    public function isSimplified(): bool
    {
        return \array_intersect(self::qsimple, $this->qualifiers()) !== [];
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