<?php

final class DataSets
{

    private const ruleDir = 'rules';

    private const dataSetDir = 'datasets';

    private const groupsBasePath = 'benchmark/data';

    public static function all(array $ids): array
    {
        $dataSets = [];

        foreach ($ids as $id)
            $dataSets = \array_merge($dataSets, DataSets::allFromId($id));

        return $dataSets;
    }

    public static function allFromId(?string $id = null): array
    {
        if (null === $id)
            $groups = $rulesSets = $qualifierss = [];
        else {
            preg_match("#^(.*)(?:/(.*))?(?:\[(.*)\])?$#U", $id, $matches);
            list (, $groups, $rulesSets, $qualifierss) = $matches + \array_fill(0, 4, '');
        }
        $groups = empty($groups) ? self::getAllGroups() : \explode(',', $groups);
        $rulesSets = empty($rulesSets) ? [] : \explode(',', $rulesSets);
        $qualifierss = empty($qualifierss) ? [
            ''
        ] : \explode(';', $qualifierss);

        $groups = \array_merge(...\array_map(fn ($g) => self::expand($g, 'DataSets::getAllGroups'), $groups));

        \natcasesort($groups);

        foreach ($qualifierss as &$q) {
            $q = \array_values(\array_filter(\explode(',', $q)));
            \natcasesort($q);
        }
        unset($q);

        $ret = [];

        foreach ($groups as $group) {

            if (empty($rulesSets))
                $eRulesSets = self::getAllRules($group);
            else {
                $eRulesSets = \array_merge(...\array_map(fn ($g) => self::expand($g, fn () => self::getAllRules($group)), $rulesSets));
                \natcasesort($eRulesSets);
            }

            if (empty($eRulesSets))
                $eRulesSets = (array) '#no_rules';

            foreach ($eRulesSets as $rulesSet) {
                foreach ($qualifierss as $q)
                    $ret[] = DataSet::create($group, $rulesSet, $q);
            }
        }
        return $ret;
    }

    public static function fromId(string $id): DataSet
    {
        $ret = self::allFromId($id);

        if (\count($ret) > 1)
            throw new Exception("$id represents multiple DataSets");

        return $ret[0];
    }

    private static function expand(string $s, callable $getAll): array
    {
        $filter = null;

        if ($s[0] === '!') {
            $pattern = substr($s, 1);
            $filter = fn ($p) => \fnmatch($pattern, $p);
        } elseif ($s[0] === '~') {
            $filter = fn ($p) => \preg_match($s, $p);
        }

        if (null !== $filter)
            return \array_filter($getAll(), $filter);

        return (array) $s;
    }

    // ========================================================================
    public static function exists(DataSet $dataset, bool $checkDataSet = true): bool
    {
        return self::_exists($dataset->group(), $dataset->rules(), $dataset->qualifiers(), $checkDataSet);
    }

    public static function allNotExists(array $datasets, bool $checkDataSet = true): array
    {
        $notExists = [];

        foreach ($datasets as $ds)
            if (! self::exists($ds, $checkDataSet))
                $notExists[] = $ds;

        return $notExists;
    }

    public static function checkNotExists(array $datasets, bool $checkDataSet = true): void
    {
        $notExists = DataSets::allNotExists($datasets, $checkDataSet);

        if (! empty($notExists)) {
            $s = implode("\n", $notExists);
            throw new \Exception("Some DataSets do not exists:\n$s\n");
        }
    }

    // ========================================================================
    public static function getAllGroups(): array
    {
        $ret = \scandirNoPoints(self::getGroupsBasePath());
        return \array_filter($ret, fn ($g) => $g[0] !== "#");
    }

    public function getAllRules(string $group): array
    {
        $dir = self::getRulesBasePath($group);

        if (! \is_dir($dir))
            return [];

        return \scandirNoPoints($dir);
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

    public static function getGroupPath(string $group): string
    {
        return self::_groupPath($group);
    }

    public static function getRulesPath(string $group, string $rulesSet): string
    {
        return self::_theRulesPath($group, $rulesSet);
    }

    public static function getDataSetsBasePath(string $group): string
    {
        return self::_groupPath($group) . '/' . self::dataSetDir;
    }

    public static function pathOf(DataSet $dataset): string
    {
        return self::_dataSetPath($dataset->group(), $dataset->rules(), $dataset->qualifiers());
    }

    public static function idOf(DataSet $dataset): string
    {
        return self::_getId($dataset->group(), $dataset->rules(), $dataset->qualifiers());
    }

    public static function getQualifiersString(array $qualifiers): string
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

    private static function _getId(string $group, string $theRules, array $qualifiers): string
    {
        $q = self::getQualifiersString($qualifiers);

        if (! empty($theRules))
            return "$group/$theRules$q";

        return "$group$q";
    }

    private static function _exists(string $group, string $theRules, array $qualifiers, bool $checkDataSet = true): bool
    {
        if (empty($group) && empty($theRules))
            return true;
        if (empty($theRules))
            return self::_groupExists($group);
        if (empty($group))
            return false;

        return self::_theRulesExists($group, $theRules) && //
        (! $checkDataSet || self::_theDataSetExists($group, $theRules, $qualifiers));
    }

    private static function _groupExists(string $group): bool
    {
        return \is_dir(self::_groupPath($group));
    }

    private static function _theRulesExists(string $group, string $theRules): bool
    {
        return \is_dir(self::_theRulesPath($group, $theRules));
    }

    private static function _theDataSetExists(string $group, string $theRules, array $qualifiers): bool
    {
        return \is_dir(self::_dataSetPath($group, $theRules, $qualifiers));
    }
}