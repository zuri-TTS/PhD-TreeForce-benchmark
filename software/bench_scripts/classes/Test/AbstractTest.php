<?php
namespace Test;

abstract class AbstractTest
{

    protected array $errors = [];

    protected \DataSet $ds;

    protected string $collection;

    protected \Data\IPartition $partition;

    protected CmdArgs $cmdParser;

    private \XMLLoader $xmlLoader;

    public abstract function execute();

    public function __construct(\DataSet $ds, \Data\IPartition $partition, CmdArgs $cmdParser)
    {
        $this->ds = $ds;
        $this->partition = $partition;
        $this->collection = $partition->getCollectionName();

        if (! empty($collection) && ! \in_array($collectionName, $ds->getCollections()))
            throw new \Exception("$ds does not have the collection $collectionName");

        $this->cmdParser = $cmdParser;
        $this->xmlLoader = \XMLLoader::of($ds);
    }

    public final function collectionExists(): bool
    {
        if (empty($this->collection))
            return true;

        return \MongoImport::collectionExists($this->collection);
    }

    public final function dropCollection(string $clean = "*.json"): void
    {
        if (empty($this->collection))
            return;
        \MongoImport::dropCollection($this->collection);

        if (! empty($clean))
            $this->xmlLoader->clean($clean);
    }

    public final function loadCollection(): void
    {
        if (empty($this->collection))
            return;
        $this->xmlLoader->convert();
        \MongoImport::importCollections($this->ds, $this->partition);
    }

    public final function reportErrors(?array $errors = null): void
    {
        $errors = $errors ?? $this->errors;

        if (empty($errors))
            return;

        \ob_start();
        echo "\n== Error reporting ==\n\n";

        foreach ($errors as $err) {
            echo "= {$err['dataset']}/{$err['collection']} =\n{$err['exception']->getMessage()}\n{$err['exception']->getTraceAsString()}\n\n";
        }
        \fwrite(STDERR, \ob_get_clean());
    }

    public final function getErrors(): array
    {
        return $this->errors;
    }
}