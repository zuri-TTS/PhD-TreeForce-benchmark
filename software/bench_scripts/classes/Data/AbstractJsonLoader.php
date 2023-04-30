<?php
namespace Data;

abstract class AbstractJsonLoader implements IJsonLoader
{

    protected array $dataSets;

    protected string $group;

    protected string $groupPath;

    protected bool $isLogical;

    public function __construct(array $dataSets, array $config)
    {
        $groups = [];

        foreach ($dataSets as $dataSet)
            $groups[$group = $dataSet->group()] = null;

        if (\count($groups) > 1)
            throw new \Exception("Multiple groups given: " . \implode(',', $groups));

        $basePath = \getBenchmarkBasePath();
        $this->group = $group;
        $this->groupPath = \DataSets::getGroupPath($group);
        $this->dataSets = $dataSets;
    }

    private function prepareDir(\DataSet $dataSet): string
    {
        $dataSetOutPath = $dataSet->path();

        if (! \is_dir($dataSetOutPath)) {
            \mkdir($dataSetOutPath, 0777, true);
        }
        return $dataSetOutPath;
    }

    protected abstract function getDocumentStream(string $dsgroupPath);

    protected function postProcessDocument(array &$document, \DataSet $ds): void
    {}

    protected function lastProcessDocument(array &$document, \DataSet $ds): void
    {}

    public function getPartitioningBuilderFor(\DataSet $ds): IPartitioningBuilder
    {
        $group = $ds->group();
        $pname = $ds->group_partitioning();

        if (\Help\Strings::empty($pname))
            return SimplePartitioning::builder($ds);

        // Lambda partitioning
        if (\preg_match('/^(L)?L(\d+)$/', $pname, $matches)) {
            $isLogic = $matches[1] === 'L';
            $depth = $matches[2];
            return LambdaPartitioning::getBuilder($depth, $ds, $isLogic);
        }
        throw new \ErrorException("Can't handle dataset $ds");
    }

    public function generateJson(): void
    {
        $fpool = new \Help\FilePool(100);
        $builders = [];
        $datasets = $this->dataSets;

        foreach ($datasets as $k => $ds) {
            $outPath = $this->prepareDir($ds);
            \wdPush($outPath);

            if (is_file('end.json'))
                unset($datasets[$k]);
            else {
                \Help\Files::globClean('*.json');
                $builders[$k] = $this->getPartitioningBuilderFor($ds);
            }
            \wdPop();
        }

        if (empty($datasets))
            return;

        foreach ($this->getDocumentStream($this->groupPath) as $doc) {

            foreach ($datasets as $k => $ds) {
                $pb = $builders[$k];

                $dsdoc = $doc;
                $this->postProcessDocument($dsdoc, $ds);
                $partition = $pb->getPartitionFor($dsdoc, $pb);

                // TODO: dynamic pid key according to the cli parameters
                $dsdoc['pid'] = $partition->getPID();
                $this->lastProcessDocument($dsdoc, $ds);
                $file = $partition->getId();

                if (\Help\Strings::empty($file))
                    throw new \ErrorException("Empty partition id for $ds");

                $f = $fpool->get($ds->path() . '/' . $file);
                $f->fwrite(\json_encode($dsdoc));
                $f->fwrite("\n");
            }
        }
        $fpool->clean();
        foreach ($datasets as $k => $ds) {
            $builders[$k]->save();
            \touch($ds->path() . '/end.json');
        }
    }
}