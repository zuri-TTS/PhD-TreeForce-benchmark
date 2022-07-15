<?php
namespace Test;

final class PrintJavaConfig extends AbstractTest
{

    private array $testConfig;

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        parent::__construct($ds, $cmdParser, ...$partitions);
        $parsed = $this->cmdParser->parsed();
        $this->testConfig = \makeConfig($this->ds, $partitions[0], $parsed['args'], $parsed['javaProperties']);
    }

    public function execute()
    {
        echo "#<$this->ds/{$this->partitions[0]->getID()}>\n";
        (new \Benchmark($this->testConfig))->printJavaConfig();
        echo "\n";
    }
}