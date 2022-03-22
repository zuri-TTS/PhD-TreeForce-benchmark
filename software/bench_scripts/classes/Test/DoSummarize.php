<?php
namespace Test;

final class DoSummarize extends AbstractTest
{

    private OneTest $doIt;

    private string $summaryType;

    public function __construct(\DataSet $ds, string $collection, CmdArgs $cmdParser)
    {
        parent::__construct($ds, $collection, $cmdParser);

        $args = $cmdParser->parsed()['args'];

        if (($cmd = $args['cmd']) !== 'summarize')
            throw new \Exception(__CLASS__ . " cannot handle cmd=$cmd");

        $this->summaryType = $args['summary'];
    }

    public static function summarize(\DataSet $ds, string $collection, string $summaryType): void
    {
        $summArgs = [
            $ds->id(),
            'cmd' => 'summarize',
            'skip-existing' => true,
            'generate-dataset' => false,
            'clean-db' => false,
            'summary' => $summaryType,
            'output' => \sys_get_temp_dir(),
            'plot' => false,
            'doonce' => true,
            'forget-results' => true
        ];
        $doItParser = CmdArgs::default();
        $doItParser->parse($summArgs);
        $doIt = new OneTest($ds, $collection, $doItParser);

        $summaryPath = $doIt->getTestConfig()['summary'];
        $summaryName = \basename($summaryPath);

        echo "\nSummarizing $summaryName\n";

        if (\is_file($summaryPath)) {
            echo "Already exists\n";
            return;
        }
        \XMLLoader::of($ds)->convert();
        \MongoImport::importCollections($ds, $collection);
        $doIt->execute();
        \clearstatcache();
    }

    public function execute()
    {
        self::summarize($this->ds, $this->collection, $this->summaryType);
    }
}