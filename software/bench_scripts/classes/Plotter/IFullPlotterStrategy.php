<?php
namespace Plotter;

interface IFullPlotterStrategy
{

    function getID(): string;

    function groupCSVFiles(array $csvFiles): array;

    function getDataLine(string $csvPath = ''): array;

    function getDataHeader(): array;

    function plot_getStackedMeasures(): array;

    function setPlotter(FullPlotter $plotter): void;

    function getPlotter(): FullPlotter;
}