<?php
namespace Data;

final class PrefixPartitioning extends AbstractPartitioning
{

    private array $partitionsPrefix;

    private bool $oneCollection;

    private bool $regexCheck;

    private function __construct(bool $oneCollection, bool $regexCheck, string $id, string $baseDir, array $partitionsPrefix, string $idPartitions)
    {
        parent::__construct($id, $baseDir, $idPartitions);
        $this->partitionsPrefix = $partitionsPrefix;
        $this->oneCollection = $oneCollection;
        $this->regexCheck = $regexCheck;
    }

    public function getAllPartitionsOf(\DataSet $ds): array
    {
        if ($this->regexCheck)
            return $this->getAllPartitionsOf_regexCheck($ds);
        else
            return $this->getAllPartitionsOf_noRegexCheck($ds);
    }

    private function getAllPartitionsOf_regexCheck(\DataSet $ds)
    {
        $ret = [];

        $cname = \MongoImport::getCollectionName($ds);

        foreach ($this->partitionsPrefix as $name => $prefix) {

            if ($this->oneCollection) {
                $partition = new PrefixPartition($ds, $cname, $name, $prefix);
                $partition->setRegexCheck($this->regexCheck);
                $lpartitioning = new LogicalPrefixPartitioning($partition, $cname, $name, [
                    $name => $prefix
                ]);
                $partition->setLogicalPartitioning($lpartitioning);
                $ret[] = $partition;
            } else {
                $ccname = "$cname.$name";
                $partition = new PrefixPartition($ds, $ccname, $name, $prefix);
                $partition->setRegexCheck($this->regexCheck);
                $ret[] = $partition;
            }
        }
        return $ret;
    }

    private function getAllPartitionsOf_noRegexCheck(\DataSet $ds)
    {
        $ret = [];
        $prefixes = \array_values($this->partitionsPrefix);
        $rulesId = $ds->rules();
        $rulesPath = $ds->rulesPath() . "/querying.txt";

        // Create partitions according to the rules
        if (\is_file($rulesPath)) {
            $rules = \Data\LabelReplacer::getRelabellings($rulesPath);
            $newPrefixes = [];

            while (null !== ($prefix = \array_pop($prefixes))) {
                $newPrefixes[$prefix] = $prefix;
                $parts = \explode('.', $prefix);
                $first = [];

                while (null !== ($part = \array_shift($parts))) {
                    $replacements = $rules[$part] ?? null;

                    if (null == $replacements)
                        $first[] = $part;
                    else {

                        foreach ($replacements as $r) {
                            $newPrefix = \implode('.', \array_merge($first, [
                                $r
                            ], $parts));

                            if (! \in_array($newPrefix, $newPrefixes))
                                $prefixes[] = $newPrefix;
                        }
                        break;
                    }
                }
            }
            $prefixes = $newPrefixes;
        }
        $cname = \MongoImport::getCollectionName($ds);

        foreach ($prefixes as $name => $prefix) {

            if ($this->oneCollection) {
                $partition = new PrefixPartition($ds, $cname, $name, $prefix);
                $partition->setRegexCheck($this->regexCheck);
                $lpartitioning = new LogicalPrefixPartitioning($partition, $cname, $name, [
                    $name => $prefix
                ]);
                $partition->setLogicalPartitioning($lpartitioning);
                $ret[] = $partition;
            } else {
                $ccname = "$cname.$name";
                $partition = new PrefixPartition($ds, $ccname, $name, $prefix);
                $partition->setRegexCheck($this->regexCheck);
                $ret[] = $partition;
            }
        }
        return $ret;
    }

    // ========================================================================
    public static function create(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(false, true, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }

    public static function oneCollection(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(true, true, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }

    public static function createLambda(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(false, false, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }

    public static function oneLambdaCollection(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(true, false, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }
}