<?php
namespace Data;

interface IPartition
{

    public const NO_PID = - 1;

    function getID(): string;

    function getPID(): int;
}