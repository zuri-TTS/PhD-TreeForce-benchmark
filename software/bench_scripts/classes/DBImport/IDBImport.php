<?php
namespace DBImport;

interface IDBImport
{

    function collectionExists(string $collection): bool;

    function collectionsExists(array $collection): bool;

    function createIndex($collection, string $indexName, int $order = 1): void;

    function dropCollection(string $collection): void;

    function dropCollections(array $collections): void;

    function importCollections(\DataSet $dataSet, array $collections): void;

    function importDataSet(\DataSet $dataSet): void;
}