<?php

final class DataSet
{

    private const ruleDir = 'rules';

    private const dataSetDir = 'datasets';

    private const groupsBasePath = 'benchmark/data';

    public const originalDataSet = 'original';

    private string $benchmarkBasePath;

    private string $groupsBasePath;

    private string $group;

    private ?array $rules;

    // ========================================================================
    public function __construct(string $id)
    {
        $this->benchmarkBasePath = getBenchmarkBasePath();
        $this->groupsBasePath = self::groupsBasePath();
        list ($group, $rules) = self::parseDataSetId($id);
        $this->setGroup($group);
        $this->setRules($rules);
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getRules(): array
    {
        return $this->rules ?? $this->getAllRules();
    }

    public function setGroup(string $group): DataSet
    {
        $this->group = $group;
        return $this;
    }

    public function setRules($rules): DataSet
    {
        $this->rules = $this::parseRules($rules);
        return $this;
    }

    private static function parseRules($rules): ?array
    {
        if (null === $rules)
            $rules = null;
        elseif (is_array($rules))
            $rules = $rules;
        else
            $rules = [
                (string) $rules
            ];
        return $rules;
    }

    private function rulesArg(?string $rules = null): ?string
    {
        return $rules ?? $this->rules[0] ?? null;
    }

    // ========================================================================
    public function rulesBasePath(?string $group = null): string
    {
        return $this->groupPath() . '/' . self::ruleDir;
    }

    public function groupPath(?string $group = null): string
    {
        $group = $group ?? $this->group;
        return "$this->groupsBasePath/$group";
    }

    public function rulesPath(?string $rules = null, ?string $group = null): string
    {
        $group = $group ?? $this->group;
        $rules = $this->rulesArg($rules);
        return "$this->groupsBasePath/$group/" . self::ruleDir . "/$rules";
    }

    public function dataSetPath(?string $rules = null, ?string $group = null): string
    {
        $group = $group ?? $this->group;
        $rules = $this->rulesArg($rules);
        return "$this->groupsBasePath/$group/" . self::dataSetDir . "/$rules";
    }

    public function getId(?string $rules = null, ?string $group = null): string
    {
        $group = $group ?? $this->group;
        $rules = $this->rulesArg($rules);

        if (! empty($rules))
            return "$group/$rules";

        return $group;
    }

    // ========================================================================
    public function allNotExists(?string $group = null, ?array $rules = null): array
    {
        $ret = [];
        $rules = $rules ?? $this->rules;

        if ($rules === null)
            return [];

        foreach ($rules as $rulesDir) {
            if (! $this->exists($group, $rulesDir))
                $ret[] = $this->getId($rulesDir, $group);
        }
        return $ret;
    }

    public function exists(?string $group = null, ?string $rules = null): bool
    {
        $group = $group ?? $this->group;
        $rules = $this->rulesArg($rules);

        if ($group === null && $rules === null)
            return true;
        if ($rules === null)
            return $this->groupExists($group);
        if ($group === null)
            return false;
        return $this->rulesExists($rules, $group);
    }

    public function groupExists(?string $group = null): bool
    {
        $group = $group ?? $this->group;
        return \is_dir($this->groupPath($group));
    }

    public function rulesExists(?string $rules = null, ?string $group = null): bool
    {
        $rules = $this::rulesArg($rules);
        return $rules === self::originalDataSet || \is_dir($this->rulesPath($rules, $group));
    }

    // ========================================================================
    public static function getAllGroups(): array
    {
        return \scandirNoPoints(self::groupsBasePath());
    }

    public function getAllRules(?string $group = null): array
    {
        $group = $group ?? $this->group;
        $rules = \scandirNoPoints($this->rulesBasePath($group));
        $rules[] = self::originalDataSet;
        return $rules;
    }

    // ========================================================================
    public static function groupsBasePath(): string
    {
        return getBenchmarkBasePath() . '/' . self::groupsBasePath;
    }

    private static function parseDataSetId(string $id): array
    {
        $tmp = explode('/', $id);

        if (\count($tmp) == 2) {
            if ($tmp[1] === "")
                return [
                    $tmp[0],
                    null
                ];
            else
                return $tmp;
        }
        return [
            $id,
            null
        ];
    }

    // ========================================================================
}