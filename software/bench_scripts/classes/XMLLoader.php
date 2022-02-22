<?php

final class XMLLoader
{

    private string $group;

    private string $groupPath;

    private array $dataSets;

    private array $path = [];

    private array $files;

    private array $filesData;

    private array $dataSets_exists;

    private array $doNotSimplify;

    private array $summary;

    private int $summaryDepth;

    private bool $summarize;

    private array $stats = [
        'documents.nb' => 0
    ];

    private \Data\ILoader $groupLoader;

    public function __construct(array $dataSets)
    {
        $groups = [];

        foreach ($dataSets as $dataSet)
            $groups[$group = $dataSet->group()] = null;

        if (\count($groups) > 1)
            throw new \Exception("Multiple groups given: " . \implode(',', $groups));

        $basePath = \getBenchmarkBasePath();
        $this->group = $group;
        $this->groupPath = DataSets::getGroupPath($group);
        $this->dataSets = $dataSets;

        $this->summarize = false;
        $this->groupLoader = DataSets::getGroupLoader($this->group);
        $this->doNotSimplify = $this->groupLoader->getDoNotSimplifyConfig();
    }

    // ========================================================================
    private function summaryExists(DataSet $dataSet)
    {
        foreach ($this->summaryPaths($dataSet) as $p) {
            if (! \is_file($p))
                return false;
        }
        return true;
    }

    private function summaryPaths(DataSet $dataSet): array
    {
        return [
            $this->summaryPath($dataSet, 'key'),
            $this->summaryPath($dataSet, 'key-type')
        ];
    }

    private function summaryPath(DataSet $dataSet, string $type)
    {
        return "{$dataSet->path()}/summary-$type.txt";
    }

    public function getDataSet(): DataSet
    {
        return $this->dataSet;
    }

    // ========================================================================
    public function summarize(bool $summarize = true): self
    {
        $this->summarize = $summarize;
        return $this;
    }

    // ========================================================================
    private function prepareDir(DataSet $dataSet)
    {
        $dataSetOutPath = $dataSet->path();

        if (! \is_dir($dataSetOutPath)) {
            \mkdir($dataSetOutPath, 0777, true);
        }
    }

    private function setPostProcesses()
    {
        $this->summary = [];
        $this->summaryDepth = 0;
        $rules = \array_map(fn ($d) => $d->rules(), $this->dataSets);

        $filesData = \array_map(function ($dataSet) {
            $this->prepareDir($dataSet);
            $path = $dataSet->path();

            $rulesFiles = $dataSet->rulesFilesPath();
            $exists = \is_file("$path/end.json");

            if ($exists && (! $this->summarize || $this->summaryExists($dataSet)))
                return $dataSet;

            return [
                'randomize' => empty($rulesFiles) ? fn ($data) => $data : $this->groupLoader->getLabelReplacerForDataSet($dataSet),
                'simplify' => $dataSet->isSimplified(),
                'path' => $path,
                'dataset' => $dataSet
            ];
        }, $this->dataSets);

        list ($this->filesData, $this->dataSets_exists) = \array_partition($filesData, '\is_array');

        foreach ($this->filesData as $f) {

            $this->summary[$f['dataset']->id()] = [];

            if (! \is_dir($f['path']))
                \mkdir($f['path']);
        }
    }

    public function convert()
    {
        $this->setPostProcesses();

        echo "Generating json:\n";

        foreach ($this->dataSets as $d)
            echo "$d", \in_array($d, $this->dataSets_exists) ? ' (Exists)' : '', "\n";

        if (empty($this->filesData))
            return;

        $this->read($this->groupLoader->getXMLReader());

        foreach ($this->filesData as $fd) {
            \touch("{$fd['path']}/end.json");

            if ($this->summarize) {
                \ksort($this->summary[$fd['dataset']->id()]);
                $this->writeSummary($fd['dataset']);
            }
        }
        \printPHPFile("$this->groupPath/stats.php", $this->stats);
    }

    // ========================================================================
    public function deleteXMLFile()
    {
        return $this->groupLoader->deleteXMLFile();
    }

    public function dropEmpty()
    {
        foreach ($this->dataSets as $dataSet) {
            echo "Trying to drop $dataSet: ";
            echo self::rmDataSet($dataSet) ? 'Success' : '!!Failed!!';
            echo "\n";
        }
    }

    public function drop()
    {
        foreach ($this->dataSets as $dataSet) {
            echo "Dropping $dataSet: ";
            self::cleanDataSet($dataSet, "*");
            echo self::rmDataSet($dataSet) ? 'Success' : '!!Failed!!';
            echo "\n";
        }
    }

    public function clean()
    {
        foreach ($this->dataSets as $dataSet) {
            echo "Cleaning <$dataSet>\n";
            self::cleanDataSet($dataSet, "*.json");
        }
    }

    // ========================================================================
    private static function rmDataSet(DataSet $dataSet): bool
    {
        $dataSetOutPath = $dataSet->path();

        if (! \is_dir($dataSetOutPath))
            return true;

        return @\rmdir($dataSetOutPath);
    }

    private static function cleanDataSet(DataSet $dataSet, string $pattern)
    {
        $dataSetOutPath = $dataSet->path();

        if (\is_dir($dataSetOutPath)) {
            $files = \wdOp($dataSetOutPath, fn () => self::cleanGlob($pattern));
        }
    }

    private static function cleanGlob(string $pattern): void
    {
        foreach (\glob($pattern) as $f) {
            \is_file($f) && \unlink($f);
        }
    }

    // ========================================================================
    private function reachUnwind(string $path): ?string
    {
        $ret = [];

        foreach ($this->groupLoader->getUnwindConfig() as $u) {
            if (0 === \strpos($u, $path))
                $ret[] = $u;
        }
        return \count($ret) === 1 ? $ret[0] : null;
    }

    private function read(XMLReader $reader): void
    {
        $path = &$this->path;
        $path = [];
        $rules = \array_keys($this->filesData);

        \wdPush($this->groupPath);
        while ($reader->read()) {
            switch ($reader->nodeType) {
                case XMLReader::TEXT:
                    break;
                case XMLReader::ELEMENT:
                    $path[] = $reader->name;
                    $paths = \implode('.', $path);
                    $u = $this->reachUnwind($paths);

                    if (null === $u)
                        break;

                    echo "Unwinding $u\n";
                    $this->files = \array_combine( //
                    $rules, //
                    \array_map(fn ($theRules) => new \SplFileObject(self::getFileFromUnwind($u, $this->filesData[$theRules]['path']), 'w'), $rules));

                    $reader->read();
                    $u_a = explode('.', $u);
                    $this->unwind($reader, $u_a);

                    // Close files
                    \array_walk($this->files, function (&$f) {
                        $f = null;
                    });
                    break;
                case XMLReader::END_ELEMENT:
                    \array_pop($path);
                    break;
                default:
            }
        }
        \wdPop();
    }

    private function unwind(XMLReader $reader, array $unwind)
    {
        $path = &$this->path;
        $keyPattern = $unwind[\count($path)];

        if ('$' === $keyPattern)
            $this->unwindUndefined($reader, $unwind);
        elseif ('*' === $keyPattern)
            $this->unwindEach($reader, $unwind);
        else {
            fwrite(STDERR, "Can't handle $keyPattern");
            exit(1);
        }
    }

    private function unwindUndefined(XMLReader $reader, array $unwind)
    {
        $path = &$this->path;
        while ($reader->next()) {
            switch ($reader->nodeType) {
                case XMLReader::TEXT:
                    break;
                case XMLReader::ELEMENT:
                    $path[] = $reader->name;
                    $reader->read();
                    $this->unwind($reader, $unwind);
                    break;
                case XMLReader::END_ELEMENT:
                    \array_pop($path);
                    return;
                default:
            }
        }
    }

    private function unwindEach(XMLReader $reader, array $unwind)
    {
        $path = &$this->path;
        while ($reader->next()) {
            switch ($reader->nodeType) {
                case XMLReader::TEXT:
                    break;
                case XMLReader::ELEMENT:
                    self::writeJson($reader);
                    break;
                case XMLReader::END_ELEMENT:
                    \array_pop($path);
                    return;
                default:
            }
        }
    }

    private function writeJson(XMLReader $reader)
    {
        $data = self::array_path($this->path, [
            $reader->name => [
                self::toPHP(\simplexml_load_string($reader->readOuterXml()))
            ]
        ]);

        foreach ($this->files as $d => $file) {
            $fileData = $this->filesData[$d];
            $file->fwrite($this->toJsonString($fileData['dataset'], $data, $fileData['simplify'], $fileData['randomize']) . "\n");
        }
    }

    // ========================================================================
    private static function getFileFromUnwind(string $unwind, string $baseDir = ""): string
    {
        if (! empty($baseDir))
            $baseDir .= '/';

        return "$baseDir$unwind.json";
    }

    private static function getFilesFromUnwind(array $unwinds, string $baseDir = ""): array
    {
        $ret = [];
        $baseDir = \rtrim($baseDir, '/');

        foreach ($unwinds as $unwind) {
            $ret[$unwind] = self::getFileFromUnwind($unwind, $baseDir);
        }
        return $ret;
    }

    private function doSimplifyObject(&$keys): void
    {
        if (! \is_array($keys))
            return;

        foreach ($keys as $k => &$e) {
            $doNotSimplify = \in_array($k, $this->doNotSimplify);

            if ($doNotSimplify)
                $e = (array) $e;

            if (\is_array($e)) {

                foreach ($e as &$se)
                    $this->doSimplifyObject($se);

                if (! $doNotSimplify && \count($e) === 1)
                    $e = $e[0];
            }
        }
    }

    private static function array_path(array $keys, $val): array
    {
        $ret = [];
        $p = &$ret;

        foreach ($keys as $k) {
            $p[$k][] = [];
            $p = &$p[$k][0];
        }
        $p = $val;
        return $ret;
    }

    private function addToSummary(DataSet $dataSet, array $data)
    {
        $depth = 0;
        $keys = [];
        $toProcess = [
            $data
        ];
        $summary = &$this->summary[$dataSet->id()];

        while (! empty($toProcess)) {
            $nextToProcess = [];
            $incDepth = 0;

            foreach ($toProcess as $array) {

                if (! $incDepth && ! \array_is_list($array))
                    $incDepth = 1;

                foreach ($array as $label => $val) {
                    $cdepth = $depth;

                    if (\is_array($val))
                        $nextToProcess[] = $val;

                    $type = (\is_array($val) && \array_is_list($val)) ? 'ARRAY' : 'OBJECT';

                    if (\is_string($label)) {

                        if (! isset($summary[$label]))
                            $summary[$label] = [];

                        $t = &$summary[$label];

                        if (! \in_array($type, $t))
                            $t[] = $type;
                    }
                }
            }
            $depth += $incDepth;
            $toProcess = $nextToProcess;
        }
        $this->summaryDepth = \max($this->summaryDepth, $depth);
    }

    private function writeSummary(DataSet $dataSet)
    {
        $summary = $this->summary[$dataSet->id()];
        $fileType = \fopen($this->summaryPath($dataSet, 'key-type'), 'w');
        $file = \fopen($this->summaryPath($dataSet, 'key'), 'w');

        \fwrite($fileType, "$this->summaryDepth\n");
        \fwrite($file, "$this->summaryDepth\n");

        foreach ($summary as $key => $types) {
            $typeStr = implode(',', $types);
            \fwrite($fileType, "\"$key\":\"$typeStr\"\n");
            \fwrite($file, "\"$key\"\n");
        }
        \fclose($file);
        \fclose($fileType);
    }

    private static function toPHP(\SimpleXMLElement $element): array
    {
        $obj = [];

        foreach ($element->children() as $name => $val) {
            if (\count($val) != 0) {
                $php = self::toPHP($val);
                $obj[$name][] = $php;
            } else {
                $attr = self::getAttributes($val);
                $sval = (string) $val;

                if (empty($attr))
                    $obj[$name][] = $sval;
                elseif (\strlen($sval) > 0)
                    $obj[$name][] = $attr + [
                        '#value' => $sval
                    ];
                else
                    $obj[$name][] = $attr;
            }
        }

        // Clean the one value array
        foreach ($obj as &$subObj) {
            if (\count($subObj) > 1)
                continue;

            $val = $subObj[0] ?? null;

            if (\is_string($val))
                $subObj = $val;
        }
        unset($subObj);

        $name = $element->getName();

        if ($name === "text")
            $obj["#value"] = $element->asXML();

        $attr = self::getAttributes($element);
        return $attr + $obj;
    }

    private static function getAttributes(SimpleXMLElement $element): array
    {
        $attr = [];

        // Add attributes
        foreach ($element->attributes() as $name => $val)
            $attr["@$name"] = (string) $val;

        return $attr;
    }

    private function toJsonString(DataSet $dataSet, array $data, bool $simplify, ?callable $postProcess = null): string
    {
        if ($simplify)
            $this->doSimplifyObject($data);

        if (isset($postProcess)) {
            $data = $postProcess($data);
        }
        $this->addToSummary($dataSet, $data);
        $this->stats['documents.nb'] ++;

        return \json_encode($data);
    }
}
