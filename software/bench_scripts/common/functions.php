<?php
if (! function_exists('array_is_list')) {

    function array_is_list(array $array)
    {
        return \array_keys($array) === \range(0, \count($array) - 1);
    }
}

function scandirNoPoints(string $path)
{
    $ret = \array_filter(\scandir($path), fn ($f) => $f[0] !== '.');
    \natcasesort($ret);
    return $ret;
}

function removePrefix(string $s, string $prefix): string
{
    if (0 === \strpos($s, $prefix))
        return \substr($s, \strlen($prefix));

    return $s;
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
            $sign = $arg[0];
            $arg = \substr($arg, 1);
            list ($name, $val) = parseArgKeyValue($argv, $arg);

            if (\is_int($name)) {
                $name = $val;
                $val = ($sign === '+');
            }
        } else {
            list ($name, $val) = parseArgKeyValue($argv, $arg);
        }

        if (\is_int($name))
            $ret[] = $val;
        else
            $ret[$name] = $val;
    }
    return $ret;
}

function parseArgKeyValue(array &$argv, string $currentArg): array
{
    if (false !== \strpos($currentArg, '=')) {
        list ($name, $val) = \explode('=', $currentArg, 2);
        return [
            $name,
            $val
        ];
    } elseif ($currentArg[\strlen($currentArg) - 1] === ':') {
        return [
            \substr($currentArg, 0, - 1),
            \array_shift($argv)
        ];
    } else
        return [
            0,
            $currentArg
        ];
}

function argPrefixed(array $args, string $prefix)
{
    $ret = [];

    foreach ($args as $arg => $v) {

        if (0 !== \strpos($arg, $prefix))
            continue;

        $ret[\substr($arg, \strlen($prefix))] = $v;
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

function array_filter_shift(array &$array, ?callable $filter = null, int $mode): array
{
    $drop = [];
    $ret = [];

    if ($mode === 0)
        $fmakeParams = fn ($k, $v) => (array) $v;
    elseif ($mode === ARRAY_FILTER_USE_KEY)
        $fmakeParams = fn ($k, $v) => (array) $k;
    elseif ($mode === ARRAY_FILTER_USE_BOTH)
        $fmakeParams = fn ($k, $v) => [
            $k,
            $v
        ];
    else
        throw new \Exception("Invalid mode $mode");

    foreach ($array as $k => $v) {
        $valid = $filter(...$fmakeParams($k, $v));

        if ($valid) {
            $drop[] = $k;
            $ret[$k] = $v;
        }
    }
    foreach ($drop as $d)
        unset($array[$d]);

    return $ret;
}

function updateArray(array $args, array &$array, ?callable $onUnexists = null, ?callable $mapKey = null)
{
    if (null === $mapKey)
        $mapKey = fn ($k) => \is_int($k) ? $k : \str_replace('.', '_', $k);

    foreach ($args as $k => $v) {
        $k = $mapKey($k);

        if (! \array_key_exists($k, $array)) {

            if ($onUnexists === null)
                throw new \Exception("The key '$key' does not exists in the array: " . implode(',', \array_kets($array)));
            else
                $onUnexists($array, $k, $v);
        } else
            $array[$k] = $v;
    }
}

function updateArray_getRemains(array $args, array &$array, ?callable $mapKey = null): array
{
    $remains = [];
    $fstore = function ($array, $k, $v) use (&$remains): void {
        $remains[$k] = $v;
    };

    updateArray($args, $array, $fstore, $mapKey);
    return $remains;
}

function updateObject(array $args, object &$obj)
{
    foreach ($args as $k => $v) {
        $k = \str_replace('.', '_', $k);
        $obj->$k = $v;
    }
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
    static $p = null;
    return $p ?? ($p = \realpath(getcwd()));
    // return \realpath(__DIR__ . "/../../..");
}
getBenchmarkBasePath();

function getQueriesBasePath(): string
{
    return getBenchmarkBasePath() . '/benchmark/queries';
}

function getQueries(bool $getPath = true): array
{
    $path = getQueriesBasePath();
    $queries = scandirNoPoints($path);
    $ret = \array_filter($queries, fn ($f) => \is_file("$path/$f"));
    \sort($ret);

    if ($getPath)
        return \array_map(fn ($f) => "$path/$f", $ret);
    return $ret;
}

function checkDataSetExists(DataSet $dataSet, bool $qualified = true, bool $onlyRules = false): void
{
    if (! $qualified) {
        $q = $dataSet->getQualifiers();
        $dataSet->setQualifiers([]);
    }
    if (! empty($error = $dataSet->allNotExists($onlyRules))) {
        $error = implode(",\n", $error);
        throw new \Exception("DataSet '$error' does not exists");
    }

    if (! $qualified)
        $dataSet->setQualifiers($q);
}