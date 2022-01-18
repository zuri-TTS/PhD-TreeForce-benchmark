<?php

final class DataSet
{

    private const ruleDir = 'rules';

    private const dataSetDir = 'datasets';

    private const groupsBasePath = 'benchmark/data';

    public const originalDataSet = 'original';

    private string $benchmarkBasePath;

    private string $group;

    private ?array $rules;

    private string $theRules;

    private array $qualifiers = [];

    // ========================================================================
    public function __construct(string $id)
    {
        $this->benchmarkBasePath = getBenchmarkBasePath();
        list ($group, $rules, $qualifiers) = self::parseDataSetId($id);
        $this->theRules = '';
        $this->setGroup($group);
        $this->setRules($rules);
        $this->setQualifiers($qualifiers);
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getRules(): array
    {
        return $this->rules ?? $this->getAllRules();
    }

    public function getTheRules(): string
    {
        return $this->theRules;
    }

    public function getQualifiers(): array
    {
        return $this->qualifiers;
    }

    public function setGroup(string $group): DataSet
    {
        $this->group = $group;
        return $this;
    }

    public function setRulesArray(array $rules): DataSet
    {
        $thus->rules = [];

        foreach ($rules as $r)
            $this->rules[] = $this->parseRules($r);

        return $this;
    }

    public function setTheRules(string $theRules): DataSet
    {
        if (! \in_array($theRules, $this->rules))
            throw new \Exception("The rules $theRules must be an item of: " . implode(',', $this->rules));

        $this->theRules = $theRules;
        return $this;
    }

    public function setRules($rules): DataSet
    {
        $this->rules = $this->parseRules($rules);

        if (empty($this->rules))
            $this->theRules = '';
        elseif (! \in_array($this->theRules, $this->rules))
            $this->theRules = $rules[0] ?? '';

        return $this;
    }

    public function setQualifiers(array $qualifiers): DataSet
    {
        $this->qualifiers = $qualifiers;
        \sort($this->qualifiers);
        return $this;
    }

    // ========================================================================
    private function parseRules($rules): array
    {
        if (null === $rules)
            $rules = $this->getAllRules();
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
        return $rules ?? $this->theRules ?? $this->rules[0] ?? null;
    }

    // ========================================================================
    public function rulesBasePath(): string
    {
        return self::getRulesBasePath($this->group);
    }

    public function groupPath(): string
    {
        return self::_groupPath($this->group);
    }

    public function rulesPath(): string
    {
        return self::getRulesBasePath($this->group);
    }

    public function theRulesPath(): string
    {
        return self::_theRulesPath($this->group, $this->theRules);
    }

    public function dataSetPath(): string
    {
        return self::_dataSetPath($this->group, $this->theRules, $this->qualifiers);
    }

    public function getTheId(): string
    {
        return self::_getTheId($this->group, $this->theRules, $this->qualifiers);
    }

    public function getId(): string
    {
        return self::_getId($this->group, $this->rules, $this->qualifiers);
    }

    public function qualifiersString(): string
    {
        return self::getQualifiersString($this->qualifiers);
    }

    // ========================================================================
    public function allNotExists(): array
    {
        return self::getAllNotExists($this->group, $this->rules, $this->qualifiers);
    }

    public static function getAllNotExists(string $group, array $rules, array $qualifiers = []): array
    {
        $ret = [];

        if ($rules === null)
            return [];

        foreach ($rules as $theRules) {
            if (! self::_exists($group, $theRules, $qualifiers))
                $ret[] = self::_getTheId($group, $theRules, $qualifiers);
        }
        return $ret;
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
    public static function getGroupsBasePath(): string
    {
        return getBenchmarkBasePath() . '/' . self::groupsBasePath;
    }

    public static function getRulesBasePath(string $group): string
    {
        return self::_groupPath($group) . '/' . self::ruleDir;
    }

    public static function getDataSetsBasePath(string $group): string
    {
        return self::_groupPath($group) . '/' . self::dataSetDir;
    }

    private static function parseDataSetId(string $id): array
    {
        preg_match("#^([^/]*)(?:/([^\[]*))?(?:\[(.*)\])?$#", $id, $matches);
        list (, $group, $rules, $qualifiers) = $matches + \array_fill(0, 4, '');

        return [
            $group,
            empty($rules) ? null : explode(',', $rules),
            empty($qualifiers) ? [] : explode(',', $qualifiers)
        ];
    }

    private static function getQualifiersString(array $qualifiers): string
    {
        if (empty($qualifiers))
            return '';

        $s = implode(',', $qualifiers);
        return "[$s]";
    }

    // ========================================================================
    private static function _groupPath(string $group): string
    {
        $base = self::getGroupsBasePath();
        return "$base/$group";
    }

    private static function _theRulesPath(string $group, string $theRules): string
    {
        return self::getRulesBasePath($group) . "/$theRules";
    }

    private static function _dataSetPath(string $group, string $theRules, array $qualifiers): string
    {
        $q = self::getQualifiersString($qualifiers);
        return self::getDataSetsBasePath($group) . "/$theRules$q";
    }

    private static function _getTheId(string $group, string $theRules, array $qualifiers): string
    {
        $q = self::getQualifiersString($qualifiers);

        if (! empty($theRules))
            return "$group/$theRules$q";

        return "$group$q";
    }

    private static function _getId(string $group, array $rules, array $qualifiers): string
    {
        return self::_getTheId($group, implode(',', $rules), $qualifiers);
    }

    private static function _exists(string $group, string $theRules, array $qualifiers): bool
    {
        if (empty($group) && empty($theRules))
            return true;
        if (empty($theRules))
            return self::_groupExists($group);
        if (empty($group))
            return false;
        echo $theRules, ":", (self::_theRulesPath($group, $theRules)), "\n";
        return self::_theRulesExists($group, $theRules) && self::_theDataSetExists($group, $theRules, $qualifiers);
    }

    private static function _groupExists(string $group): bool
    {
        return \is_dir(self::_groupPath($group));
    }

    private static function _theRulesExists(string $group, string $theRules): bool
    {
        return $theRules === self::originalDataSet || \is_dir(self::_theRulesPath($group, $theRules));
    }

    private static function _theDataSetExists(string $group, string $theRules, array $qualifiers): bool
    {
        return $theRules === self::originalDataSet || \is_dir(self::_dataSetPath($group, $theRules, $qualifiers));
    }
}