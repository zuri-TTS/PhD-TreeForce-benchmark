<?php
namespace Plotter;

abstract class AbstractFullPlotter implements IPlotter
{

    protected bool $xtics_pretty = true;

    protected bool $xtics_infos = true;

    protected bool $xtics_infos_answers_nb = true;

    private const factors = [
        'K' => 10 ** 3,
        'M' => 10 ** 6,
        'G' => 10 ** 9
    ];

    protected static function nbFromGroupName(string $groupName)
    {
        if (\preg_match('#^(\d+)([KMG])#', $groupName, $matches))
            return (int) $matches[1] * (self::factors[$matches[2]] ?? 0);
        return 0;
    }

    protected function makeXTic(string $dirName, int $nbReformulations, int $nbAnswers)
    {
        if (\preg_match("#^\[.+\]\[(.+)\]#U", $dirName, $matches)) {

            if ($this->xtics_pretty) {
                if (\preg_match("#^\((\d+)\)#U", $matches[1], $smatches))
                    $ret = $smatches[1];
                else
                    $ret = $matches[1];
            } else
                $ret = $matches[0];
        } else
            $ret = $dirName;

        $infos = [];

        if ($this->xtics_infos) {

            if ($nbReformulations != $ret)
                $infos[] = "$nbReformulations";

            if ($this->xtics_infos_answers_nb)
                $infos[] = "$nbAnswers";

            $infos = \implode(',', $infos);

            \preg_match("#\[summary-(.+)\]#U", $dirName, $summary);
            \preg_match("#\[toNative-(.+)\]#U", $dirName, $stoNative);
            $filterLeaf = false !== \strpos($dirName, '[filter-leaf]');

            $summary = $summary[1] ?? null;
            $stoNative = $stoNative[1] ?? null;

            $sinfo = [];
            if ($summary) {
                $filter = $filterLeaf ? '-filtleaf' : '';
                $sinfo[] = "S-$summary$filter";
            }
            if ($stoNative)
                $sinfo[] = "2-$stoNative";

            $sinfo = \implode(',', $sinfo);
            $ret = "$ret\[$sinfo\]($infos)";
        }
        return \Plot::gnuplotSpecialChars($ret);
    }

    protected function cleanCurrentDir()
    {
        foreach ($g = \glob('*.dat') as $file)
            \unlink($file);
    }
}
