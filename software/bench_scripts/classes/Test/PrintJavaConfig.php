<?php
namespace Test;

final class PrintJavaConfig extends AbstractTest
{

    private array $testConfig;

    public function __construct(\DataSet $ds, $partitions, CmdArgs $cmdParser)
    {
        parent::__construct($ds, \Data\Partitions::noPartition(), $cmdParser);
        $parsed = $this->cmdParser->parsed();
        $this->testConfig = \makeConfig($this->ds, $partitions, $parsed['args'], $parsed['javaProperties']);
    }

    public function execute()
    {
        echo "#<$this->collection/{$this->partition->getID()}>\n";
        (new \Benchmark($this->testConfig))->printJavaConfig();
        echo "\n";
    }
}