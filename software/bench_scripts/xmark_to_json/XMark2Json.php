<?php

class XMark2Json
{

    private DataSet $dataSet;

    private array $config;

    private array $unwind;

    private array $path = [];

    private array $files;

    private array $filesData;

    public function __construct(DataSet $dataSet)
    {
        $basePath = getBenchmarkBasePath();

        if (!empty($error = $dataSet->allNotExists())){
            $error = implode(',', $error);
            throw new \Exception("Group set '$error' does not exists");
        }
        $dataSetPath = $dataSet->groupPath();
        $configPath = "$dataSetPath/config.php";
        $this->dataSet = $dataSet;
        $this->config = include $configPath;
        $this->unwind = include __DIR__ . '/unwind.php';
    }

    public function getSeed(): int
    {
        return $this->config['seed'];
    }

    public function getDataSet(): DataSet
    {
        return $this->dataSet;
    }

    private function setPostProcesses()
    {
        $rules = $this->dataSet->getRules();
        $pos = \array_search(DataSet::originalDataSet, $rules, true);
        $this->filesData = [];

        if (false !== $pos) {
            $this->filesData[DataSet::originalDataSet] = [
                'randomize' => fn ($data) => $data,
                'path' => $this->dataSet->dataSetPath(DataSet::originalDataSet)
            ];
            unset($rules[$pos]);
        }

        $this->filesData += \array_combine( //
        $rules, //
        \array_map(fn ($d) => [
            'randomize' => (include __DIR__ . '/json_postprocess-random_keys.php')($d, $this),
            'path' => $this->dataSet->dataSetPath($d)
        ], $rules) //
        );
    }

    public function convert()
    {
        $this->setPostProcesses();
        $this->_convertGroup();
    }

    private function getAllDataSets()
    {
        $dataSets = \scandirNoPoints("$this->dataSetPath/rules");
        $dataSets[] = 'original';
        return $dataSets;
    }

    private function _convertGroup()
    {
        echo "Processing {$this->dataSet->getGroup()} [", implode(',', $this->dataSet->getRules()), "]\n";

        $this->clean();
        $xmarkFilePath = "{$this->dataSet->groupPath()}/xmark.xml";
        $this->read(\XMLReader::open($xmarkFilePath));
    }

    public function delete($dataSets = null)
    {
        $rules = $this->dataSet->getRules();

        foreach ($rules as $dataSet) {
            $this->dataSet->setRules($dataSet);
            $this->_clean();
            rmdir($this->dataSet->dataSetPath());
        }
        $this->dataSet->setRules($rules);
    }

    public function clean()
    {
        $rules = $this->dataSet->getRules();

        foreach ($rules as $dataSet) {
            $this->dataSet->setRules($dataSet);
            $this->_clean();
        }
        $this->dataSet->setRules($rules);
    }

    private function _clean()
    {
        $dataSetId = $this->dataSet->getId();
        echo "Cleaning $dataSetId\n";

        $dataSetOutPath = $this->dataSet->dataSetPath();

        if (! \is_dir($dataSetOutPath))
            \mkdir($dataSetOutPath, 0777, true);
        else {
            foreach (\glob("$dataSetOutPath/*.json") as $f) {
                \is_file($f) && unlink($f);
            }
        }
    }

    private function reachUnwind(string $path): ?string
    {
        $ret = [];

        foreach ($this->unwind as $u) {
            if (0 === \strpos($u, $path))
                $ret[] = $u;
        }
        return \count($ret) === 1 ? $ret[0] : null;
    }

    private function read(XMLReader $reader): void
    {
        $path = &$this->path;
        $path = [];
        $outputPath = $this->dataSet->rulesPath();
        $dataSets = $this->dataSet->getRules();

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
                    $dataSets, //
                    \array_map(fn ($d) => new \SplFileObject(self::getFileFromUnwind($u, $this->filesData[$d]['path']), 'w'), $dataSets));

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
    }

    private function unwind(XMLReader $reader, array $unwind)
    {
        $path = &$this->path;
        $keyPattern = $unwind[count($path)];

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
                self::toPHP(simplexml_load_string($reader->readOuterXml()))
            ]
        ]);
        foreach ($this->files as $d => $file)
            $file->fwrite(self::toJsonString($data, $this->filesData[$d]['randomize']) . "\n");
    }

    // ========================================================================
    private static function _getRelabellings($ruleFile): array
    {
        $lines = file($ruleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ret = [];

        foreach ($lines as $line) {
            if (\trim($line)[0] === '#')
                continue;

            [
                $body,
                $head
            ] = \array_map('trim', explode('->', $line));
            $body = \explode('=', $body, 2)[0];
            $head = \explode('=', $head, 2)[0];

            $body = \trim($body, "'\"");
            $head = \trim($head, "'\"");

            // The head is in the replacement set
            if (! isset($ret[$head]))
                $ret[$head] = [
                    $head
                ];

            $ret[$head][] = $body;
        }
        return $ret;
    }

    public function getRelabellings(string $rulesPath)
    {
        $rules = $this->dataSet->rulesPath($rulesPath) . "/querying.txt";

        if (! is_file($rules)) {
            echo "Warning: rule file $rules does not exists\n";
            $rel = [];
        } else
            $rel = self::_getRelabellings($rules);

        return $rel;
    }

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

    private static function toPHP(SimpleXMLElement $element): array
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
                        '#value' => $Sval
                    ];
                else
                    $obj[$name][] = $attr;
            }
        }

        // Clean the one value array
        foreach ($obj as &$subObj) {
            if (count($subObj) > 1)
                continue;

            $val = $subObj[0] ?? null;

            if (is_string($val))
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

    private static function toJsonString(array $data, ?callable $postProcess = null): string
    {
        if (isset($postProcess)) {
            $data = $postProcess($data);
        }
        return \json_encode($data);
    }
}
