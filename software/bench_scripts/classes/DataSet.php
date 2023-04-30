<?php

final class DataSet
{

    private \Data\IJsonLoader $loader;

    private string $full_group;

    private string $group;

    private string $group_partitioning;

    private string $rules;

    private string $pidKey;

    private string $queriesId = '';

    private string $collectionsId = '';

    private \Data\IPartitioning $partitioning;

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

    public static function create(string $full_group, string $rules, array $qualifiers): DataSet
    {
        \preg_match('/^(.+)(?:\.(.+)(?:-(.+))?)?$/U', $full_group, $matches);

        $qualifiers = \array_unique($qualifiers);
        \sort($qualifiers);

        $ret = new DataSet();
        $ret->full_group = $full_group;
        $ret->group = $matches[1];
        $ret->group_partitioning = $matches[2] ?? '';
        $ret->pidKey = $matches[3] ?? 'pid';
        $ret->rules = $rules;
        $ret->qualifiers = $qualifiers;

        $ret->processQualifiers($qualifiers);

        return $ret;
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

        if ($c > 0)
            throw new \Exception("Can't handle qualifiers: " . implode(',', $remaining));
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

    public function fullGroup(): string
    {
        return $this->full_group;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function group_partitioning(): string
    {
        return $this->group_partitioning;
    }

    public function rules(): string
    {
        return $this->rules;
    }

    public function qualifiers(): array
    {
        return $this->qualifiers;
    }

    public function pidKey(): string
    {
        return $this->pidKey;
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

    public function getJsonLoader(): \Data\IJsonLoader
    {
        if (isset($this->loader))
            return $this->loader;

        return $this->loader = DataSets::getJsonLoader($this);
    }

    public function getPartitioning(): \Data\IPartitioning
    {
        if (isset($this->partitioning))
            return $this->partitioning;

        return $this->partitioning = $this->getJsonLoader()
            ->getPartitioningBuilderFor($this)
            ->load();
    }

    public function getPartitions(): array
    {
        return $this->getPartitioning()->getPartitions();
    }

    // ========================================================================
    public function drop()
    {
        $basepath = $this->groupPath();
        \wdPush($basepath);
        $dir = $this->directory();

        if (\is_dir($dir))
            \rrmdir($dir);

        \wdPop();
    }

    public function dropEmpty()
    {
        $basepath = $this->groupPath();
        \wdPush($basepath);
        $dir = $this->directory();

        if (\is_dir($dir))
            \rmdir($dir);

        \wdPop();
    }

    // ========================================================================
    public function directory(): string
    {
        return DataSets::directoryOf($this);
    }

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