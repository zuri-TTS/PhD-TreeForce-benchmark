<?php
namespace Data;

final class GitHubLoader extends AbstractJsonLoader
{

    private const repo = "https://data.gharchive.org/";

    private \Args\ObjectArgs $oargs;

    private string $id;

    public string $conf_year = '*';

    public string $conf_month = '*';

    public string $conf_day = '*';

    public string $conf_hour = '*';

    public function __construct(array $dataSets, array $config)
    {
        parent::__construct($dataSets, $config);
        $oa = $this->oargs = (new \Args\ObjectArgs($this))-> //
        setPrefix('conf_')-> //
        mapKeyToProperty(fn ($k) => \str_replace('.', '_', $k));
        $oa->updateAndShift($config);
        $oa->checkEmpty($config);

        $this->id = "{$this->conf_year}_{$this->conf_month}_{$this->conf_day}_{$this->conf_hour}";
    }

    protected function postProcessDocument(array &$doc, \DataSet $ds): void
    {
//         $id = $doc['id'];
//         unset($doc['id']);
//         $doc['_id'] = (float) $id;
    }

    protected function getDocumentStream(string $dsgroupPath)
    {
        $this->generateBaseJson($dsgroupPath);

        $file = "$dsgroupPath/{$this->id}.json";
        $fp = \fopen($file, 'r');

        while (false !== ($line = \fgets($fp)))
            yield \json_decode($line, true);

        \fclose($fp);
    }

    public function cleanFiles(int $level = self::CLEAN_ALL)
    {
        foreach ($this->dataSets as $ds) {
            $basepath = $ds->groupPath();

            \wdPush($basepath);
            if ($level & self::CLEAN_BASE_FILES) {
                @\unlink("$this->id.json");
                @\unlink("$this->id-end.json");
            }

            if ($level & self::CLEAN_JSON_FILES) {
                \wdPush($ds->path());
                \Help\Files::globClean('*.json');
                \wdPop();
            }
            \wdPop();
        }
    }

    private function generateBaseJson(string $dsgroupPath): void
    {
        \wdPush($dsgroupPath);
        $id = $this->id;
        $endfile = "$id-end.json";

        if (! \is_file($endfile)) {
            echo "Get files for periods: $id\n";
            $years = $this->getTimeRange($this->conf_year, \range(2015, 2022));
            $months = $this->getTimeRange($this->conf_month, \range(1, 12));
            $days = $this->getTimeRange($this->conf_day, \range(1, 31));
            $hours = $this->getTimeRange($this->conf_hour, \range(0, 23));

            $jsonfp = \fopen("$id.json", 'w');

            foreach ($years as $y)
                foreach ($months as $m)
                    foreach ($days as $d)
                        foreach ($hours as $h)
                            $this->downloadDayFile($jsonfp, $y, $m, $d, $h);

            \fclose($jsonfp);
            \touch($endfile);
        }
        \wdPop();
    }

    private function getTimeRange(?string $val, array $all): array
    {
        if (! isset($val) || $val === '' || $val === '*')
            return $all;
        if (\preg_match('/(\d+)(?:-(\d+))?/', $val, $match)) {
            $c = count($match);

            if ($c == 3)
                return \range($match[1], $match[2]);
            else
                return (array) (int) $match[1];
        }
        throw new \Exception("Can't handle time range `$val`");
    }

    private function downloadDayFile($jsonfp, int $year, int $month, int $day, int $hour): void
    {
        $repo = self::repo;
        $m = \sprintf("%02d", $month);
        $d = \sprintf("%02d", $day);

        $fname = "$year-$m-$d-$hour";
        $url = "$repo$fname.json.gz";

        $tmpfp = \fopen("compress.zlib://$url", 'r');
        echo "Get $url\n";
        \stream_copy_to_stream($tmpfp, $jsonfp);
    }

    private function cpyFile(string $from, string $to): void
    {
        $to = $to ?? $from;

        echo "Copying $from into $to\n";

        if (! \copy($from, $to)) {
            \unlink($to);
            throw new \Exception("An error occured");
        }
    }
}
