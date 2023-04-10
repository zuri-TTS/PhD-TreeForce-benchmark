<?php
namespace Test;

final class PrintJavaConfig extends AbstractTest
{

    private array $testConfig;

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        parent::__construct($ds, $cmdParser, ...$partitions);
        $this->testConfig = \makeConfig($this->dbImport, $this->ds, $partitions, $cmdParser);
    }

    public function execute()
    {
        foreach ($this->partitions as $p)
            echo "#<$this->ds/{$p->getID()}>\n";
        (new \Benchmark($this->testConfig))->printJavaConfig();
        echo "\n";
    }
}