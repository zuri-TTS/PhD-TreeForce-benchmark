<?php
namespace Help;

final class Arrays
{

    private function __construct()
    {
        throw new \Error();
    }

    // ========================================================================
    public static function first(array $a, $default = null)
    {
        if (empty($a))
            return $default;

        return $a[\array_key_first($a)];
    }

    public static function getDepthKeys(array $a, int $depth)
    {
        $ret = [];
        $toProcess = [
            $a
        ];
        $nextToProcess = [];

        while ($depth && ! empty($toProcess)) {
            $currentDepth = [];

            while (null !== ($a = \array_pop($toProcess))) {
                $currentDepth = \array_merge($currentDepth, \array_keys($a));
                $nextToProcess = \array_filter($a, '\is_array');
            }
            $currentDepth = \array_unique($currentDepth);
            \sort($currentDepth);
            $ret[] = $currentDepth;
            $toProcess = $nextToProcess;
            $nextToProcess = [];
            $depth --;
        }
        return $ret;
    }

    public static function prefixSubItemsWithKey(array $a, string $delimiter = '.')
    {
        $ret = [];

        foreach ($a as $k => $item) {

            if (\is_array($item))
                foreach ($item as $kk => $subItem) {
                    $ret["$k$delimiter$kk"] = $subItem;
                }
            else
                $ret[$k] = $v;
        }

        return $ret;
    }

    public static function jsonRecursiveCount(array $a)
    {
        $nb = 0;

        $p = [
            $a
        ];

        while (! empty($p)) {
            $a = \array_pop($p);

            if (empty($a));
            elseif (\array_is_list($a))
                $nb = $nb - 1 + \count($a);
            else
                $nb += \count($a);

            foreach ($a as $v)
                if (\is_array($v))
                    $p[] = $v;
        }
        return $nb;
    }

    public static function recursiveCount(array $a)
    {
        $nb = 0;

        $p = [
            $a
        ];

        while (! empty($p)) {
            $a = \array_pop($p);
            $nb += \count($a);

            foreach ($a as $v)
                if (\is_array($v))
                    $p[] = $v;
        }
        return $nb;
    }

    public static function renameColumn(array $a, ...$pairs): array
    {
        while (! empty($pairs))
            $replacements[\array_shift($pairs)] = \array_shift($pairs);

        $ret = [];

        foreach ($a as $line => $items)

            foreach ($items as $k => $v) {
                $repl = $replacements[$k] ?? $k;
                $ret[$line][$repl] = $v;
            }

        return $ret;
    }

    public static function dropColumn(array $a, ...$column): array
    {
        $ret = $a;

        foreach ($column as $column)
            foreach ($ret as &$v)
                unset($v[$column]);

        return $ret;
    }

    public static function usearchValues(array $a, callable $pred)
    {
        $ret = [];

        foreach ($a as &$v)
            if ($pred($v, $k))
                return $ret[$k] = $v;

        return $ret;
    }

    public static function last(array $a, $default = null)
    {
        if (empty($a))
            return $default;

        return $a[\array_key_last($a)];
    }

    public static function flipKeys(array $a, $default = null)
    {
        $ret = [];

        foreach ($a as $k => $v)
            $ret[$v][] = $k;

        return $ret;
    }

    public static function subSelect(array $a, array $keys, $default = null)
    {
        $ret = [];

        foreach ($keys as $k)
            $ret[$k] = $a[$k] ?? $default;

        return $ret;
    }

    public static function keysExists(array $a, string ...$keys)
    {
        foreach ($keys as $k)
            if (! \array_key_exists($k, $a))
                return false;
        return true;
    }

    public static function columns(array $a, string ...$columns)
    {
        $ret = $a;

        foreach ($columns as $c)
            $ret = \array_column($ret, $c);
        return $ret;
    }

    public static function &follow(array &$array, array $path, $default = null)
    {
        $p = &$array;

        for (;;) {
            $k = \array_shift($path);

            if (! \array_key_exists($k, $p))
                return $default;

            $p = &$p[$k];

            if (empty($path))
                return $p;
            if (! is_array($p) && ! empty($path))
                return $default;
        }
    }

    public static function encode(array $a, string $delimiter = ","): string
    {
        $s = \implode($delimiter, $a);
        return "[$s]";
    }

    public static function decode(string $s, string $delimiter = ","): array
    {
        if (! \str_starts_with($s, '[') || ! \str_ends_with($s, ']'))
            $s = \substr($s, 1, - 1);

        return \explode($delimiter, $s);
    }
}
