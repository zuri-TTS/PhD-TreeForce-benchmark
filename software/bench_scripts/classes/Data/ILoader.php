<?php
namespace Data;

interface ILoader
{

    function __construct(string $group, array $config);

    function getUnwindConfig(): array;

    function getDoNotSimplifyConfig(): array;

    function getXMLReader(): \XMLReader;

    function deleteXMLFile(): bool;

    function getLabelReplacerForDataSet(\DataSet $dataSet): callable;
}