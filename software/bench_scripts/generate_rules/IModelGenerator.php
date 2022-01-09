<?php

interface IModelGenerator
{

    function validArgs(): bool;

    function getOutputFileName(): string;

    function usage(): string;

    function generate(\SplFileObject $writeTo);
}