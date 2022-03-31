<?php
namespace Data;

final class PrefixPartitioning implements IPartitioning
{

    private object $noPrefix;

    private array $partitionsPrefix;

    private string $id;

    private function __construct(string $id, array $partitionsPrefix)
    {
        $this->id = $id;
        $this->noPrefix = (object) null;
        $this->partitionsPrefix = $partitionsPrefix;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $ret = [];

        foreach ($this->partitionsPrefix as $name => $prefix)
            $ret[] = new class($ds, $name, $prefix, $this->noPrefix) implements IPartition {

                private object $noPrefix;

                private array $prefix;

                private string $id;

                private string $cname;

                function __construct(\DataSet $ds, string $id, string $prefix, object $noPrefix)
                {
                    $this->id = $id;
                    $this->noPrefix = $noPrefix;
                    $this->cname = \MongoImport::getCollectionName($ds) . ".$prefix";
                    $this->prefix = \explode('.', $prefix);
                }

                function getID(): string
                {
                    return $this->id;
                }

                function getCollectionName(): string
                {
                    return $this->cname;
                }

                function contains(array $data): bool
                {
                    $f = \array_follow($data, $this->prefix, $this->noPrefix);
                    return $f !== $this->noPrefix;
                }
            };

        return $ret;
    }

    function getID(): string
    {
        return $this->id;
    }

    public static function create(string $id, array $partitionsPrefix): IPartitioning
    {
        return new PrefixPartitioning($id, $partitionsPrefix);
    }
}