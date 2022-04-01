<?php
namespace Data;

interface IPartitioning
{

    function getID(): string;

    function getBaseDir(): string;

    function getPartitionsOf(\DataSet $ds): array;
}