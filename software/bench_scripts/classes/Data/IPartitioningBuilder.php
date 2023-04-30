<?php
namespace Data;

interface IPartitioningBuilder
{

    function getDataSet(): \DataSet;

    function load(): IPartitioning;

    function save(): IPartitioning;

    function getPartitionFor(array $document): IPartition;
}