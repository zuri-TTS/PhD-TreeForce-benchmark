<?php
namespace Help;

final class Plotter
{

    private function __construct()
    {
        throw new \Error();
    }

    // ========================================================================
    public static function encodeDataValue($v)
    {
        if (\is_string($v))
            return "\"$v\"";

        return $v;
    }

    public function extractDirNameElements(string $dirName)
    {
        $ret = [
            'group' => null,
            'rules' => null,
            'partition' => null,
            'qualifiers' => null,
            'summary' => null,
            'toNative' => null,
            'parallel' => false
        ];

        \preg_match("#^\[(.+)(?:\.(.+))?\]\[(.+)\]\[(.+)\]#U", $dirName, $matches);
        $ret['group'] = $matches[1] ?? null;
        $ret['partition'] = $matches[2] ?? null;
        $ret['rules'] = $matches[3] ?? null;
        $ret['qualifiers'] = $matches[4] ?? null;

        if (\preg_match("#\[summary-(.+)\]#U", $dirName, $matches))
            $ret['summary'] = $matches[1] ?? null;

        if (\preg_match("#\[toNative-(.+)\]#U", $dirName, $matches))
            $ret['toNative'] = $matches[1] ?? null;

        if (\preg_match("#\[parall]#U", $dirName))
            $ret['parallel'] = true;

        return $ret;
    }
}
