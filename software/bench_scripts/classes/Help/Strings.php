<?php
namespace Help;

final class Strings
{

    private function __construct()
    {
        throw new \Error();
    }

    // ========================================================================
    public static function append(string $delimiter, ...$s)
    {
        $ret = \array_shift($s);

        while (null !== ($v = \array_shift($s)))
            if (! empty($v))
                $ret .= "$delimiter$v";

        return $ret;
    }

    public static function removePrefix(string $s, string $prefix): string
    {
        if (0 === \strpos($s, $prefix))
            return \substr($s, \strlen($prefix));

        return $s;
    }

    public static function removeSuffix(string $s, string $suffix): string
    {
        $len = \strlen($suffix);

        if (\strlen($s) - $len === \strrpos($s, $suffix))
            return \substr($s, 0, - $len);

        return $s;
    }
}
