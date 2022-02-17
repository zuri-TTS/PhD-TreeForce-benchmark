<?php

final class Check
{

    private array $paths;

    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    // ========================================================================
    private static function groupBy_data_query_rules(array &$groups, \SplFileInfo $fileInfo): void
    {
        $query = \basename($fileInfo, '.csv');
        $bname = \basename(\dirname($fileInfo));
        \preg_match("#^\[(.+)\]\[(.+)\]#U", $bname, $matches);
        $dataGroup = $matches[1];
        $rules = $matches[2];

        $groups[$dataGroup][$query][$rules] = $fileInfo->getRealPath();
    }

    private static function stats_data_rules_query(array &$groups, \SplFileInfo $fileInfo): void
    {
        $query = \basename($fileInfo, '.csv');
        $bname = \basename(\dirname($fileInfo));

        if (false === \strpos($bname, '[stats]'))
            return;

        \preg_match("#^\[(.+)\]\[(.+)\]#U", $bname, $matches);
        $dataGroup = $matches[1];
        $rules = $matches[2];

        $groups[$dataGroup][$rules][$query][] = $fileInfo->getRealPath();
    }

    private static function getCSVGroups(array $paths, callable $fgroup): array
    {
        $csvGroups = [];

        foreach ($paths as $outPath) {

            if (is_dir($outPath)) {
                $dir = new RecursiveDirectoryIterator($outPath);
                $ite = new RecursiveIteratorIterator($dir);
                $reg = new RegexIterator($ite, "#/[^@/][^/]*\.csv$#");

                foreach ($reg as $fileInfo)
                    $fgroup($csvGroups, $fileInfo);
            } else {
                fputs(STDERR, "Can't handle '$outPath'!\n");
                continue;
            }
        }
        return $csvGroups;
    }

    // ========================================================================
    public function checkNbAnswers()
    {
        $csvGroups = self::getCSVGroups($this->paths, 'Check::groupBy_data_query_rules');
        \uksort($csvGroups, 'strnatcasecmp');

        foreach ($csvGroups as &$queriesFiles) {
            \uksort($queriesFiles, 'strnatcasecmp');

            foreach ($queriesFiles as &$f)
                \uksort($f, 'strnatcasecmp');
        }

        foreach ($csvGroups as $group => $queriesFiles) {
            echo "[$group]\n";
            $dataSet = DataSets::fromId("$group/(1)original");
            $nbDocuments = $dataSet->stats()['documents.nb'];

            echo "documents.nb: $nbDocuments\n";

            foreach ($queriesFiles as $query => $files) {
                echo "[$group.$query]\n";
                $ok = true;
                $answers = null;
                $cache = [];

                foreach ($files as $file) {
                    $data = CSVReader::read($file);
                    $ans = (int) $data['answers']['total'];
                    $cache[$file] = $ans;

                    if (null === $answers)
                        $answers = $ans;
                    elseif ($answers !== $ans)
                        $ok = false;
                }

                if ($ok) {
                    $selectivity = (double) $ans / $nbDocuments;
                    $selectivity = \sprintf("%f", $selectivity);

                    echo //
                    "answers.nb: $answers\n", //
                    "selectivity: $selectivity\n";
                } else {
                    echo "Failed\n";

                    foreach ($cache as $f => $ans)
                        echo "$f: $ans\n";

                    echo "\n";
                }
            }
            echo "\n";
        }
    }

    public function checkStats()
    {
        $csvGroups = $this->getCSVGroups($this->paths, 'Check::stats_data_rules_query');

        foreach ($csvGroups as &$queriesFiles) {
            \uksort($queriesFiles, 'strnatcasecmp');

            foreach ($queriesFiles as &$f)
                \uksort($f, 'strnatcasecmp');
        }

        foreach ($csvGroups as $group => $rules_queries) {
            foreach ($rules_queries as $rules => $queriesGroup) {
                $ok = true;

                foreach ($queriesGroup as $query => $queries) {
                    $refStats = null;
                    $stats = [];

                    foreach ($queries as $file) {
                        $csvData = CSVReader::read($file);

                        $stats[] = $csvData['stats'];

                        if (empty($refStats))
                            $refStats = $csvData['stats'];
                        elseif ($ok && $refStats !== $csvData['stats'])
                            $ok = false;
                    }

                    echo "[$group/$rules/$query]\n";

                    if ($ok) {
                        $percent = \round((100.0 * $csvData['stats']['queries.empty.nb']) / $csvData['stats']['queries.nb'], 1);
                        echo "queries.nb: {$csvData['stats']['queries.nb']}\n", //
                        "queries.empty.percent: $percent%\n", //
                        "queries.empty.nb: {$csvData['stats']['queries.empty.nb']}\n", //
                        "queries.non-empty.nb: {$csvData['stats']['queries.nonempty.nb']}\n"; //
                    } else {
                        echo "Error! differents stats:\n";
                        foreach ($stats as $k => $v)
                            echo "<$k>=", \var_export($v, true), "\n";
                    }
                    echo "\n";
                }
            }
        }
        echo "\n";
    }
}