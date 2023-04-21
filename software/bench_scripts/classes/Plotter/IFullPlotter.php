<?php
namespace Plotter;

interface IFullPlotter extends IPlotter
{

    function plot(array $tests): void;
}