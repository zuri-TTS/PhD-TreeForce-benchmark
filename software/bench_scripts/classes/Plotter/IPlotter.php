<?php
namespace Plotter;

interface IPlotter
{

    function getId(): string;

    function getProcessType(): string;

    function plot(array $csvPaths): void;
}