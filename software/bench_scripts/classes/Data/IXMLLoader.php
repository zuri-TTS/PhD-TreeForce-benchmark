<?php
namespace Data;

interface IXMLLoader
{

    function __construct(array $dataSets, array $config);

    function getUnwindConfig(): array;

    function isList(string $name): bool;

    function getOut(string $name, string $subVal): bool;

    function isObject(string $name): bool;

    function isText(string $name): bool;

    function isMultipliable(string $name): bool;

    function getXMLReader(): \XMLReader;

    function deleteXMLFile(): bool;

    function getLabelReplacerForDataSet(\DataSet $dataSet): ?callable;

    function getPartitioning(string $name): IPartitioning;
}