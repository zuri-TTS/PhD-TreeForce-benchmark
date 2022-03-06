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
        $destVal = $getOut = [];

        $name = $reader->name;
        $this->toPHP($reader, $destVal, $getOut);

        $data = self::array_path($this->path, [
            $name => $destVal
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

        foreach ($cp = $keys as $name => $val) {
            $e = &$keys[$name];

            if (! is_array($e))
                continue;

            if (! \array_is_list($e))
                throw new \Exception("Element must be a list; have: $name" . var_export($e, true));

            $c = \count($e);

            if ($c === 0)
                throw new \Exception("Should never happens");

            foreach ($e as &$se)
                $this->doSimplifyObject($se);

            if (! $this->groupLoader->isList($name)) {

                if ($c !== 1 || ! isset($val[0]))
                    throw new \Exception("Element cannot be a list; have: $name:" . var_export($e, true));

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

    private static function getAttributesOf(\XMLReader $reader, string $prefix = '@'): array
    {
        $ret = [];

        while ($reader->moveToNextAttribute()) {
            $ret["$prefix$reader->name"][] = $reader->value;
        }
        return $ret;
    }

    private function mergeArrayContents(array &$theObject, string $name, $val)
    {
        $present = $theObject[$name] ?? null;

        if (null !== $present) {

            if (\is_array_list($present))

                if (\is_array_list($val))
                    $theObject[$name] = \array_merge($theObject[$name], $val);
                else
                    $theObject[$name][] = $val;
        } else {
            $theObject[$name] = $val;
        }
    }

    private const textKey = '#text';

    private $readNext = true;

    private function toPHP(\XMLReader $reader, array &$out, array &$getOut): void
    {
        if ($reader->nodeType !== XMLReader::ELEMENT)
            throw new \Exception("The reader must be on an element; have $reader->nodeType");

        $obj = [];
        $name = $reader->name;
        $val = $reader->value;
        $nbText = $nbElements = 0;

        // Handles attributes
        {
            $attr = self::getAttributesOf($reader);
            $getOut = [];

            foreach ($attr as $aname => $val) {

                if ($this->groupLoader->getOut($name, $aname)) {
                    $getOut["$name$aname"] = $val;
                    unset($attr[$aname]);
                }
            }

            if (! empty($attr)) {
                $obj = $attr;
            }
        }

        if ($this->groupLoader->isText($name)) {
            $obj[self::textKey . $nbText] = $reader->readInnerXML();
            $reader->next();
            $nbText = 1;

            // Needed because can jump after the next element in the for loop
            $this->readNext = false;
        } else {

            for (;;) {

                if ($this->readNext)
                    $reader->read();
                else
                    $this->readNext = true;

                switch ($reader->nodeType) {
                    case XMLReader::SIGNIFICANT_WHITESPACE:
                    case XMLReader::COMMENT:
                        break;
                    case XMLReader::TEXT:
                        {
                            $obj[self::textKey . $nbText] = \trim($reader->value);
                            $nbText ++;
                            break;
                        }
                    case XMLReader::ELEMENT:
                        {
                            $subObj = [];

                            $php = $gout = [];
                            $cname = $reader->name;

                            $this->toPHP($reader, $php, $gout);
                            $obj = \array_merge($obj, $gout);
                            $this->mergeArrayContents($obj, $cname, $php);
                            break;
                        }
                    case XMLReader::END_ELEMENT:
                        {
                            if ($reader->name !== $name)
                                throw new \Exception("Error, waiting for end element: $name; has $reader->name=" . print_r($obj, true));

                            break 2;
                        }
                    default:
                        throw new \Exception("Cannot handle node $reader->name:$reader->nodeType");
                }
            }
        }

        if ($nbElements >= 1);
        elseif ($nbText === 1) {
            $k = self::textKey . "0";
            $stringVal = $obj[$k];
            unset($obj[$k]);

            if ($this->groupLoader->isObject($name))
                $obj[self::textKey] = $stringVal;
            else
                $obj = $stringVal;
        }

        // Handles isMultipliable
        if (\is_array($obj)) {

            foreach ($obj as $name => $val) {

                if (! is_array($val))
                    continue;

                if ($this->groupLoader->isMultipliable($name)) {
                    $c = \count($val);

                    if ($c > 1) {
                        $obj["multi$name"] = $val;
                        unset($obj[$name]);
                    }
                }
            }
        }

        // Makes the output
        if ($nbText === 0 || $nbElements === 0)
            $out[] = $obj;
        else
            $out = $obj;
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
