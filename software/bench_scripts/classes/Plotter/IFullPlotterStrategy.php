<?php
namespace Plotter;

interface IFullPlotterStrategy
{

    function getID(): string;

    function groupTests(array $tests): array;

    function getDataLine(\Measures $measures): array;

    function sortDataLines(array &$data): void;

    function getDataHeader(): array;

    function plot_getStackedMeasures(): array;

    function setPlotter(FullPlotter $plotter): void;

    function getPlotter(): FullPlotter;
}