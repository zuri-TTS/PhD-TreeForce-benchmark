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
            'partitioning' => null,
            'partition' => null,
            'rules' => null,
            'qualifiers' => null,
            'summary' => null,
            'toNative' => null,
            'parallel' => false,
            'full_group' => null,
            'full_partition' => null
        ];

        \preg_match("#^\[((.+)(?:\.(.+))?)\]\[(.+)\]\[(.+)\]#U", $dirName, $matches);
        $ret['full_group'] = $matches[1] ?? null;
        $ret['group'] = $matches[2] ?? null;
        $ret['full_partition'] = $matches[3] ?? null;
        $ret['rules'] = $matches[4] ?? null;
        $ret['qualifiers'] = $matches[5] ?? null;

        list ($ret['partitioning'], $ret['partition']) = explode('.', $ret['full_partition']) + [
            null,
            null
        ];

        if (\preg_match("#\[summary-(.+)\]#U", $dirName, $matches))
            $ret['summary'] = $matches[1] ?? null;

        if (\preg_match("#\[toNative-(.+)\]#U", $dirName, $matches))
            $ret['toNative'] = $matches[1] ?? null;

        if (\preg_match("#\[parall]#U", $dirName))
            $ret['parallel'] = true;

        return $ret;
    }
}
