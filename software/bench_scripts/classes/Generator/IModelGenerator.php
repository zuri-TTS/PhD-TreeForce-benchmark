<?php
namespace Generator;

interface IModelGenerator
{

    function validArgs(): bool;

    function getOutputFileName(): string;

    function usage(): string;

    function generate(string $filePath);
}