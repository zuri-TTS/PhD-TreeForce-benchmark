<?php
namespace Data;

final class PrefixLocation implements IDataLocation
{

    private \DataSet $ds;

    private array $prefixes;

    private array $fp = [];

    public function __construct(\DataSet $ds, array $prefixes)
    {
        $this->ds = $ds;
        $this->prefixes = $prefixes;
    }

    public function __destruct()
    {
        foreach ($this->fp as $fp)
            \fclose($fp);
    }

    private function getValidPrefixes(array $dataPath): array
    {
        $ret = [];
        $dataPref_s = \implode('.', $dataPath);

        foreach ($this->prefixes as $pref => $coll) {

            if (\str_starts_with($dataPref_s, $pref))
                $ret[] = $pref;
        }
        return $ret;
    }

    private function fp(array $data)
    {
        $prefixes = [];
        $me = $this;

        \array_walk_branches($data, function ($path) use (&$prefixes, $me) {
            $prefixes = \array_unique(\array_merge($prefixes, $me->getValidPrefixes($path)));
        });
        $c = count($prefixes);

        if ($c === 0)
            throw new \Exception("No prefix set for the data: " . print_r($data, true));
        if ($c > 1) {
            \sort($prefixes);
            $prefixes = [
                \array_pop($prefixes)
            ];
        }
        $k = \array_pop($prefixes);

        if (isset($this->fp[$k]))
            return $this->fp[$k];

        return $this->fp[$k] = \fopen("{$this->ds->path()}/$k.json", "w");
    }

    public function writeData(array $unwindPath, array $data): void
    {
        \fwrite($this->fp($data), \json_encode($data) . "\n");
    }

    public function collectionJsonFiles(): array
    {
        wdPush($this->ds->path());

        $jsonFiles = \glob("*.json");
        $ret = [];

        foreach ($jsonFiles as $json) {
            if ($json === 'end.json')
                continue;

            $pref = \basename($json, '.json');
            $ret[\MongoImport::getCollectionName($this->ds) . '.' . $this->prefixes[$pref]] = [
                $json
            ];
        }
        wdPop();
        return $ret;
    }

    public function getDBCollections(): array
    {
        $cpref = \MongoImport::getCollectionName($this->ds);

        foreach ($this->prefixes as $pref)
            $ret[] = "$cpref.$pref";

        return $ret;
    }
}