<?php

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
