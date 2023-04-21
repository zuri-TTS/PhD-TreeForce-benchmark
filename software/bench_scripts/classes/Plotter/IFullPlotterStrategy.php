<?php
namespace Plotter;

interface IFullPlotterStrategy
{

    function getID(): string;

    function groupTests(array $tests): array;

    function getDataLine(string $test, array $data): array;

    function sortDataLines(array &$data): void;

    function getDataHeader(): array;

    function plot_getStackedMeasures(): array;

    function setPlotter(FullPlotter $plotter): void;

    function getPlotter(): FullPlotter;
}