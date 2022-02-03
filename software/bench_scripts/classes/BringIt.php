<?php

final class BringIt
{

    private string $outputPath;

    private array $scanned = [];

    public function __construct(string $outDir)
    {
        $this->outputPath = getBenchmarkBasePath() . "/$outDir";
    }

    private function bringFileName(string $dirName)
    {
        return "at$dirName.png";
    }

    public function scan()
    {
        clearstatcache();
        $lastScan = $this->scanned;
        $this->scanned = [];

        $scanned = scandirNoPoints($this->outputPath);

        foreach ($scanned as $dirName) {
            $path = "$this->outputPath/$dirName";

            if (is_dir($path)) {
                $this->scanned[] = $dirName;
            }
        }
        $dels = array_diff($lastScan, $this->scanned);

        foreach ($this->scanned as $dir) {
            $pathLink = "$this->outputPath/" . $this->bringFileName($dir);
            $ref = "$this->outputPath/$dir/all_time.png";

            if (! is_file($pathLink) && is_file($ref)) {
                @symlink($ref, $pathLink);
            }
        }

        foreach ($dels as $del) {
            $pathLink = "$this->outputPath/" . $this->bringFileName($del);

            if (is_link($pathLink)) {
                echo "Drop $del\n";
                unlink($pathLink);
            }
        }
    }
}
