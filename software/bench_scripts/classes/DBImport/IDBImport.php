<?php
namespace DBImport;

interface IDBImport
{

    function makeJavaProperties(array $serverConfig): array;

    function collectionExists(string $collection): bool;

    function collectionsExists(array $collection): bool;

    function createIndex($collection, string $indexName, int $order = 1): void;

    function dropDataset(\DataSet $ds): void;

    function dropDataSets(array $dataSets): void;

    function dropCollection(string $collection): void;

    function dropCollections(array $collections): void;

    function importCollection(\DataSet $dataSet, string $collection): void;

    function importCollections(\DataSet $dataSet, array $collections): void;

    function importDataSet(\DataSet $dataSet): void;
}