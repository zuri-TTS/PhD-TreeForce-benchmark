<?php

final class Measures
{

    private const TIME_MEASURE = [
        'r',
        'u',
        's',
        'c'
    ];

    private const MEASURES_OPTIONS_DEFAULT = [
        'sortMeasure' => ''
    ];

    private $testDir;

    private $measuresGroups = [];

    private $wd;

    public function __construct(string $testDirectory)
    {
        $this->testDir = $testDirectory;
        $this->wd = \getcwd() . "/$testDirectory";
    }

    public function getAvgMeasures(array $option = MEASURES_OPTIONS_DEFAULT): array
    {
        $sortMeasure = $this->config['bench.sort.measure'];
        \usort($measures, function ($a, $b) use ($sortMeasure) {
            return $a['measures'][$sortMeasure]['r'] - $b['measures'][$sortMeasure]['r'];
        });
        return $measures;
    }

    public function loadMeasuresOf(string $queryName)
    {
        \wdPush($this->wd);

        // csv test
        $fname = "$queryName.csv";

        if (is_file($fname))
            $data = \array_merge_recursive(\CSVReader::read($fname), $this->loadBenchParams());
        else
            throw new ValueError("Can't handle `$fname`");

        \wdPop();
        return $data;
    }

    private function loadBenchParams(): array
    {
        $ret = [];
        $fname = '@config.csv';

        if (\is_file($fname))
            $ret = \CSVReader::read($fname);

        return $ret;
    }

    // ========================================================================
    // static
    public static function loadTestMeasures(string $test)
    {
        return (new self(\dirname($test)))->loadMeasuresOf(\basename($test));
    }

    public static function writeAsJavaProperties(array $properties, $streamOut): void
    {
        foreach (self::asJavaPropertiesPairs($properties) as $pair) {
            list ($k, $v) = $pair;
            fwrite($streamOut, "$k: $v\n");
        }
    }

    public static function asJavaPropertiesPairs(array $properties): array
    {
        $ret = [];

        foreach ($properties as $k => $v) {

            if (! \is_array($v))
                $v = (array) $v;

            foreach ($v as $v)
                $ret[] = [
                    $k,
                    $v
                ];
        }
        return $ret;
    }

    public static function parseLinesOfMeasures(iterable $lines): array
    {
        $ret = [];

        foreach ($lines as $line) {

            if (preg_match("#\[(.+)\]#", $line, $matches)) {
                $group = $matches[1];
                $p = &$ret[$group];
            } else if (preg_match("#([^\s=:]+)[\s=:]+([^\s]+)#", $line, $matches)) {
                $var = $matches[1];
                $val = $matches[2];

                if (\is_numeric($val))
                    $val = (int) $val;

                $p[$var] = $val;
            }
        }
        // $ret['measures'] = self::appExtractMeasures($ret['measures'] ?? []);
        return $ret;
    }

    public static function toArrayTimeMeasures(array $data): array
    {
        $ret = [];
        foreach ($data as $k => $v) {

            if (self::isStringTimeMeasure($v))
                $ret['measures'][$k] = self::decodeTimeMeasure($v);
            elseif (self::isArrayTimeMeasure($v))
                $ret['measures'][$k] = $v;
            else
                $ret[$k] = $v;
        }
        return $ret;
    }

    public static function toStringTimeMeasures(array $data): array
    {
        $ret = [];
        foreach ($data as $k => $items) {

            foreach ($items as $ki => $v) {

                if (self::isArrayTimeMeasure($v))
                    $v = self::encodeTimeMeasure($v);

                $ret[$k][$ki] = $v;
            }
        }
        return $ret;
    }

    public static function sumArrayMeasures(array $a, array $b): array
    {
        $ret = [];

        foreach (self::TIME_MEASURE as $tm) {
            $ret[$tm] = (int) $a[$tm] + (int) $b[$tm];
        }
        return $ret;
    }

    public static function sumStringMeasures(string $a, string $b): string
    {
        return self::encodeMeasure(self::sumArrayMeasures(self::decodeMeasure($a), self::decodeMeasure($b)));
    }

    private static function appExtractMeasures($measures): array
    {
        foreach ($measures as &$meas)
            $meas = \Measures::decodeTimeMeasure($meas);

        return $measures;
    }

    // ========================================================================
    // Time measures
    public static function isTimeMeasure($data, &$decode = null): bool
    {
        return self::isStringTimeMeasure($data, $decode) || (self::isArrayTimeMeasure($data) && $decode = $data);
    }

    public static function isStringTimeMeasure($data, &$decode = null): bool
    {
        return \is_string($data) && \count($decode = self::decodeStringTimeMeasure($data)) === 4;
    }

    public static function isArrayTimeMeasure($data): bool
    {
        return \is_array($data) && count($data) === 4 && \Help\Arrays::keysExists($data, ...self::TIME_MEASURE);
    }

    public static function encodeTimeMeasure(array $timeMeasure): string
    {
        return "r{$timeMeasure['r']}u{$timeMeasure['u']}s{$timeMeasure['s']}c{$timeMeasure['c']}";
    }

    public static function decodeTimeMeasure($timeMeasures): array
    {
        if (self::isArrayTimeMeasure($timeMeasures))
            return $timeMeasures;

        return self::decodeStringTimeMeasure($timeMeasures);
    }

    public static function decodeStringTimeMeasure(string $timeMeasures, array $measureTypes = []): array
    {
        if (empty($measureTypes))
            $measureTypes = self::TIME_MEASURE;

        $meas = [];

        foreach ($measureTypes as $tm) {

            if (\preg_match("/$tm(-?\d+)/", $timeMeasures, $capture)) {
                $meas[$tm] = (int) $capture[1];
            }
        }
        return $meas;
    }

    public static function getOneMeasure(array $data, string $measureName, string $measureType = 'r')
    {
        if (\array_key_exists('measures', $data)) {
            $val = $data['measures'][$measureName] ?? null;
        }
        if (! isset($val) && \array_key_exists($measureName, $data)) {
            $val = $data[$measureName];
        }
        if (isset($val)) {

            if (self::isStringTimeMeasure($val, $decode))
                return $decode[$measureType];
            elseif (\is_array($val))
                return $val[$measureType] ?? null;
        }
        return null;
    }
}