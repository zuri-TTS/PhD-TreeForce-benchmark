<?php
namespace Test;

abstract class AbstractTest
{

    protected array $errors = [];

    protected \DataSet $ds;

    protected string $collection;

    protected CmdArgs $cmdParser;

    private \XMLLoader $xmlLoader;

    public abstract function execute();

    public function __construct(\DataSet $ds, string $collectionName, CmdArgs $cmdParser)
    {
        $this->ds = $ds;
        $this->collection = $collectionName;

        if (! \in_array($collectionName, $ds->getCollections()))
            throw new \Exception("$ds does not have the collection $collectionName");

        $this->cmdParser = $cmdParser;
        $this->xmlLoader = \XMLLoader::of($ds);
    }

    public final function collectionExists(): bool
    {
        return \MongoImport::collectionExists($this->collection);
    }

    public final function dropCollection(): void
    {
        \MongoImport::dropCollection($this->collection);
    }

    public final function loadCollection(): void
    {
        $this->xmlLoader->convert();
        \MongoImport::importCollections($this->ds, $this->collection);
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