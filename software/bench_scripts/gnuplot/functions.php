<?php
include_once __DIR__ . '/../benchmark/functions.php';

function nextExecution(array &$argv, string $config): array
{
    while (null !== ($arg = \array_shift($argv))) {

        if (false !== \strpos($arg, '.php'))
            $config = $arg;
        else {
            return [
                $config,
                $arg
            ];
        }
    }
    return [];
}

function extractResumeData(string $filePath): array
{
    $ret = [];
    $file = new SPLFileObject($filePath, "r");
    $file->setFlags(SPLFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

    $header = $file->current();
    $file->next();

    $i = 0;
    foreach ($header as &$item) {
        if ($item === "") {
            $item = "_$i";
            $i ++;
        }
    }
    unset($item);

    while ($file->valid()) {
        $lineData = $file->current();

        if (empty($lineData))
            continue;

        $lineData = array_map(function ($item) {
            if (is_numeric($item))
                return (int) $item;
            return $item;
        }, $lineData);
        $ret[] = array_combine($header, $lineData);
        $file->next();
    }
    return $ret;
}

// function plot_selectDataThreads(array $data, array $PLOT): array
// {
// $ret = [];
// $threads = $PLOT['config']['threads'];

// foreach ($data as $data_k => $data_v) {
// $ret[$data_k] = [];

// foreach ($data_v as $k => $item) {

// if (! is_int($k))
// $ret[$data_k][$k] = $item;
// elseif (in_array($k, $threads))
// $ret[$data_k][$k] = $item;
// }
// // Forget '', #codes, #lines
// $ret[$data_k]["_0"] = '"' . $ret[$data_k]["_0"] . ' (' . $ret[$data_k]['#codes'] . ')"';
// $ret[$data_k] = array_slice($ret[$data_k], 0, - 3, true);
// }
// return $ret;
// }

// function plot_makeTitle(array $PLOT): string
// {
// return basename(realpath($PLOT['baseDir']));
// }
function gnuplot_plot(array $PLOT): void
{
    $nbData = count($PLOT['data'][0]);
    $doonce = false;
    echo "plot ";

    foreach ((array) $PLOT['config']['plot.plot'] as $plotItem) {
        if ($doonce)
            echo ",\\\n\t";
        else
            $doonce = true;

        if (is_callable($plotItem))
            echo $plotItem($PLOT);
        else
            echo (string) $plotItem;
    }
    echo "\n";
}
