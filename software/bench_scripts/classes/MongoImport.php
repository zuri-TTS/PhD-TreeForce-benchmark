<?php

final class MongoImport
{

    private DataSet $dataSet;

    private static ?array $collections_cache = null;

    public function __construct(DataSet $dataSet)
    {
        $this->dataSet = $dataSet;
    }

    public static function import(): void
    {
        $this->importDataSet($this->dataSet);
    }

    public static function dropCollection(DataSet $dataSet): void
    {
        $collectionName = self::getCollectionName($dataSet);
        self::_dropCollection($collectionName);
    }

    public static function dropCollections(array $dataSets): void
    {
        self::_dropCollection(...\array_map('MongoImport::getCollectionName', $dataSets));
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
        $output = \str_replace("'", '"', $output);
        return self::$collections_cache = \json_decode($output);
    }

    public static function importDataSet(DataSet $dataSet): void
    {
        DataSets::checkNotExists([
            $dataSet
        ]);
        echo "\nImporting $dataSet: ";

        $collectionName = self::getCollectionName($dataSet);

        if (self::collectionExists($collectionName)) {
            echo "Exists";
            return;
        }
        echo "\n";

        \wdOp($dataSet->path(), function () use ($collectionName) {
            $jsonFiles = \glob("*.json");

            if (empty($jsonFiles)) {
                throw new \Exception("$collectionName: no json files to load\n");
            }

            $nbFails = 0;

            foreach ($jsonFiles as $json) {

                if ($json === 'end.json')
                    continue;

                echo "$json\n";
                $cname = \escapeshellarg($collectionName);
                $json = \escapeshellarg($json);
                \simpleExec("mongoimport -d treeforce -c $cname --file $json", $output, $err);

                \preg_match('/(\d+) document\(s\) failed/', $err, $matches);
                $nbFails += (int) $matches[1];
            }

            if (0 === $nbFails)
                echo "Success";
            else
                echo "Failed ($nbFails documents)";

            if (null === self::$collections_cache)
                self::$collections_cache[] = $collectionName;
        });
        echo "\n";
    }

    public static function getCollectionName(DataSet $dataSet)
    {
        return \str_replace('/', '_', $dataSet->id());
    }
}