<?php
include_once __DIR__ . '/common/functions.php';
include_once __DIR__ . '/classes/DataSet.php';

\array_shift($argv);


if (\count($argv) == 0) {
    echo "Import ALL dataSets to MongoDB\n\n";
    $argv = DataSet::getAllGroups();
}

function import(DataSet $dataSet): void
{
    if (! empty($error = $dataSet->allNotExists())) {
        $error = implode(',', $error);
        throw new \Exception("DataSet '$error' does not exists");
    }

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

$wdir = \getcwd();

while (null !== ($dataSetId = \array_shift($argv))) {
    import(new DataSet($dataSetId));

    // Just reset at the end
    \chdir($wdir);
}

