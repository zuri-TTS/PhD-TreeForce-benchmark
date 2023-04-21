<?php
namespace Plotter;

interface IGroupPlotter extends IPlotter
{

    function plot(string $dirName, array $queriesName): void;
}