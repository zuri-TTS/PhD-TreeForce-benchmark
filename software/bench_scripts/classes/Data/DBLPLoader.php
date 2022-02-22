<?php
namespace Data;

final class DBLPLoader implements ILoader
{

    private const repo = "https://dblp.org/xml/release/";

    private ?\XMLReader $xmlReader = null;

    private string $group;

    private \Args\ObjectArgs $oargs;

    public int $conf_seed;

    public string $conf_xml;

    public string $conf_dtd;

    public function __construct(string $group, array $config)
    {
        $this->group = $group;

        $oa = $this->oargs = (new \Args\ObjectArgs($this))-> //
        setPrefix('conf_')-> //
        mapKeyToProperty(fn ($k) => \str_replace('.', '_', $k));
        $oa->updateAndShift($config);
        $oa->checkEmpty($config);
    }

    public function __destruct()
    {
        $this->closeXMLReader();
    }

    private const unwind = [
        'dblp.*'
    ];

    public function getUnwindConfig(): array
    {
        return self::unwind;
    }

    private const doNotSimplify = [
        'author',
        'editor',
        'title',
        'booktitle',
        'pages',
        'year',
        'address',
        'journal',
        'volume',
        'number',
        'month',
        'url',
        'ee',
        'cdrom',
        'cite',
        'publisher',
        'note',
        'crossref',
        'isbn',
        'series',
        'school',
        'chapter',
        'publnr',
        'sub',
        'sup',
        'i',
        'tt',
        'ref'
    ];

    public function getDoNotSimplifyConfig(): array
    {
        return self::doNotSimplify;
    }

    public function deleteXMLFile(): bool
    {
        $ret = false;

        if (! $this->_deleteXMLFile("$this->conf_xml.xml"))
            $ret = false;

        if (! $this->_deleteXMLFile("$this->conf_dtd.dtd"))
            $ret = false;

        return true;
    }

    private function _deleteXMLFile(string $file): bool
    {
        if (! \is_file($file))
            return true;

        return \unlink($file);
    }

    private function closeXMLReader()
    {
        if (null !== $this->xmlReader) {
            $this->xmlReader->close();
            $this->xmlReader = null;
        }
    }

    public function getXMLReader(): \XMLReader
    {
        \wdPush(\DataSets::getGroupPath($this->group));
        $xmlPath = "$this->conf_xml.xml";

        $this->downloadFile("compress.zlib://" . self::repo . "$this->conf_xml.xml.gz", $xmlPath);
        $this->downloadFile(self::repo . "$this->conf_dtd.dtd", "$this->conf_dtd.dtd");
        $reader = \XMLReader::open($xmlPath);
        $reader->setParserProperty(\XMLReader::LOADDTD, true);
        $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, true);
        \wdPop();

        return $reader;
    }

    private function downloadFile(string $from, string $to): void
    {
        $to = $to ?? $from;

        if (! \is_file($to)) {
            echo "Downloading $from into $to\n";

            if (! \copy($from, $to)) {
                \unlink($to);
                throw new \Exception("An error occured");
            }
        }
    }

    public function getLabelReplacerForDataSet(\DataSet $dataSet): callable
    {
        return LabelReplacer::getReplacerForDataSet($dataSet, $this->conf_seed);
    }
}
