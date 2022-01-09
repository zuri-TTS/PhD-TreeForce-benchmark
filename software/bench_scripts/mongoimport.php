<?php
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

function importGroup(string $dataSetGroup)
{
    $basePath = \getBenchmarkBasePath();
    $dataSetGroupPath = "$basePath/benchmark/data/$dataSetGroup";

    if (! \is_dir($dataSetGroupPath))
        throw new \Exception("The dataSet $dataSetGroupPath does not exists");

    $dataSets = \scandirNoPoints("$dataSetGroupPath/data");

    foreach ($dataSets as $dataSet)
        import($dataSetGroup, $dataSet);
}

function import(string $dataSetGroup, string $dataSet)
{
    echo "\nImporting $dataSetGroup/data/$dataSet\n";
    $basePath = \getBenchmarkBasePath();
    $path = "$basePath/benchmark/data/$dataSetGroup/data/$dataSet";

    if (! \is_dir($path))
        throw new \Exception("The dataSet $path does not exists");

    \chdir($path);
    $collectionName = "{$dataSetGroup}_$dataSet";
    $jsonFiles = \glob("*.json");

    echo "\nDeleting treeforce.$collectionName from MongoDB\n";

    $cmd = "echo '' | mongoimport -d treeforce -c '$collectionName' --drop\n";
    echo \shell_exec($cmd);

    foreach ($jsonFiles as $json) {
        echo "Importing $json\n";
        echo \shell_exec("cat '$json' | mongoimport -d treeforce -c '$collectionName'");
    }
}

$wdir = \getcwd();

if (\count($argv) == 0) {
    echo "Importing ALL dataSets\n\n";
    $argv = getDataSetGroups();
}

while (null !== ($dataSetGroup = \array_shift($argv))) {

    $tmp = \explode('/', $dataSetGroup);

    if (\count($tmp) === 2)
        import(...$tmp);
    else
        importGroup($dataSetGroup);

    // Just reset at the end
    \chdir($wdir);
}

