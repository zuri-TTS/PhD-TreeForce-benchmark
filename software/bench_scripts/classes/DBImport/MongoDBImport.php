<?php
namespace DBImport;

final class MongoDBImport extends AbstractDBImport
{

    private ?array $collections_cache = null;

    private ?array $collections_stats_cache = null;

    public function import(): void
    {
        $this->importDataSet($this->dataSet);
    }

    // ========================================================================
    public function countDocuments(\DataSet $dataSet, bool $forceEval = false): int
    {
        if (! $forceEval) {
            $v = $this->collections_stats_cache[$dataSet->group()] ?? null;

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
            $this->collections_stats_cache[$dataSet->group()] = $ret;
            return $ret;
        } else {
            throw new \Exception("Error on command: $cmd\nexit code: $exitCode\n");
        }
    }

    // ========================================================================
    public function dropDataSets(array $dataSets): void
    {
        foreach ($dataSets as $ds)
            self::dropDataSet($ds);
    }

    public function dropDataSet(\DataSet $dataSet): void
    {
        self::_dropCollections(...$dataSet->getCollections());
    }

    public function dropCollections(array $collections): void
    {
        self::_dropCollections(...$collections);
    }

    private function _dropCollections(string ...$collections): void
    {
        if (null !== $this->collections_cache)
            $delCache = function (array $collections) {
                \array_delete($this->collections_cache, ...$collections);
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

        var_dump($script);
        $script = \escapeshellarg($script);
        $cmd = "mongosh treeforce --quiet --eval $script";

        if (0 === ($exitCode = \simpleExec($cmd, $output, $err))) {
            $res = \json_decode($output);

            if (null === $res) {
                \fwrite(STDERR, "Cannot decode as json data:<<<\n'$output'\n>>>\nErrors: $err\n\n");
                $res = [];
            }
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
    public function collectionExists(string $collection): bool
    {
        return \in_array($collection, self::getCollections());
    }

    public function collectionsExists(array $collection): bool
    {
        return empty(\array_diff($collection, self::getCollections()));
    }

    public function getCollections(bool $forceCheck = false): array
    {
        if (! $forceCheck && $this->collections_cache !== null)
            return $this->collections_cache;

        $script = <<<EOD
        collections = db.getCollectionNames();

        for(var coll of collections){
            print(coll);
        }
        EOD;
        $script = \escapeshellarg($script);

        $cmd = "mongosh treeforce --quiet --eval $script\n";
        \simpleExec($cmd, $output, $err);

        if (empty($output))
            return [];

        return $this->collections_cache = explode("\n", $output);
    }

    public function createIndex($collection, string $indexName, int $order = 1): void
    {
        if (is_string($collection))
            $collections = (array) $collection;
        elseif ($collection instanceof \DataSet)
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
    protected function _importJsonFile(string $jsonFile, string $collectionName): int
    {
        // Get only the last line
        $err = function ($s) use (&$lineBuff) {
            echo $s;
            $s = \rtrim($s, "\n");
            $pos = \strrpos($s, "\n");

            if ($pos === false) {
                $lineBuff .= $s;
            } else {
                $ss = \substr($s, $pos);

                if (\strlen($ss) > 0)
                    $lineBuff = $ss;
            }
        };

        $cname = \escapeshellarg($collectionName);
        $jsonFile = \escapeshellarg($jsonFile);
        // $moreParams = "--numInsertionWorkers=4";
        $moreParams = "";
        \simpleExec("mongoimport -d treeforce -c $cname --file $jsonFile $moreParams", $output, $err);

        \preg_match('/(\d+) document\(s\) failed/', $lineBuff, $matches);
        $nbFails = (int) ($matches[1] ?? - 1);

        if (null !== $this->collections_cache)
            $this->collections_cache[] = $collectionName;

        return $nbFails;
    }

    // ========================================================================
}