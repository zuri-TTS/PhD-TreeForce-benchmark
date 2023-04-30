<?php

class XMLLoader extends \Data\AbstractJsonLoader implements \Data\IJsonLoader
{

    private array $stats = [
        'documents.nb' => 0,
        'edges.nb' => 0
    ];

    private array $pdata = [];

    private \Data\IXMLLoader $xmlLoader;

    public function __construct(array $dataSets, array $config, ?\Data\IXMLLoader $xmlLoader = null)
    {
        parent::__construct($dataSets, $config);
        $this->xmlLoader = $xmlLoader;

        foreach ($dataSets as $ds) {
            $rand = $this->xmlLoader->getLabelReplacerForDataSet($ds);

            if ($ds->isSimplified()) {

                if ($rand)
                    $call = fn ($doc) => $rand($this->doSimplifyObject($doc));
                else
                    $call = fn ($doc) => $this->doSimplifyObject($doc);
            } elseif ($rand)
                $call = $rand;
            else
                $call = fn ($doc) => $doc;

            $this->pdata[$ds->id()] = $call;
        }
    }

    public static function create(\Data\IXMLLoader $xmlLoader, array $dataSets)
    {
        return new XMLLoader($dataSets, [], $xmlLoader);
    }

    public function getPartitioning(string $name = ''): \Data\IPartitioning
    {
        return $this->xmlLoader->getPartitioning($name);
    }

    // ========================================================================
    private int $nb;

    protected function postProcessDocument(array &$doc, \DataSet $ds): void
    {
        $doc = $this->pdata[$ds->id()]($doc);
//         $this->stats['edges.nb'] += \Help\Arrays::jsonRecursiveCount($doc);
    }

    protected function lastProcessDocument(array &$doc, \DataSet $ds): void
    {
        $doc['_id'] = ++ $this->nb;
    }

    protected function getDocumentStream(string $dsgroupPath)
    {
        $this->nb = 0;
        $nbEdges = 0;

        \wdPush($dsgroupPath);
        $reader = $this->xmlLoader->getXMLReader();

        foreach ($this->read($reader) as $doc) {
            yield $doc;
        }
        $reader->close();
        \wdPop();
        $stats = [
            'documents.nb' => $this->nb
        ];
        \printPHPFile("$dsgroupPath/stats.php", $stats);
    }

    // ========================================================================
    public function cleanFiles(int $level = self::CLEAN_ALL)
    {
        foreach ($this->dataSets as $ds) {
            $basepath = $ds->groupPath();

            \wdPush($basepath);
            if ($level & self::CLEAN_BASE_FILES) {
                $this->xmlLoader->deleteXMLFile();
            }

            if ($level & self::CLEAN_JSON_FILES) {
                $dir = $ds->path();

                if (\is_dir($dir)) {
                    $files = \wdOp($dir, fn () => \Help\Files::globClean("*.json"));
                }
            }
            \wdPop();
        }
    }

    // ========================================================================
    private function reachUnwind(string $path): ?string
    {
        $ret = [];

        foreach ($this->xmlLoader->getUnwindConfig() as $u) {
            if (0 === \strpos($u, $path))
                $ret[] = $u;
        }
        return \count($ret) === 1 ? $ret[0] : null;
    }

    private function read(XMLReader $reader)
    {
        $stack = [
            0
        ];
        $pathstack = [];
        $path = [];

        while (null !== ($state = \array_pop($stack))) {

            if ($state === 0) {

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

                            $reader->read();
                            $unwind = explode('.', $u);
                            \array_push($stack, 0);
                            \array_push($stack, 10);
                            \array_push($pathstack, $path);
                            break 2;
                        case XMLReader::END_ELEMENT:
                            \array_pop($path);
                            break;
                        default:
                    }
                }
            } // unwind
            elseif ($state === 10) {
                $path = \array_pop($pathstack);
                $keyPattern = $unwind[\count($path)];

                if ('$' === $keyPattern)
                    \array_push($stack, 20);
                elseif ('*' === $keyPattern)
                    \array_push($stack, 30);
                else
                    throw new \ErrorException("Can't handle $keyPattern");
            } // unwindUndefined
            elseif ($state === 20) {

                while ($reader->next()) {
                    switch ($reader->nodeType) {
                        case XMLReader::TEXT:
                            break;
                        case XMLReader::ELEMENT:
                            $path[] = $reader->name;
                            $reader->read();
                            \array_push($stack, 20);
                            \array_push($stack, 10);
                            \array_push($pathstack, $path);
                            break 2;
                        case XMLReader::END_ELEMENT:
                            \array_pop($path);
                            break 2;
                        default:
                    }
                }
            } // unwindEach
            elseif ($state === 30) {

                while ($reader->next()) {
                    switch ($reader->nodeType) {
                        case XMLReader::TEXT:
                            break;
                        case XMLReader::ELEMENT:
                            $this->path = $path;
                            yield self::makeDocument($reader);
                            break;
                        case XMLReader::END_ELEMENT:
                            \array_pop($path);
                            break 2;
                        default:
                    }
                }
            } else
                throw new \ErrorException();
        }
    }

    private function makeDocument(XMLReader $reader): array
    {
        $destVal = $getOut = [];

        $name = $reader->name;
        $this->toPHP($reader, $destVal, $getOut);

        $data = self::array_path($this->path, [
            $name => $destVal
        ]);
        $this->doSimplifyText($data);
        return $data;
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

            if (! $this->xmlLoader->isList($name))
                $list = $list[0];
        } else {

            foreach ($list as $k => &$item) {

                if (\is_array($item))
                    $this->doSimplifyText($item, $k);
            }
        }
    }

    private function doSimplifyObject(array $doc): array
    {
        $this->doSimplifyObject_($doc);
        return $doc;
    }

    private function doSimplifyObject_(&$keys): void
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
                $this->doSimplifyObject_($se);

            if (! $this->xmlLoader->isList($name)) {

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

                if ($this->xmlLoader->getOut($name, $aname)) {
                    $getOut["$name$aname"] = $val;
                    unset($attr[$aname]);
                }
            }
        }

        if ($isEmpty);
        elseif ($this->xmlLoader->isText($name)) {
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

            if ($this->xmlLoader->isObject($name)) {
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

                if ($this->xmlLoader->isMultipliable($name)) {
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
