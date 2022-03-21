<?php
namespace Data;

final class DataSetLocation implements IDataLocation
{

    private \DataSet $ds;

    private array $fp = [];

    public function __construct(\DataSet $ds)
    {
        $this->ds = $ds;
    }

    public function __destruct()
    {
        foreach ($this->fp as $fp)
            \fclose($fp);
    }

    private function fp(array $unwindPath)
    {
        $k = \implode('.', $unwindPath);

        if (isset($this->fp[$k]))
            return $this->fp[$k];

        return $this->fp[$k] = \fopen("{$this->ds->path()}/$k.json", "w");
    }

    public function writeData(array $unwindPath, array $data): void
    {
        \fwrite($this->fp($unwindPath), \json_encode($data) . "\n");
    }

    public function collectionJsonFiles(): array
    {
        $path = $this->ds->path();

        if (! \is_dir($path))
            return [];

        wdPush($path);

        $jsonFiles = \glob("*.json");
        $ret = [
            \MongoImport::getCollectionName($this->ds) => $jsonFiles
        ];

        wdPop();
        return $ret;
    }

    public function getDBCollections(): array
    {
        return (array)\MongoImport::getCollectionName($this->ds);
    }
}