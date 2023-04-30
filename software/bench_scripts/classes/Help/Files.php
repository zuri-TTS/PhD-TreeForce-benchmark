<?php
namespace Help;

final class Files
{

    private function __construct()
    {
        throw new \Error();
    }

    // ========================================================================
    public static function maxMTime(array $files): \SplFileInfo
    {
        return self::filterFileInfo($files, function ($a, $b) {
            return $a->getMTime() > $b->getMTime() ? $a : $b;
        });
    }

    public static function globClean(string $pattern): void
    {
        foreach (\glob($pattern) as $f)
            \unlink($f);
    }

    public static function filterFileInfo(array $files, callable $aggregate): \SplFileInfo
    {
        $finfo = new \SplFileInfo(\array_pop($files));

        foreach ($files as $f) {
            $current = new \SplFileInfo(\array_pop($files));
            $finfo = $aggregate($finfo, $current);
        }
        return $finfo;
    }
}