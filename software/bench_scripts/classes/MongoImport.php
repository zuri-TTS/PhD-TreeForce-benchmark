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

    public static function dropCollection(string $collection): void
    {
        self::_dropCollection($collection);
    }

    public static function dropCollectionsOf(DataSet $dataSet): void
    {
        self::dropCollections([
            $dataSet
        ]);
    }

    public static function dropCollections(array $dataSets): void
    {
        $collections = [];

        foreach ($dataSets as $ds) {
            $dscolls = $ds->getCollections();
            $collections = \array_merge($collections, $dscolls);
        }
        self::_dropCollection(...$collections);
    }

    private static function _dropCollection(string ...$collections): void
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

    public static function collectionExists(string $collection): bool
    {
        return \in_array($collection, self::getCollections());
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

    public static function importDataSet(DataSet $dataSet): void
    {
        DataSets::checkNotExists([
            $dataSet
        ]);
        self::importCollections($dataSet, ...$dataSet->getCollections());
    }

    public static function importCollections(DataSet $dataSet, string ...$collections): void
    {
        DataSets::checkNotExists([
            $dataSet
        ]);
        $dsColls = $dataSet->getCollections();
        $invalidColls = \array_diff($collections, $dsColls);

        if (! empty($invalidColls)) {
            $invalidColls = implode(',', $invalidColls);
            $dsColls = implode(',', $dsColls);
            throw new \Exception("$dataSet does not have collections [$invalidColls]; has [$dsColls]");
        }
        echo "\nImporting $dataSet\n";

        $partitions = $dataSet->getPartitions();

        \wdPush($dataSet->path());

        $nbFails = 0;

        foreach ($partitions as $partition) {
            $collectionName = $partition->getCollectionName();
            $jsonFile = "{$partition->getID()}.json";

            if (self::collectionExists($collectionName)) {
                echo "$collectionName: already exists\n";
                continue;
            }
            echo "$jsonFile in collection: $collectionName\n";

            if (! \is_file($jsonFile))
                throw new \Exception("The file $jsonFile does not exists");

            $cname = \escapeshellarg($collectionName);
            $jsonFile = \escapeshellarg($jsonFile);
            \simpleExec("mongoimport -d treeforce -c $cname --file $jsonFile", $output, $err);

            \preg_match('/(\d+) document\(s\) failed/', $err, $matches);
            $nbFails += (int) $matches[1];

            if (null !== self::$collections_cache)
                self::$collections_cache[] = $collectionName;
        }

        if (0 === $nbFails)
            echo "Success";
        else
            echo "Failed ($nbFails documents)";

        \wdPop();
        echo "\n";
    }

    public static function getCollectionName(DataSet $dataSet)
    {
        if (! $dataSet->hasQueryingVocabulary())
            return $dataSet->group() . DataSets::getQualifiersString($dataSet->qualifiers());

        return \str_replace('/', '_', $dataSet->id());
    }
}