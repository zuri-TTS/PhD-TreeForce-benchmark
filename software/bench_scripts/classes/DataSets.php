<?php

final class DataSets
{

    private const ruleDir = 'rules';

    private const dataSetDir = 'datasets';

    private const groupsBasePath = 'benchmark/data';

    // ========================================================================
    private static function allThings(callable $getAllThings, ...$args): array
    {
        $ids = \array_pop($args);

        if (empty($ids))
            return $getAllThings(...$args);

        if (\is_string($ids))
            $ids = (array) $ids;

        $things = [];
        $allThings = $getAllThings(...$args);

        foreach ($ids as $id)
            $things = \array_merge($things, self::oneThing($id, $allThings));

        \natcasesort($things);
        return \array_unique($things);
    }

    private static function oneThing(string $id, $allThings): array
    {
        $things = empty($id) ? $allThings : \explode(',', $id);
        $things = \array_map_merge(fn ($t) => self::expand($t, fn () => $allThings), $things);
        return $things;
    }

    // ========================================================================
    public static function allGroups($ids = null): array
    {
        return self::allThings('DataSets::_getAllGroups', $ids);
    }

    public static function allRules(string $group, $ids = null): array
    {
        return self::allThings('DataSets::_getAllRules', $group, $ids);
    }

    public static function allQueries(string $group, $ids = null): array
    {
        return self::allThings('DataSets::_getAllQueries', $group, $ids);
    }

    public static function allCollections(DataSet $ds, $ids = null): array
    {
        return self::allThings(fn () => \Data\Partitions::getCollectionsOf($ds->getPartitions()), $ids);
    }

    // ========================================================================
    public static function groupExists(string $group): bool
    {
        return self::_groupExists($group);
    }

    public static function allGroupsNotExists(array $groups)
    {
        $notExists = [];

        foreach ($groups as $gr)
            if (! self::groupExists($gr))
                $notExists[] = $gr;

        return $notExists;
    }

    public static function checkGroupsNotExists(array $groups): void
    {
        $notExists = DataSets::allGroupsNotExists($groups);

        if (! empty($notExists)) {
            $s = implode("\n", $notExists);
            throw new \Exception("Some groups do not exists:\n$s\n");
        }
    }

    // ========================================================================
    public static function all($ids): array
    {
        if (! \is_array($ids))
            $ids = (array) $ids;
        if (empty($ids))
            $ids = (array) '';

        return \array_map_merge(fn ($k) => DataSets::one($k), $ids);
    }

    private static function one(string $id): array
    {
        preg_match("#^(.*)(?:\[(.*)\])?$#U", $id, $matches);
        list (, $id, $qualifierss) = $matches + \array_fill(0, 3, '');

        list ($groups, $rules, $queries, $collsIDs) = explode('/', $id) + \array_fill(0, 4, '');

        $groups = self::allGroups($groups);
        $qualifierss = empty($qualifierss) ? [
            ''
        ] : \explode('|', $qualifierss);

        foreach ($qualifierss as &$q) {
            $q = \array_values(\array_filter(\preg_split('/[,;]/', $q)));
            \natcasesort($q);
        }
        unset($q);

        $ret = [];

        foreach ($groups as $group) {
            list ($group, $partition) = \explode('.', $group, 2) + \array_fill(0, 2, '');

            $eRulesSets = self::allRules($group, $rules);

            if (empty($eRulesSets))
                $eRulesSets = (array) '#no_rules';

            foreach ($eRulesSets as $rulesSet) {
                foreach ($qualifierss as $q) {
                    $ret[] = DataSet::create($group, $partition, $rulesSet, $q)-> //
                    setQueriesId($queries)-> //
                    setCollectionsId($collsIDs);
                }
            }
        }
        return $ret;
    }

    public static function fromId(string $id): DataSet
    {
        $ret = self::all($id);

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

    public static function getGroupConfig(string $group): array
    {
        return include self::getGroupPath($group) . '/config.php';
    }

    public static function getGroupConfigPath(string $group): string
    {
        return self::getGroupPath($group) . '/config.php';
    }

    public static function getGroupLoader(string $group): \Data\ILoader
    {
        $config = self::getGroupConfig($group);

        if (! isset($config['loader']))
            throw new \Exception("The field 'loader' must be defined in $group/config.php");
        if (! \class_exists($config['loader']))
            throw new \Exception("The class {$config['loader']} does not exists");

        $loader = $config['loader'];
        unset($config['loader']);
        return new $loader($group, $config);
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
    private static function _getAllGroups(): array
    {
        $ret = \scandirNoPoints(self::getGroupsBasePath());
        return \array_filter($ret, fn ($g) => $g[0] !== "#");
    }

    private static function _getAllRules(string $group): array
    {
        $dir = self::getRulesBasePath($group);

        if (! \is_dir($dir))
            return [];

        return \scandirNoPoints($dir);
    }

    private static function _getAllQueries(string $group): array
    {
        $dir = self::getQueriesBasePath($group);

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
        return getBenchmarkBasePath() . "/benchmark/rules_conf/symlinks/$group";
    }

    public static function getQueriesBasePath(string $group): string
    {
        return getBenchmarkBasePath() . "/benchmark/queries_conf/symlinks/$group";
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
        $path = self::_dataSetPath($dataset->group(), $dataset->rules(), $dataset->qualifiers());

        $partitioning = $dataset->getPartitioning()->getID();

        if (! empty($partitioning))
            $path .= "/$partitioning";

        return $path;
    }

    public static function idOf(DataSet $dataset): string
    {
        return self::_getId($dataset->group(), $dataset->rules(), $dataset->qualifiers());
    }

    public static function getQualifiersString(array $qualifiers): string
    {
        if (empty($qualifiers))
            return '';

        $s = implode(';', $qualifiers);
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
        $path = self::_theRulesPath($group, $theRules);
        return \is_file($path) || \is_dir($path);
    }

    private static function _theDataSetExists(string $group, string $theRules, array $qualifiers): bool
    {
        return \is_dir(self::_dataSetPath($group, $theRules, $qualifiers));
    }
}