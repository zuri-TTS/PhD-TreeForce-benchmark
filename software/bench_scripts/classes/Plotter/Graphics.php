<?php
namespace Plotter;

final class Graphics implements \ArrayAccess
{

    private array $graphics;

    public function __construct(array $conf = [])
    {
        $g = include __DIR__ . '/graphics_conf.php';
        $conf = \array_intersect_key($conf, $g);
        $this->graphics = $conf + $g;
    }

    // ========================================================================
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->graphics[] = $value;
        } else {
            $this->graphics[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->graphics[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->graphics[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->graphics[$offset]) ? $this->graphics[$offset] : null;
    }

    // ========================================================================
    public function getGraphics()
    {
        return $this->graphics;
    }

    public function compute(int $nbBars, int $nbBarGroups, int $nbPlots)
    {
        $g = &$this->graphics;
        $g = [
            'bar.gap.nb' => ($nbBarGroups - 1) * $g['bar.gap.factor']
        ] + $g;

        if ($g['logscale']) {
            $base = is_int($g['logscale']) ? $g['logscale'] : ($g['plot.yrange.step'] ?? 10);
            $g['logscale.base'] = $base;
            $g['plot.y.step.nb'] = \ceil(\log($g['plot.yrange.max'], $base)) - \ceil(\log($g['plot.yrange.min'], $base));
        } else {
            $g['plot.y.step.nb'] = ($g['plot.yrange.max'] - $g['plot.yrange.min']) / $g['plot.yrange.step'];
            $g['logscale.base'] = 10;
        }
        $onlyPlotH = $g['plot.y.step.nb'] * $g['plot.y.step'];
        $g['plot.lmargin.pixels'] = $g['plot.lmargin'] * $g['font.size'];
        $g['plot.rmargin.pixels'] = $g['plot.rmargin'] * $g['font.size'];
        $g['plot.bmargin.pixels'] = $g['plot.bmargin'] * $g['font.size'];
        $gaps = $g['bar.gap.nb'];

        $g['plot.w.full.bar.nb'] = $nbBars + $gaps + $g['bar.offset.factor'] + $g['bar.end.factor'];

        $g['plot.w'] = $g['plot.w.full.bar.nb'] * $g['bar.w'];
        $g['plot.w'] = max($g['plot.w'], $g['plot.w.min']);
        $g['plot.h'] = $onlyPlotH + $g['plot.h.space'];

        $g['plot.x'] = $g['plot.lmargin.pixels'];
        $g['plot.y'] = $g['plot.bmargin.pixels'];

        $g['plot.w.full'] = $g['plot.w'] + $g['plot.lmargin.pixels'] + $g['plot.rmargin.pixels'];
        $g['plot.h.full'] = $g['plot.h'] + $g['plot.bmargin.pixels'];

        $g['blocs.w'] = 0;
        $g['blocs.h'] = 0;

        $g['plots.max.x'] = $g['plots.max.x'] ?? $nbPlots;
        $g['plots.x'] = $nbXPlots = $g['plots.max.x'] + 1;
        $g['plots.y'] = $nbYPlots = \ceil((float) $nbPlots / $nbXPlots);

        $g['plots.w'] = $g['plot.w.full'] * $nbXPlots;
        $g['plots.h'] = $g['plot.h.full'] * $nbYPlots;

        $g['w'] = $g['plot.w.full'];
        $g['h'] = $g['plot.h.full'];
        $g['w'] = $g['plots.w'];
        $g['h'] = $g['plots.h'];
    }

    private function graphics_addBSpace(int $space)
    {
        $g = &$this->graphics;
        $g['blocs.h'] += $space;
        $g['h'] += $space;
        $g['plot.y'] += $space;
    }

    public function addFooter(array $footerBlocs): string
    {
        $blocs = \array_map([
            $this,
            'computeFooterBlocGraphics'
        ], $footerBlocs);

        list ($charOffset, $h) = \array_reduce($blocs, fn ($c, $i) => [
            \max($c[0], $i['lines.nb']),
            \max($c[1], $i['h'])
        ], [
            0,
            0
        ]);
        $this->graphics_addBSpace($h);
        $ret = '';

        $x = 0;
        foreach ($blocs as $b) {
            $s = \str_replace('_', '\\\\_', \implode('\\n', $b['bloc']));
            $ret .= "set label \"$s\" at screen 0.01,0.01 offset character $x, character $charOffset\n";
            $x += $b['lines.size.max'];
        }
        $g = &$this->graphics;
        $g['blocs.w'] += $x * $g['font.size'] * 0.9;
        $this->updateWidth();
        return $ret;
    }

    private function updateWidth()
    {
        $g = &$this->graphics;
        $g['w'] = \max($g['w'], $g['blocs.w']);
    }

    private function computeFooterBlocGraphics(array $bloc): array
    {
        $bloc = \array_map(fn ($v) => empty($v) ? '' : (null === ($v[1] ?? null) ? $v[0] : "$v[0]: $v[1]"), $bloc);
        $maxLineSize = \array_reduce($bloc, fn ($c, $i) => \max($c, strlen($i)), 0);
        $nbLines = \count($bloc);
        $g = $this->graphics;

        return [
            'bloc' => $bloc,
            'lines.nb' => $nbLines,
            'lines.size.max' => $maxLineSize * 0.85,
            'w' => $g['font.size'] * $maxLineSize,
            'h' => ($g['font.size'] + 8) * $nbLines
        ];
    }

    public function getYMinMax(array $arrOfCsvData): array
    {
        $min = PHP_INT_MAX;
        $max = 0;
        $times = [
            'r',
            'c'
        ];

        foreach ($arrOfCsvData as $csvData) {

            foreach ($csvData as $meas) {

                if (! \Plot::isTimeMeasure($meas))
                    continue;

                foreach ($times as $t) {
                    $max = \max($max, $meas[$t]);
                    $min = \min($min, $meas[$t]);
                }
            }
        }
        return [
            $min,
            $max
        ];
    }

    public function plotYLines(int $yMax): string
    {
        $yNbLine = log10($yMax);

        for ($i = 0, $m = 1; $i < $yNbLine; $i ++) {
            $lines[] = "$m ls 0";
            $m *= 10;
        }
        return implode(",\\\n", $lines);
    }

    public function prepareBlocs(array $groups, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $blocs = [];

        foreach ($groups as $group) {
            $blocs[] = $this->prepareOneBloc((array) $group, $exclude, $val);
        }
        return $blocs;
    }

    public function prepareOneBloc(array $group, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $line = [];

        foreach ((array) $group as $what) {
            $line[] = [
                "[$what]",
                null
            ];

            foreach ($val[$what] as $k => $v) {

                if (in_array($k, $exclude))
                    continue;

                $line[] = [
                    $k,
                    (string) $v
                ];
            }
            $line[] = null;
        }
        return $line;
    }
}