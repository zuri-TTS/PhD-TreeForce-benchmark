<?php

final class MongoImport
{

    private DataSet $dataSet;

    public function __construct(DataSet $dataSet)
    {
        $this->dataSet = $dataSet;
    }

    public static function import(): void
    {
        $this->importDataSet($this->dataSet);
    }

    public static function dropDatabase(DataSet $dataSet): void
    {
        foreach ($dataSet->getRules() as $rulesDir) {
            $dataSet->setTheRules($rulesDir);
            $collectionName = self::getCollectionName($dataSet);
            self::_dropDatabase($collectionName);
        }
    }

    private static function _dropDatabase(string $collectionName): void
    {
        echo "\nDeleting treeforce.$collectionName from MongoDB\n";

        $cmd = "echo '' | mongoimport -d treeforce -c '$collectionName' --drop\n";
        echo \shell_exec($cmd);
    }

    public static function collectionExists(string $collection): bool
    {
        return \in_array($collection, self::getCollections());
    }

    public static function getCollections(bool $forceCheck = false): array
    {
        static $ret;

        if (! $forceCheck && $ret !== null)
            return $ret;

        $cmd = "echo 'show tables' | mongosh treeforce --quiet\n";
        \preg_match_all("#([^\s]+)#", \shell_exec($cmd), $matches);
        return $ret = $matches[0];
    }

    public static function importDataSet(DataSet $dataSet, bool $forceImport = false): void
    {
        checkDataSetExists($dataSet);
        echo "\nImporting {$dataSet->getId()}\n";

        foreach ($dataSet->getRules() as $rulesDir) {
            $dataSet->setTheRules($rulesDir);
            $collectionName = self::getCollectionName($dataSet);

            if (self::collectionExists($collectionName)) {
                echo "$collectionName exists\n";
                continue;
            }
            $path = $dataSet->dataSetPath();
            echo "CD $path\n";
            \chdir($path);

            self::_dropDatabase($collectionName);

            $jsonFiles = \glob("*.json");

            if (empty($jsonFiles)) {
                echo "!!$collectionName: no json files to load!!\n";
                continue;
            }
            foreach ($jsonFiles as $json) {
                echo "Importing $json\n";
                echo \shell_exec("cat '$json' | mongoimport -d treeforce -c '$collectionName'");
            }
        }
    }

    public static function getCollectionName(DataSet $dataSet)
    {
        return \str_replace('/', '_', $dataSet->getTheId());
    }
}