<?php
namespace Data;

interface ILoader
{

    function __construct(string $group, array $config);

    function getUnwindConfig(): array;

    function getDoNotSimplifyConfig(): array;

    function getXMLFilePath(): string;

    function deleteXMLFile(): bool;

    function getLabelReplacerForDataSet(\DataSet $dataSet): LabelReplacer;
}