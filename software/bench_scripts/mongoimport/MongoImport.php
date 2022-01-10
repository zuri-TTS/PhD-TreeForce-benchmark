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
            $path = $dataSet->dataSetPath($rulesDir);
            \chdir($path);
            $collectionName = "{$dataSet->getGroup()}_$rulesDir";
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
}