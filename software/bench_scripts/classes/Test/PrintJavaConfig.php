<?php
namespace Test;

final class PrintJavaConfig extends AbstractTest
{

    private array $testConfig;

    public function __construct(\DataSet $ds, \Data\IPartition $partition, CmdArgs $cmdParser)
    {
        parent::__construct($ds, $partition, $cmdParser);
        $parsed = $this->cmdParser->parsed();
        $this->testConfig = \makeConfig($this->ds, $partition, $parsed['args'], $parsed['javaProperties']);
    }

    public function execute()
    {
        echo "#<$this->collection/{$this->partition->getID()}>\n";
        (new \Benchmark($this->testConfig))->printJavaConfig();
        echo "\n";
    }
}