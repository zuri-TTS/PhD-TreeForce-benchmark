<?php
namespace Data;

interface IPartitioning
{

    function getID(): string;

    function getPartitionsOf(\DataSet $ds): array;
}