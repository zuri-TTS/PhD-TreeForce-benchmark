<?php
namespace Data;

interface IPartition
{

    function getID(): string;

    function getCollectionName(): string;

    function contains(array $data): bool;

    function isLogical(): bool;

    function getLogicalRange(): ?array;
}