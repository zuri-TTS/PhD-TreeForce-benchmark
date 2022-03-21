<?php
namespace Data;

final class DataLocations
{

    function __construct()
    {
        throw new \Error();
    }

    public function getLocationFor(\DataSet $dataSet, string $locationId = ''): IDataLocation
    {
        if (empty($locationId))
            return new DataSetLocation($dataSet);

        $locs = $dataSet->getGroupLoader()->getDataLocationConfig();
        $loc = $locs[$locationId] ?? null;

        if (! isset($loc)) {
            $possible = \array_keys($locs);

            if (empty($locs))
                $moreInfos = ' none possible';
            else
                $moreInfos = ' possible value in [' . \implode('.', $possible) . ']';

            throw new \Exception("Invalid locationId: $locationId$moreInfos");
        }

        return new $loc['class']($dataSet, $loc['params'] ?? null);
    }
}