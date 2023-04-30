<?php
namespace DBImport;

abstract class AbstractDBImport implements IDBImport
{

    public function collectionExists(string $collection): bool
    {
        return $this->collectionsExists([
            $collection
        ]);
    }

    public function dropCollection(string $collection): void
    {
        $this->dropCollections([
            $collection
        ]);
    }

    public function importCollection(\DataSet $dataSet, string $collection): void
    {
        $this->importCollection($dataSet, [
            $collection
        ]);
    }

    public function importDataSet(\DataSet $dataSet): void
    {
        \DataSets::checkNotExists([
            $dataSet
        ]);
        $this->importCollections($dataSet, $dataSet->getCollections());
    }

    public function dropDataSet(\DataSet $dataSet): void
    {
        $this->dropDataSets([
            $dataSet
        ]);
    }

    public function dropDataSets(array $dataSets): void
    {
        foreach ($dataSets as $ds)
            $this->dropCollections($ds->getCollections());
    }

    /**
     *
     * @return int Number of failures (documents not imported)
     */
    protected abstract function _importJsonFile(string $jsonFile, string $collectionName): int;

    public function importCollections(\DataSet $dataSet, array $collections): void
    {
        $className = \get_called_class();
        $dsColls = $dataSet->getCollections();
        $collections = \array_unique($collections);
        $invalidColls = \array_diff($collections, $dsColls);

        if (! empty($invalidColls)) {
            $invalidColls = implode(',', $invalidColls);
            $dsColls = implode(',', $dsColls);
            throw new \Exception("[$className]$dataSet does not have collections [$invalidColls]; has [$dsColls]");
        }
        $ignoreCollections = \array_diff($dsColls, $collections);
        $ignoreCollections = \array_combine($ignoreCollections, \array_fill(0, \count($ignoreCollections), true));

        echo "\nImporting $dataSet\n";

        $partitions = $dataSet->getPartitions();
        $nbFails = [];
        $loading = \array_combine($collections, \array_fill(0, \count($collections), false));

        foreach ($partitions as $partition) {
            $collectionName = \Data\Partitions::getCollectionName($dataSet, $partition);

            if (isset($ignoreCollections[$collectionName]));
            elseif (! $loading[$collectionName] && self::collectionExists($collectionName))
            // echo "$collectionName: already exists\n";
            ;
            else {
                {
                    \wdPush($dataSet->path());

                    $jsonFile = "{$partition->getID()}.json";
                    echo "$jsonFile in collection: $collectionName\n";

                    if (! \is_file($jsonFile))
                        throw new \Exception("The file $jsonFile does not exists");

                    $lineBuff = "";
                    $fails = $this->_importJsonFile($jsonFile, $collectionName);
                    \wdPop();
                }
                if ($fails != 0)
                    $nbFails[$collectionName] = $fails;

                $loading[$collectionName] = true;
            }
        }

        if (empty($nbFails))
            echo "Success";
        else {
            foreach ($nbFails as $coll => $nb)
                $displayFail[] = "$coll:$nb";

            $displayFail = \implode(',', $displayFail);
            throw new \Exception("[$className]: import failed for $dataSet ($displayFail fails)");
        }
        echo "\n";
    }
}