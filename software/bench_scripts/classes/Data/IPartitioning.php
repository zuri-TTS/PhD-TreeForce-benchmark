<?php
namespace Data;

interface IPartitioning
{

    function getDataSet(): \DataSet;

    function getPartitions(): array;

    function getID(): string;
}