<?php
namespace Data;

interface ILogicalPartitioningFactory
{
    function create(PhysicalPartition $parent): IPartitioning;
}