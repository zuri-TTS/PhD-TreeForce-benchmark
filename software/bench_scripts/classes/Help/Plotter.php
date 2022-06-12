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

    public function readJavaProperties($stream)
    {
        if (is_string($stream)) {
            $s = $stream;
            $stream = \fopen('php://memory', 'r+');
            fwrite($stream, $s);
            rewind($stream);
        }

        $ret = [];
        while (false !== ($line = \fgets($stream))) {
            $line = \trim($line);
            if (empty($line))
                continue;
            $parts = \explode('=', $line, 2);

            $ret[$parts[0]] = \trim($parts[1]);
        }
        \fclose($stream);
        return $ret;
    }

    public function encodeDirNameElements(array $elements)
    {
        $fullPattern = $elements['full_pattern'] ?? null;
        $group = $elements['group'];
        $theRules = $elements['rules'];
        $qualifiers = $elements['qualifiers'] ?? null;

        if (isset($elements['full_partition']))
            $coll = $elements['full_partition'];
        else {
            $pid = $elements['partitioning'];
            $coll = empty($pid) ? '' : ".$pid";
            $pid = $elements['partition'];
            $coll .= empty($pid) ? '' : ".$pid";
        }
        $outDir = "[$group$coll][$theRules][$qualifiers]";
        $outDir .= '%s';

        if ($elements['parallel'])
            $outDir .= '[parall]';

        if ($summary = $elements['summary'] ?? null)
            $outDir .= "[summary-{$summary}]";
        if ($elements['filter_types'] ?? false)
            $outDir .= '[filter-types]';
        if ($i = $elements['filter_prefix'])
            $outDir .= "[filter-prefix-$i]";
        if (! empty($coll) && ($pid = $elements['partition_id'] ?? null) && $pid !== '_id')
            $outDir .= "[pid-$pid]";
        if ($summary = $elements['toNative'] ?? null)
            $outDir .= "[toNative-{$elements['toNative']}]";

        return $outDir;
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
            'full_partition' => null,
            'partition_id' => null,
            'filter_types' => false,
            'filter_prefix' => null
        ];

        \preg_match("#^\[((.+)(?:\.(.+))?)\]\[(.+)\]\[(.+)\]#U", $dirName, $matches);
        $ret['full_group'] = $matches[1] ?? null;
        $ret['group'] = $matches[2] ?? null;
        $ret['full_partition'] = $matches[3] ?? null;
        $ret['rules'] = $matches[4] ?? null;
        $ret['qualifiers'] = $matches[5] ?? null;
        $ret['full_pattern'] = \preg_replace('#\[(\d\d\d\d-\d\d-\d\d.+)\]#U', '}[%s]', $dirName);
        \preg_match("#\[filter-prefix-(\d+)\]#U", $dirName, $matches);
        $ret['filter_prefix'] = $matches[1] ?? null;

        list ($ret['partitioning'], $ret['partition']) = explode('.', $ret['full_partition']) + [
            null,
            null
        ];
        if (\preg_match("#\[filter-types\]#U", $dirName, $matches))
            $ret['filter_types'] = $matches[1] ?? null;

        if (\preg_match("#\[pid-(.+)\]#U", $dirName, $matches))
            $ret['partition_id'] = $matches[1] ?? null;

        if (\preg_match("#\[summary-(.+)\]#U", $dirName, $matches))
            $ret['summary'] = $matches[1] ?? null;

        if (\preg_match("#\[toNative-(.+)\]#U", $dirName, $matches))
            $ret['toNative'] = $matches[1] ?? null;

        if (\preg_match("#\[parall]#U", $dirName))
            $ret['parallel'] = true;

        return $ret;
    }
}
