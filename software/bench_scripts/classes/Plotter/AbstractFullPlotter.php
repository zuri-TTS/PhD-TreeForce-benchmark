<?php
namespace Plotter;

abstract class AbstractFullPlotter implements IFullPlotter
{

    protected \Plot $plot;

    protected function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getPlot(): \Plot
    {
        return $this->plot;
    }

    protected function cleanCurrentDir(string ...$globs)
    {
        if (empty($globs))
            $globs = [
                '*.dat'
            ];
        foreach ($globs as $glob)
            foreach ($g = \glob($glob) as $file)
                \unlink($file);
    }
}
