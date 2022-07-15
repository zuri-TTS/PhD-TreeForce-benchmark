<?php

final class MongoImport
{

    private DataSet $dataSet;

    private static ?array $collections_cache = null;

    private static ?array $collections_stats_cache = null;

    public function __construct(DataSet $dataSet)
    {
        $this->dataSet = $dataSet;
    }

    public static function import(): void
    {
        $this->importDataSet($this->dataSet);
    }

    // ========================================================================
    public static function countDocuments(DataSet $dataSet, bool $forceEval = false): int
    {
        if (! $forceEval) {
            $v = self::$collections_stats_cache[$dataSet->group()] ?? null;

            if (isset($v))
                return $v;
        }
        $collections = $dataSet->getCollections();
        $colls = \array_map(fn ($c) => "\"$c\"", $collections);
        $colls = implode(',', $colls);
        $script = <<<EOD
        colls = [$colls];
        n = 0;

        for(collection of colls) {
            n += db.getCollection(collection).estimatedDocumentCount();
        }
        print(n);
        EOD;
        $script = \escapeshellarg($script);
        $cmd = "mongosh treeforce --quiet --eval $script";

        if (0 === ($exitCode = \simpleExec($cmd, $output, $err))) {
            $ret = (int) ($output);
            var_dump($ret);
            self::$collections_stats_cache[$dataSet->group()] = $ret;
            return $ret;
        } else {
            throw new \Exception("Error on command: $cmd\nexit code: $exitCode\n");
        }
    }

    // ========================================================================
    public static function dropDataSets(array $dataSets): void
    {
        foreach ($dataSets as $ds)
            self::dropDataSet($ds);
    }

    public static function dropDataSet(DataSet $dataSet): void
    {
        DataSets::checkNotExists([
            $dataSet
        ]);
        self::_dropCollections(...$dataSet->getCollections());
    }

    public static function dropCollection(string $collection): void
    {
        self::_dropCollection((array) $collection);
    }

    public static function dropCollections(array $collections): void
    {
        self::_dropCollection($collections);
    }

    private static function _dropCollections(string ...$collections): void
    {
        if (null !== self::$collections_cache)
            $delCache = function (array $collections) {
                \array_delete(self::$collections_cache, ...$collections);
            };
        else
            $delCache = function () {};

        echo "\nDeleting MongoDB collections:\n";

        if (empty($collections)) {
            echo "Nothing to drop\n";
            return;
        }

        $scriptColls = \implode(",\n", \array_map(fn ($c) => "'$c'", $collections));
        $script = <<<EOD
        collections = [
        $scriptColls
        ];
        ret = [];
        all = db.getCollectionNames();

        for(var coll of collections){

            if(!all.includes(coll))
                ret.push(true);
            else
                ret.push(db.getCollection(coll).drop());
        }
        print(ret);
        EOD;

        $script = \escapeshellarg($script);
        $cmd = "mongosh treeforce --quiet --eval $script";

        if (0 === ($exitCode = \simpleExec($cmd, $output, $err))) {
            $res = \json_decode($output);
            $deleted = [];

            foreach ($collections as $coll) {
                $valid = \array_shift($res);
                $del = $valid ? 'Success' : 'Failure';
                echo "$coll: $del\n";

                if ($valid)
                    $deleted[] = $coll;
            }
            $delCache($deleted);
        } else {
            throw new \Exception("Error on command: $cmd\nexit code: $exitCode\n");
        }
    }

    // ========================================================================
    public static function collectionExists(string $collection): bool
    {
        return \in_array($collection, self::getCollections());
    }

    public static function collectionsExists(array $collection): bool
    {
        return empty(\array_diff($collection, self::getCollections()));
    }

    public static function getCollections(bool $forceCheck = false): array
    {
        if (! $forceCheck && self::$collections_cache !== null)
            return self::$collections_cache;

        $script = "printjson(db.getCollectionNames());";
        $script = \escapeshellarg($script);

        $cmd = "mongosh treeforce --quiet --eval $script\n";
        \simpleExec($cmd, $output, $err);

        if (empty($output))
            return [];

        $output = \str_replace("'", '"', $output);
        return self::$collections_cache = \json_decode($output);
    }

    public static function createIndex($collection, string $indexName, int $order = 1): void
    {
        if (is_string($collection))
            $collections = (array) $collection;
        elseif ($collection instanceof DataSet)
            $collections = $collection->getCollections();
        elseif (is_array($collection))
            $collections = $collection;
        else
            throw new \Exception("Error, argument \$collection of type " . gettype($collections) . "is invalid");

        echo "Create the index '$indexName' for treeforce.$collection\n";

        $script = [];
        foreach ($collections as $collection)
            $script[] = "db.getCollection(\"$collection\").createIndex({ \"$indexName\" : $order });";

        $script = \escapeshellarg(implode("\n", $script));
        $cmd = "mongosh treeforce --quiet --eval $script";
        \simpleExec($cmd, $output, $err);
        echo $output;
    }

    // ========================================================================
    public static function importDataSet(DataSet $dataSet): void
    {
        DataSets::checkNotExists([
            $dataSet
        ]);
        self::_importCollections($dataSet, $dataSet->getCollections());
    }

    public static function importCollection(DataSet $dataSet, string $collection): void
    {
        self::importCollections($dataSet, $collection);
    }

    public static function importCollections(DataSet $dataSet, array $collections): void
    {
        self::_importCollections($dataSet, $collections);
    }

    private static function _importCollections(DataSet $dataSet, array $collections): int
    {
        $dsColls = $dataSet->getCollections();
        $collections = \array_unique($collections);
        $invalidColls = \array_diff($collections, $dsColls);

        if (! empty($invalidColls)) {
            $invalidColls = implode(',', $invalidColls);
            $dsColls = implode(',', $dsColls);
            throw new \Exception("$dataSet does not have collections [$invalidColls]; has [$dsColls]");
        }
        $ignoreCollections = \array_diff($dsColls, $collections);
        $ignoreCollections = \array_combine($ignoreCollections, \array_fill(0, \count($ignoreCollections), true));

        echo "\nImporting $dataSet\n";

        $partitions = $dataSet->getPartitions();
        $nbFails = 0;
        $loading = \array_combine($collections, \array_fill(0, \count($collections), false));

        foreach ($partitions as $partition) {
            $collectionName = $partition->getCollectionName();

            if (isset($ignoreCollections[$collectionName]));
            elseif (! $loading[$collectionName] && self::collectionExists($collectionName))
                echo "$collectionName: already exists\n";
            else {
                $nbFails += self::importPartition($dataSet, $partition);
                $loading[$collectionName] = true;
            }
        }

        if (0 === $nbFails)
            echo "Success";
        else
            echo "Failed ($nbFails documents)";

        echo "\n";
        return $nbFails;
    }

    private static function importPartition(DataSet $dataSet, \Data\PhysicalPartition $partition): int
    {
        \wdPush($dataSet->path());

        $collectionName = $partition->getCollectionName();
        $jsonFile = $partition->getJsonFile();
        echo "$jsonFile in collection: $collectionName\n";

        if (! \is_file($jsonFile))
            throw new \Exception("The file $jsonFile does not exists");

        $cname = \escapeshellarg($collectionName);
        $jsonFile = \escapeshellarg($jsonFile);
        \simpleExec("mongoimport -d treeforce -c $cname --file $jsonFile", $output, $err);

        \preg_match('/(\d+) document\(s\) failed/', $err, $matches);
        $nbFails = (int) ($matches[1] ?? - 1);

        if (null !== self::$collections_cache)
            self::$collections_cache[] = $collectionName;

        \wdPop();
        return $nbFails;
    }

    // ========================================================================
    public static function getCollectionName(DataSet $dataSet)
    {
        if (! $dataSet->hasQueryingVocabulary())
            return $dataSet->group() . DataSets::getQualifiersString($dataSet->qualifiers());

        return \str_replace('/', '_', $dataSet->id());
    }
}