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

    private ?array $zeroQueriesMeasures;

    private ?array $timeoutMeasures;

    private ?array $measures;

    private int $nbRepetitions;

    private string $testDir;

    private string $queryName;

    private array $measuresGroups = [];

    private string $wd;

    public function __construct(string $testDirectory)
    {
        $this->testDir = $testDirectory;
        $this->wd = \getcwd() . "/$testDirectory";
        $this->nbRepetitions = - 1;
    }

    public function hasTimeout(): bool
    {
        return isset($this->timeoutMeasures);
    }

    public function hasZeroQueries(): bool
    {
        return isset($this->zeroQueriesMeasures);
    }

    public function getTestName(): string
    {
        return "{$this->getDirectoryName()}/{$this->getQueryName()}";
    }

    public function getDirectoryName(): string
    {
        return $this->testDir;
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }

    public function getNbRepetitions(): int
    {
        return $this->nbRepetitions;
    }

    public function getMeasures(): array
    {
        if (! empty($this->measures))
            return $this->measures;

        $ref = $this->timeoutMeasures ?? $this->zeroQueriesMeasures;
        $i = $this->getNbRepetitions();

        while ($i --)
            $ret[] = $ref;

        return $ret;
    }

    public function getMeasuresFromRepetition(int $i): array
    {
        return $this->timeoutMeasures ?? $this->zeroQueriesMeasures ?? $this->measures[$i] ?? [];
    }

    public function loadMeasuresOf(string $queryName): self
    {
        \wdPush($this->wd);
        $ret = null;

        // q_measures-i.txt test
        $mfiles = self::getMeasuresTxtFiles($queryName);

        if (! empty($mfiles)) {
            \natsort($mfiles);
            $measures = [];

            foreach ($mfiles as $mf)
                $measures[] = self::parseLinesOfMeasures(\file($mf));

            $ret = clone $this;
            $ret->computeNbRepetitions();
            $ret->queryName = $queryName;
            $ret->measures = $measures;
            $ret->searchForProblems();
        } else {
            // TODO
            // csv test
            $fname = "$queryName.csv";

            if (is_file($fname))
                $data = \array_merge_recursive(\CSVReader::read($fname), $this->loadBenchParams());
            else
                throw new ValueError("Can't handle `$fname`");
        }
        \wdPop();
        return $ret;
    }

    public function setMeasures(array $measures): self
    {
        $ret = clone $this;
        $ret->measures = array_values($measures);
        $ret->nbRepetitions = \count($measures);
        $ret->searchForProblems();
        return $ret;
    }

    private function searchForProblems()
    {
        $this->zeroQueriesMeasures = null;
        $this->timeoutMeasures = null;

        $measures = $this->measures;
        $c = \count($measures);
        $lastMeasures = $measures[$c - 1];

        if (\array_key_exists("error.timeout", $lastMeasures)) {
            $this->timeoutMeasures = $lastMeasures;
            $this->measures = null;
        } elseif ($lastMeasures['queries']['total'] === 0) {
            $this->zeroQueriesMeasures = $lastMeasures;
            $this->measures = null;
        }
    }

    private function computeNbRepetitions()
    {
        if (- 1 !== $this->nbRepetitions)
            return;

        $it = self::searchMeasuresFiles(".", "#^\./\d+_measures-(\d+).txt$#", RegexIterator::ALL_MATCHES);
        $values = \array_column(\iterator_to_array($it), 1);
        $values = \array_column($values, 0);
        $this->nbRepetitions = \max($values);
    }

    public function columns(?string $sort_measureName = null): self
    {
        $testMeasures = $this->measures;

        if (null === $testMeasures)
            return $this;

        $acc = [];

        if (null != $sort_measureName) {
            foreach ($testMeasures as $k => $measures)
                foreach ($measures['measures'] as $km => $m)
                    $testMeasures[$k]['measures'][$km] = self::decodeStringTimeMeasure($m);

            // Order tests measures
            \usort($testMeasures, function ($a, $b) use ($sort_measureName) {
                return $a['measures'][$sort_measureName]['r'] - $b['measures'][$sort_measureName]['r'];
            });

            foreach ($testMeasures as $k => $measures)
                foreach ($measures['measures'] as $km => $m)
                    $testMeasures[$k]['measures'][$km] = self::encodeTimeMeasure($m);
        }

        foreach ($testMeasures as $measures)
            foreach ($measures as $group => $groupMeasures)
                foreach ($groupMeasures as $km => $m)
                    $acc[$group][$km][] = $m;

        $ret = clone $this;
        $ret->measures = $acc;
        return $ret;
    }

    public const AVERAGE_CONFIG_DEFAULT = [
        'sort_measureName' => null,
        'forgetMinMax' => 1
    ];

    public function average(array $config = []): self
    {
        if (! empty($this->zeroQueriesMeasures))
            return $this;
        if (! empty($this->timeoutMeasures))
            return $this;

        $config += self::AVERAGE_CONFIG_DEFAULT;
        \extract($config);
        $nbTests = \count($this->measures);

        // TODO timeout
        $columns = $this->columns($sort_measureName)->measures;

        foreach ($columns as $groupMeasures => $groupOfValues) {
            $measureGroup = $groupMeasures === 'measures';

            foreach ($groupOfValues as $groupValues => $values) {

                if (($n = \count($values)) !== $nbTests)
                    throw new \Exception("Error for measure $groupMeasures.$groupValues, bad number of values: $n/$nbTests");

                if ($measureGroup) {
                    $v = \array_map("self::decodeStringTimeMeasure", $values);
                    $v = \array_reduce($v, "self::sumArrayTimeMeasures", self::emptyTimeMeasures());
                    $v = \array_map(fn ($v) => $v / $nbTests, $v);
                    $v = self::encodeTimeMeasure($v);
                } elseif (is_numeric($values[0]))
                    $v = \array_sum($values) / $nbTests;
                else {
                    $unique = \array_unique($values);

                    if (\count($unique) === 1)
                        $v = \Help\Arrays::first($unique);
                    else
                        $v = \Help\Arrays::encode($values);
                }
                $columns[$groupMeasures][$groupValues] = $v;
            }
        }
        $ret = clone $this;
        $ret->zeroQueriesMeasures = null;
        $ret->timeoutMeasures = null;
        $ret->measures = $columns;
        $ret->nbRepetitions = 1;
        return $ret;
    }

    private function getAvgMeasures(array $option = MEASURES_OPTIONS_DEFAULT): array
    {
        $sortMeasure = $this->config['bench.sort.measure'];
        \usort($measures, function ($a, $b) use ($sortMeasure) {
            return $a['measures'][$sortMeasure]['r'] - $b['measures'][$sortMeasure]['r'];
        });
        return $measures;
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
    private static function searchMeasuresFiles(string $directory, ?string $regex = null, int $regexMode = RegexIterator::MATCH): \Iterator
    {
        $it = new RecursiveDirectoryIterator($directory, //
        FilesystemIterator::KEY_AS_PATHNAME | //
        FilesystemIterator::CURRENT_AS_FILEINFO | //
        FilesystemIterator::FOLLOW_SYMLINKS); //

        if (null !== $regex)
            $it = new RecursiveRegexIterator($it, $regex, $regexMode);

        $it = new RecursiveIteratorIterator($it);
        $it->setMaxDepth(1);
        return $it;
    }

    private static function getMeasuresTxtFiles(string $queryName): array
    {
        $qpcre = \preg_quote($queryName);
        $it = self::searchMeasuresFiles(".", "#^\./${qpcre}_measures-\d+\.txt$#");
        $ret = [];

        foreach ($it as $file) {
            $ret[] = $file;
        }
        return $ret;
    }

    public static function getTestsFromDirectory(string $directory): array
    {
        $it = self::searchMeasuresFiles($directory);
        $ret = [];

        foreach ($it as $file) {
            $fname = $file->getFilename();

            if (\preg_match('#^([^@][^/]*)\.csv$#', $fname, $matches) || //
            \preg_match('#^(.+)_measures-\d+\.txt$#', $fname, $matches)) {
                $testDir = \basename(\dirname($file->getRealPath()));
                $ret[] = "$testDir/{$matches[1]}";
            }
        }
        return \array_unique($ret);
    }

    public static function loadTestMeasures(string $test)
    {
        return (new self(\dirname($test)))->loadMeasuresOf(\basename($test));
    }

    public static function averageOf(string $queryName, string $sort_measureName, int $forgetMinMax = 1): self
    {
        return self::loadMeasuresOf($queryName)->average($sort_measureName, $forgetMinMax);
    }

    // ========================================================================
    public static function writeAsIni(array $properties, $streamOut): void
    {
        foreach ($properties as $group => $items) {
            fwrite($streamOut, "\n[$group]\n");

            foreach (self::asIniArray($items) as $k => $v) {
                fwrite($streamOut, "$k: $v\n");
            }
        }
    }

    private static function asIniArray(array $properties): array
    {
        $ret = [];

        foreach ($properties as $k => $v) {

            if (\is_array($v)) {
                $v = \implode(',', $v);
                $v = "[$v]";
            }
            $ret[$k] = $v;
        }
        return $ret;
    }

    public static function writeAsJavaProperties(array $properties, $streamOut): void
    {
        foreach (self::asJavaPropertiesPairs($properties) as $pair) {
            list ($k, $v) = $pair;
            fwrite($streamOut, "$k: $v\n");
        }
    }

    private static function asJavaPropertiesPairs(array $properties): array
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

            if (preg_match("#^\[(.+)\]$#", $line, $matches)) {
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
        return $ret;
    }

    // ========================================================================
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

    public static function sumArrayTimeMeasures(array $a, array $b): array
    {
        $ret = [];

        foreach (self::TIME_MEASURE as $tm) {
            $ret[$tm] = (int) $a[$tm] + (int) $b[$tm];
        }
        return $ret;
    }

    public static function sumStringTimeMeasures(string $a, string $b): string
    {
        return self::encodeArrayTimeMeasure(self::sumArrayTimeMeasures(self::decodeStringTimeMeasure($a), self::decodeStringTimeMeasure($b)));
    }

    private static function appExtractMeasures($measures): array
    {
        foreach ($measures as &$meas)
            $meas = \Measures::decodeTimeMeasure($meas);

        return $measures;
    }

    private static function emptyTimeMeasures(array $fields = self::TIME_MEASURE): array
    {
        $ret = [];

        foreach ($fields as $tm) {
            $ret[$tm] = 0;
        }
        return $ret;
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

    public static function encodeTimeMeasure($timeMeasure): string
    {
        if (self::isStringTimeMeasure($timeMeasure))
            return $timeMeasure;

        return "r{$timeMeasure['r']}u{$timeMeasure['u']}s{$timeMeasure['s']}c{$timeMeasure['c']}";
    }

    public static function encodeArrayTimeMeasure(array $timeMeasure): string
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

            // Int
            if (\preg_match("/$tm-?(\d+)/", $timeMeasures, $capture)) {
                $meas[$tm] = (int) $capture[1];
            } // Float
            elseif (\preg_match("/$tm-?(\d+\.\d+)/", $timeMeasures, $capture)) {
                $meas[$tm] = (float) $capture[1];
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