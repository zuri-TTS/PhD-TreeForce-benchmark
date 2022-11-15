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
        'documents.nb' => 0,
        'edges.nb' => 0
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

    public static function of(\DataSet ...$dataSets)
    {
        return new self($dataSets);
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
        // $this->summarize = $summarize;
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

            $i = 0;
            $partition_i = new SplObjectStorage();
            $fp = [];
            $nb_a = [];
            $i_a = [];
            \wdPush($dataSet->path());

            foreach ($dataSet->getPartitions() as $partition) {
                $jsonFile = $partition->getJsonFile();
                $partition_i[$partition] = $i ++;
                $fp[] = \fopen($jsonFile, 'w');
                $nb_a[] = 0;
                $i_a[] = 0;
            }
            \wdPop();

            return [
                'randomize' => $this->groupLoader->getLabelReplacerForDataSet($dataSet) ?? fn ($data) => $data,
                'simplify' => $dataSet->isSimplified(),
                'path' => $path,
                'dataset' => $dataSet,
                'pindex' => $partition_i,
                'fp' => $fp,
                'nbDocuments' => $nb_a,
                'offsets' => $i_a
            ];
        }, $this->dataSets);

        list ($this->filesData, $this->dataSets_exists) = \array_partition($filesData, '\is_array');
        $readStats = false;

        foreach ($this->filesData as $f) {

            $this->summary[$f['dataset']->id()] = [];

            if (! \is_dir($f['path']))
                \mkdir($f['path']);

            if (\count($f['fp']) > 1)
                $readStats = true;
        }

        if ($readStats) {
            $this->readStats($this->groupLoader->getXMLReader());

            foreach ($this->filesData as &$f) {
                // reset randomizer
                $f['randomize'] = $this->groupLoader->getLabelReplacerForDataSet($f['dataset']) ?? fn ($data) => $data;
                $total = $f['nbDocuments'][0];

                for ($i = 1, $c = \count($f['fp']); $i < $c; $i ++) {

                    $f['offsets'][$i] = $total;
                    $total += $f['nbDocuments'][$i];
                }
            }
        }
    }

    public function convert()
    {
        $this->setPostProcesses();

        echo "Generating json:\n";
        $datasets_exists = \array_map(fn ($d) => (string) $d, $this->dataSets_exists);

        foreach ($this->dataSets as $d) {
            echo "$d", \in_array((string) $d, $datasets_exists) ? ' (Exists)' : '', "\n";
        }
        if (empty($this->filesData))
            return;

        $this->read($this->groupLoader->getXMLReader());

        foreach ($this->filesData as $fd) {
            \touch("{$fd['path']}/end.json");

            foreach ($fd['fp'] as $f)
                \fclose($f);

            if ($this->summarize) {
                \ksort($this->summary[$fd['dataset']->id()]);
                $this->writeSummary($fd['dataset']);
            }
        }
        \printPHPFile("$this->groupPath/stats.php", $this->stats);
    }

    // ========================================================================
    public function load()
    {
        $dataSets = $this->dataSets;

        foreach ($dataSets as $ds)
            MongoImport::importDataSet($ds);
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

    public function clean(string $globPattern = "*.json")
    {
        foreach ($this->dataSets as $dataSet) {
            echo "Cleaning <$dataSet>\n";
            self::cleanDataSet($dataSet, $globPattern);
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

            if (\is_file($f)) {
                echo "Delete $f\n";
                \unlink($f);
            }
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

    private bool $readStats = false;

    private function readStats(XMLReader $reader): void
    {
        $this->readStats = true;
        $this->read($reader);
        $this->readStats = false;
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

                    if ($this->readStats)
                        echo "Stats for $u\n";
                    else
                        echo "Unwinding $u\n";

                    $reader->read();
                    $u_a = explode('.', $u);
                    $this->unwind($reader, $u_a);
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
        $this->doSimplifyText($data);
        $this->stats['documents.nb'] ++;
        $dataSimple = null;

        foreach ($this->filesData as &$fileData) {
            $postProcess = $fileData['randomize'];
            $data2 = $data;

            if ($fileData['simplify']) {
                if (null === $dataSimple) {
                    $dataSimple = $data;
                    $this->doSimplifyObject($dataSimple);
                }
                $data2 = $dataSimple;
            } else
                $data2 = $data;

            if (isset($postProcess))
                $data2 = $postProcess($data2);

            if ($this->readStats)
                $this->updateStats($data2, $fileData);
            else
                $this->writeFinal($data2, $fileData);

            $this->stats['edges.nb'] += \Help\Arrays::jsonRecursiveCount($data2);
        }
    }

    private function updateStats(array $data, array &$fileData): void
    {
        $partition = \Data\Partitions::getPartitionForData($fileData['dataset']->getPartitions(), $data);
        $i = $fileData['pindex'][$partition];
        $fileData['nbDocuments'][$i] ++;
    }

    private function writeFinal(array $data, array &$fileData): void
    {
        $partition = \Data\Partitions::getPartitionForData($fileData['dataset']->getPartitions(), $data);
        $i = $fileData['pindex'][$partition];

        $fp = $fileData['fp'][$i];
        $id = ++ $fileData['offsets'][$i];
        $id = [
            '_id' => $id,
            'pid' => $i
        ];
        \fwrite($fp, \json_encode($id + $data) . "\n");
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

    private function doSimplifyText(&$array, $name = ""): void
    {
        if ($array === []);
        elseif (\array_is_list($array))
            $this->doSimplifyListText($name, $array);
        elseif (\is_array($array))
            $this->doSimplifyObjectText($name, $array);
    }

    private function doSimplifyObjectText($name, &$object): void
    {
        if (! \is_array($object) || \array_is_list($object))
            throw new \Exception("Element $name must be an object; have: " . \print_r($object, true));

        foreach ($object as $name => &$list) {

            if (\is_array($list))
                $this->doSimplifyListText($name, $list);
        }
    }

    private function doSimplifyListText($name, &$list): void
    {
        if (! \is_array_list($list))
            throw new \Exception("Element $name must be a list; have: " . \print_r($list, true));

        $c = \count($list);

        if ($c === 0)
            throw new \Exception("Should never happens");
        if ($c === 1 && isset($list[0]) && \is_string($list[0])) {

            if (! $this->groupLoader->isList($name))
                $list = $list[0];
        } else {

            foreach ($list as $k => &$item) {

                if (\is_array($item))
                    $this->doSimplifyText($item, $k);
            }
        }
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
                    throw new \Exception("Element '$name' cannot be a list; have:" . var_export($e, true));

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
        throw new \Exception(__FUNCTION__ . "To review (with all summary stuff)!");
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
                    $type = [];
                    $cdepth = $depth;

                    if (\is_array($val)) {
                        $nextToProcess[] = $val;

                        if (\array_is_list($val))
                            $c = \count($val);
                        else
                            $c = 1;
                    } else {
                        $c = 0;
                        $type[] = 'LEAF';
                    }

                    if ($c == 1)
                        $type[] = 'OBJECT';
                    elseif ($c > 1) {
                        $type[] = 'MULTIPLE';
                        $obj = $leaf = false;

                        foreach ($val as $sub) {

                            if (\is_array($sub)) {
                                if ($obj)
                                    continue;
                                $obj = true;
                                $type[] = 'OBJECT';
                            } elseif (! $leaf) {
                                $leaf = true;
                                $type[] = 'LEAF';
                            }
                            if ($leaf && $obj)
                                break;
                        }
                    }

                    if (\is_string($label)) {

                        if (! isset($summary[$label]))
                            $summary[$label] = [];

                        $stypes = &$summary[$label];

                        foreach ($type as $t) {
                            if (! \in_array($t, $stypes))
                                $stypes[] = $t;
                        }
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
            $ret["$prefix$reader->name"] = $reader->value;
        }
        return $ret;
    }

    private function mergeArrayContents(array &$theObject, string $name, $val)
    {
        $present = $theObject[$name] ?? null;

        if (null !== $present) {

            if (\is_array_list($present)) {

                if (\is_array_list($val)) {
                    $c = \count($val);

                    if ($c === 1)
                        $theObject[$name] = \array_merge($theObject[$name], $val);
                    else
                        $theObject[$name][] = $val;
                } else
                    $theObject[$name][] = $val;
            }
        } else
            $theObject[$name] = $val;
    }

    private function mergeNodes(array $obj): array
    {
        $ret = [];

        foreach ($obj as $subObj) {
            $name = \array_key_first($subObj);
            $val = $subObj[$name];
            $this->mergeArrayContents($ret, $name, $val);
        }
        return $ret;
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
        $isEmpty = $reader->isEmptyElement;
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
        }

        if ($isEmpty);
        elseif ($this->groupLoader->isText($name)) {
            $obj[][self::textKey] = $reader->readInnerXML();
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
                            $obj[][self::textKey] = \trim($reader->value);
                            $nbText ++;
                        }
                        break;
                    case XMLReader::ELEMENT:
                        {
                            $nbElements ++;
                            $subObj = [];

                            $php = $gout = [];
                            $cname = $reader->name;

                            $this->toPHP($reader, $php, $gout);

                            if (! empty($gout))
                                $obj[] = $gout;

                            $obj[][$cname] = $php;
                        }
                        break;
                    case XMLReader::END_ELEMENT:
                        {
                            if ($reader->name !== $name)
                                throw new \Exception("Error, waiting for end element: $name; has $reader->name=" . print_r($obj, true));
                        }
                        break 2;
                    default:
                        throw new \Exception("Cannot handle node $reader->name:$reader->nodeType");
                }
            }
        }

        if ($nbElements >= 1) {

            if ($nbText === 0)
                $obj = $this->mergeNodes($obj);

            if (! empty($attr)) {

                if ($nbText === 0) {
                    $obj = \array_merge($attr, $obj);
                } else {
                    \array_unshift($obj, $attr);
                }
            }
        } elseif ($nbText > 1) {
            throw new \Exception("Shoud never happens: more than one #text with no element " . print_r($obj, true));
        } elseif ($nbText === 1) {
            $k = self::textKey;
            $stringVal = \array_pop($obj)[$k];

            if ($this->groupLoader->isObject($name)) {
                $obj = $attr;
                $obj[self::textKey] = $stringVal;
            } elseif (! empty($attr))
                throw new \Exception("Text node cannot have any attribute: $name: " . print_r($attr, true));
            else
                $obj = $stringVal;
        } else
            $obj = $attr;

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
}
