<?php

function scandirNoPoints(string $path)
{
    return \array_diff(\scandir($path), [
        '.',
        '..'
    ]);
}

function parseArgv(array $argv): array
{
    return parseArgvShift($argv);
}

function parseArgvShift(array &$argv, string $endArg = ''): array
{
    $ret = [];
    while (null !== ($arg = \array_shift($argv))) {

        if ($arg[0] === '+' || $arg[0] === '-') {
            $name = \substr($arg, 1);
            $ret[$name] = $arg[0] === '+';
        } else if (false === strpos($arg, '=')) {

            if ($arg === $endArg)
                break;

            $ret[] = $arg;
        } else {
            [
                $name,
                $val
            ] = explode('=', $arg, 2);
            $ret[$name] = $val;
        }
    }
    return $ret;
}

function argShift(array &$args, string $key, $default = null)
{
    if (count($args) == 0)
        return $default;

    if (\array_key_exists($key, $args)) {
        $v = $args[$key];
        unset($args[$key]);
    } else {
        $keys = \array_values(\array_filter(\array_keys($args), 'is_int'));

        if (empty($keys))
            return $default;

        $v = $args[$keys[0]];
        unset($args[$keys[0]]);
    }
    return $v;
}

function getVal(array $a, $default, string ...$key)
{
    $p = $a;

    foreach ($key as $k) {
        if (! isset($a[$k])) {
            $path = implode(' ', $key);
            fputs(STDERR, "'$path' is not a valid array path\n");
            return $default;
        }
        $p = $a[$k];
    }
    return $p;
}

function get_ob(callable $f)
{
    ob_start();
    $f();
    return ob_get_clean();
}

function get_include_contents(string $filename, array $variables, string $uniqueVar = '')
{
    if (is_file($filename)) {

        if (empty($uniqueVar))
            \extract($variables);
        else
            $$uniqueVar = $variables;

        ob_start();
        include $filename;
        return ob_get_clean();
    }
    return false;
}

function getBenchmarkBasePath(): string
{
    return \realpath(__DIR__ . "/../../..");
}

function checkDataSetExists(DataSet $dataSet):void
{
    if (! empty($error = $dataSet->allNotExists())) {
        $error = implode(',', $error);
        throw new \Exception("DataSet '$error' does not exists");
    }
}