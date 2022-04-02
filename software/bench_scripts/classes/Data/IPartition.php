<?php
namespace Data;

interface IPartition
{

    function getID(): string;

    function contains(array $data): bool;

    function isLogical(): bool;
}