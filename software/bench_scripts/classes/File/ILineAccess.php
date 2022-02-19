<?php
namespace File;

interface ILineAccess
{
    public function setSkipEmpty(bool $skip = true):ILineAccess;

    public function getLine(int $pos): string;

    public function nbLines(): int;
}