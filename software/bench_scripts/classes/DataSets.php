<?php

final class DataSets
{

    private const ruleDir = 'rules';

    private const dataSetDir = 'datasets';

    private const groupsBasePath = 'benchmark/data';

    // ========================================================================
    public static function allGroups($ids = null): array
    {
        return \Help\Thing::allThings('DataSets::getAllGroups', $ids);
    }

    public static function allRules(string $group, $ids = null): array
    {
        return \Help\Thing::allThings('DataSets::getAllRules', $group, $ids);
    }

    public static function allQueries(string $group, $ids = null): array
    {
        return \Help\Thing::allThings('DataSets::getAllQueries', $group, $ids);
    }

    public static function allCollections(DataSet $ds, $ids = null): array
    {
        return \Help\Thing::allThings(fn () => \Data\Partitions::getCollectionsOf($ds->getPartitions()), $ids);
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

    public static function groupOf(array $datasets)
    {
        $groups = \array_map(fn ($ds) => $ds->group(), $datasets);
        $groups = \array_unique($groups);

        if (\count($groups) > 1) {
            $s = \Help\Arrays::encode($groups);
            throw new \Exception("Multiple dataset groups: $s");
        }
        return \Help\Arrays::first($groups);
    }

    // ========================================================================
    public static function all($ids): array
    {
        if (! \is_array($ids))
            $ids = (array) $ids;
        if (empty($ids))
            $ids = (array) '';

        return \array_map_merge(function ($k) {

            if ($k instanceof DataSet)
                return [
                    $k
                ];

            return DataSets::one($k);
        }, $ids);
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

    public static function getGroupConfig(string $group): array
    {
        return include self::getGroupPath($group) . '/config.php';
    }

    public static function getGroupConfigPath(string $group): string
    {
        return self::getGroupPath($group) . '/config.php';
    }

    public static function getJsonLoader(array $dataSets): \Data\IJsonLoader
    {
        $group = $dataSets[0]->group();
        $config = self::getGroupConfig($group);

        if (! isset($config['loader']))
            throw new \Exception("The field 'loader' must be defined in $group/config.php");
        if (! \class_exists($config['loader']))
            throw new \Exception("The class {$config['loader']} does not exists");

        $loader = $config['loader'];
        unset($config['loader']);
        $loader = new $loader($dataSets, $config);

        if ($loader instanceof \Data\IXMLLoader)
            $loader = XMLLoader::create($loader, $dataSets);

        return $loader;
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

    public static function getAllRules(string $group): array
    {
        $dir = self::getRulesBasePath($group);

        if (! \is_dir($dir))
            return [];

        return \scandirNoPoints($dir);
    }

    public static function getAllQueries(string $group): array
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

        $partitioning = $dataset->getPartitioning()->getBaseDir();

        if (! empty($partitioning))
            $path .= "/$partitioning";

        return $path;
    }

    public static function directoryOf(DataSet $dataset, bool $baseDir = true): string
    {
        return ($baseDir ? self::dataSetDir . '/' : '') . //
        self::_dataSetDirectory($dataset->rules(), $dataset->qualifiers());
    }

    public static function idOf(DataSet $dataset): string
    {
        return self::_getId($dataset->group(), $dataset->getPartitioning(), $dataset->rules(), $dataset->qualifiers());
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

    private static function _dataSetDirectory(string $theRules, array $qualifiers): string
    {
        $q = self::getQualifiersString($qualifiers);
        return "$theRules$q";
    }

    private static function _dataSetPath(string $group, string $theRules, array $qualifiers): string
    {
        $dirname = self::_dataSetDirectory($theRules, $qualifiers);
        return self::getDataSetsBasePath($group) . "/$dirname";
    }

    private static function _getId(string $group, \Data\IPartitioning $partitioning, string $theRules, array $qualifiers): string
    {
        $pid = $partitioning->getID();

        if (! empty($pid))
            $pid = ".$pid";

        $q = self::getQualifiersString($qualifiers);

        if (! empty($theRules))
            return "$group$pid/$theRules$q";

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