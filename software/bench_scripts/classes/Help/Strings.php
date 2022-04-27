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
}
