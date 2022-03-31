<?php
namespace Data;

interface IPartition
{

    function getID(): string;

    function getCollectionName(): string;

    function contains(array $data): bool;
}