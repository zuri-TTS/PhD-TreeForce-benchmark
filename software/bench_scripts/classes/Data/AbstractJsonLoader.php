<?php
namespace Data;

abstract class AbstractJsonLoader implements IJsonLoader
{

    protected array $dataSets;

    protected string $group;

    protected string $groupPath;

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

    protected function prepareDir(\DataSet $dataSet): string
    {
        $dataSetOutPath = $dataSet->path();

        if (! \is_dir($dataSetOutPath)) {
            \mkdir($dataSetOutPath, 0777, true);
        }
        return $dataSetOutPath;
    }
}