<?php
namespace DBImport;

final class ArangoDBImport extends AbstractDBImport
{

    private const CMDPARAMS = [
        'server.database' => 'treeforce',
        'server.username' => 'root',
        'server.password' => '',
        'server.endpoint' => null
    ];

    function __construct()
    {
        $this->ensureDatabaseExists();
    }

    public function collectionsExists(array $collections): bool
    {
        $collections = $this->escapeCollectionsName($collections);
        return empty(\array_diff($collections, self::getCollections()));
    }

    public function createIndex($collection, string $indexName, int $order = 1): void
    {
        throw new \Exception("createIndex to be implemented");
    }

    public function dropCollections(array $collections): void
    {
        $collections = $this->escapeCollectionsName($collections);

        if (null !== $this->collections_cache)
            $delCache = function (array $collections) {
                \array_delete($this->collections_cache, ...$collections);
            };
        else
            $delCache = function () {};

        echo "\nDeleting ArangoDB collections:\n";

        if (empty($collections)) {
            echo "Nothing to drop\n";
            return;
        }

        $scriptColls = \implode(",\n", \array_map(fn ($c) => "\"$c\"", $collections));
        $script = <<<EOD
        collections = [
        $scriptColls
        ];
        ret = [];
        all = [];

        for(coll of db._collections())
            all.push(coll.name());

        for(coll of collections)
            db._drop(coll)
        
        all = [];

        for(coll of db._collections())
            all.push(coll.name());

        for(coll of collections)
            ret.push(!all.includes(coll));

        print(ret);
        EOD;

        // $script = \escapeshellarg($script);
        $r = $this->cmdScript($script);
        \extract($r);

        if (0 === $exitCode) {
            $res = \json_decode($output);

            if (null === $res) {
                \fwrite(STDERR, "Cannot decode as json data:<<<\n'$output'\n>>>\nErrors: $err\n\n");
                $res = [];
            }
            $deleted = [];

            foreach ($collections as $coll) {
                $valid = \array_shift($res);
                $del = $valid ? 'Success' : 'Failure';
                echo "$coll: $del\n";

                if ($valid)
                    $deleted[] = $coll;
            }
            $delCache($deleted);
        } else {
            throw new \Exception("Error on command: `$script`\nexit code: $exitCode\n");
        }
    }

    protected function _importJsonFile(string $jsonFile, string $collectionName): int
    {
        $collectionName = $this->escapeCollectionName($collectionName);
        // Get only the 4 last lines
        $filterLine = fn ($l) => ! empty(\trim($l));
        $output = \lastLineStreamBuffer(4, $outLines, $filterLine);
        $output = function ($s) use ($output) {
            echo $s;
            $output($s);
        };

        $params = self::CMDPARAMS;
        $params['collection'] = $collectionName;
        $cmdParams = $this->getCmdParams($params);
        $cmd = "cat $jsonFile";
        $cmd .= '| sed -E "s/\"_id\":([0-9]+)/\"_key\":\"\1\"/" | ';
        $cmd .= "arangoimport --file - --progress --overwrite --type jsonl --create-collection $cmdParams";
        $r = \simpleExec($cmd, $output, $err);
        {
            $errorskv = explode(":", $outLines[2]);
            $nbFails = (int) ($errorskv[1] ?? - 1);
        }

        if (null !== $this->collections_cache)
            $this->collections_cache[] = $collectionName;

        return $nbFails;
    }

    // ========================================================================
    private ?array $collections_cache = null;

    private ?array $collections_stats_cache = null;

    private function escapeCollectionsName(array $cnames): array
    {
        return \array_map([
            $this,
            'escapeCollectionName'
        ], $cnames);
    }

    private function escapeCollectionName(string $cname): string
    {
        return 'tf_' . \strtr($cname, '[]()', '----');
    }

    private function ensureDatabaseExists()
    {
        $params = self::CMDPARAMS;
        $dbname = $params['server.database'];
        unset($params['server.database']);

        $script = <<<EOD
        db._createDatabase("$dbname")
        EOD;
        $this->cmdScript($script, $params);
    }

    private function cmdScript($script, array $params = null, string $moreParams = '')
    {
        if (null === $params)
            $params = self::CMDPARAMS;

        $cmdParams = $this->getCmdParams($params);
        $script = \escapeshellarg($script);
        $cmd = "arangosh $cmdParams $moreParams --javascript.execute-string $script --quiet\n";
        $code = \simpleExec($cmd, $output, $err);
        return [
            'exitCode' => $code,
            'output' => $output,
            'err' => $err
        ];
    }

    private function getCollections(bool $forceCheck = false): array
    {
        if (! $forceCheck && $this->collections_cache !== null)
            return $this->collections_cache;

        $script = <<<EOD
        for(var coll of db._collections()){
            print(coll.name())
        }
        EOD;
        $r = $this->cmdScript($script);
        extract($r);

        if (empty($output))
            return [];

        return $this->collections_cache = explode("\n", $output);
    }

    private function getCmdParams(array $params): string
    {
        $ret = "";

        foreach ($params as $k => $v) {

            if (null === $v)
                continue;

            $v = \escapeshellarg($v);
            $ret .= " --$k $v";
        }
        return $ret;
    }
}