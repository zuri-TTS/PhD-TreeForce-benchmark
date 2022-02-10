<?php

final class XMark2Json
{

    private string $group;

    private string $groupPath;

    private array $dataSets;

    private array $config;

    private array $cmdConfig;

    private array $unwind;

    private array $path = [];

    private array $files;

    private array $filesData;

    private array $dataSets_exists;

    private array $doNotSimplify;

    private string $myBaseDir;

    public function __construct(array $dataSets, array $cmdConfig)
    {
        $groups = [];

        foreach ($dataSets as $dataSet)
            $groups[$group = $dataSet->group()] = null;

        if (\count($groups) > 1)
            throw new \Exception("Multiple groups given: " . \implode(',', $groups));

        $basePath = \getBenchmarkBasePath();
        $this->group = $group;
        $this->groupPath = DataSets::getGroupPath($group);
        $configPath = "$this->groupPath/config.php";
        $this->dataSets = $dataSets;
        $this->myBaseDir = \getBenchmarkBasePath() . '/software/bench_scripts/xmark_to_json';
        $this->config = include $configPath;
        $this->unwind = include "$this->myBaseDir/unwind.php";
        $this->cmdConfig = $cmdConfig;
    }

    public function getSeed(): int
    {
        return $this->config['seed'];
    }

    public function getDataSet(): DataSet
    {
        return $this->dataSet;
    }

    public function doNotSimplify(array $doNotSimplify): XMark2Json
    {
        $this->doNotSimplify = $doNotSimplify;
        return $this;
    }

    private function prepareDir(DataSet $dataSet)
    {
        $dataSetOutPath = $dataSet->path();

        if (! \is_dir($dataSetOutPath)) {
            \mkdir($dataSetOutPath, 0777, true);
        }
    }

    private function setPostProcesses()
    {
        $rules = \array_map(fn ($d) => $d->rules(), $this->dataSets);

        $filesData = \array_map(function ($dataSet) {
            $this->prepareDir($dataSet);
            $path = $dataSet->path();

            $rulesFiles = $dataSet->rulesFilesPath();
            $exists = \is_file("$path/end.json");

            if ($exists)
                return $dataSet;

            return [
                'randomize' => empty($rulesFiles) ? fn ($data) => $data : (include "$this->myBaseDir/json_postprocess-random_keys.php")($dataSet, $this),
                'simplify' => $dataSet->isSimplified(),
                'path' => $path,
                'dataset' => $dataSet
            ];
        }, $this->dataSets);

        list ($this->filesData, $this->dataSets_exists) = \array_partition($filesData, '\is_array');

        foreach ($this->filesData as $f) {

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

        $xmarkFilePath = $this->XMarkFilePath();
        $this->generateXMark($xmarkFilePath);
        $this->read(\XMLReader::open($xmarkFilePath));

        foreach ($this->filesData as $fd)
            \touch("{$fd['path']}/end.json");
    }

    private function generateXMark(string $xmarkFilePath)
    {
        if (\is_file($xmarkFilePath))
            return;

        echo "Generate $xmarkFilePath\n";
        $basePath = getBenchmarkBasePath();
        $xmarkCmd = $this->cmdConfig['xmark.program.path'];
        $factor = $this->config['xmark.factor'];
        $cmd = "'$basePath/$xmarkCmd' -f $factor -o '$xmarkFilePath'";
        echo "Execute $cmd";
        \system($cmd);
    }

    private function XMarkFilePath(): string
    {
        return "$this->groupPath/xmark.xml";
    }

    public function deleteXMark()
    {
        $path = $this->XMarkFilePath();

        if (\is_file($path))
            \unlink($path);
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
        $rules = \array_keys($this->filesData);

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
            $file->fwrite($this->toJsonString($data, $fileData['simplify'], $fileData['randomize']) . "\n");
        }
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

    public function getRelabellings(DataSet $dataSet)
    {
        $rulesPath = $dataSet->rulesPath();
        $rulesFilePath = "$rulesPath/querying.txt";

        if (! \is_dir($rulesPath)) {
            echo "Warning: rule file $rulesFilePath does not exists (for $dataSet)\n";
            $rel = [];
        } else if (! \is_file($rulesFilePath)) {
            $rel = [];
        } else
            $rel = self::_getRelabellings($rulesFilePath);

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

    private function doSimplifyObject(&$keys): void
    {
        if (! \is_array($keys))
            return;

        foreach ($keys as $k => &$e) {
            $doNotSimplify = \in_array($k, $this->doNotSimplify);

            if ($doNotSimplify)
                $e = (array) $e;

            // Add a value to be recognized as an array by TreeForce-Demo
            // if (\count($e) === 1)
            // $e[] = null;

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

    private function toJsonString(array $data, bool $simplify, ?callable $postProcess = null): string
    {
        if ($simplify)
            $this->doSimplifyObject($data);

        if (isset($postProcess)) {
            $data = $postProcess($data);
        }

        return \json_encode($data);
    }
}
