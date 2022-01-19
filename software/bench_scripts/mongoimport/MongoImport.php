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

    public static function importDataSet(DataSet $dataSet): void
    {
        checkDataSetExists($dataSet);
        echo "\nImporting {$dataSet->getId()}\n";

        foreach ($dataSet->getRules() as $rulesDir) {
            $dataSet->setTheRules($rulesDir);

            $path = $dataSet->dataSetPath();
            echo "CD $path\n";
            \chdir($path);
            $collectionName = self::getCollectionName($dataSet);

            $jsonFiles = \glob("*.json");

            echo "\nDeleting treeforce.$collectionName from MongoDB\n";

            $cmd = "echo '' | mongoimport -d treeforce -c '$collectionName' --drop\n";
            echo \shell_exec($cmd);

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