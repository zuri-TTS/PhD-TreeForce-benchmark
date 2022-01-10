<?php
include_once __DIR__ . '/common/functions.php';
include_once __DIR__ . '/classes/DataSet.php';
include_once __DIR__ . '/mongoimport/MongoImport.php';

\array_shift($argv);


if (\count($argv) == 0) {
    echo "Import ALL dataSets to MongoDB\n\n";
    $argv = DataSet::getAllGroups();
}

$wdir = \getcwd();

while (null !== ($dataSetId = \array_shift($argv))) {
    MongoImport::importDataSet(new DataSet($dataSetId));

    // Just reset at the end
    \chdir($wdir);
}

