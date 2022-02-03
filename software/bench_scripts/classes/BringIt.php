<?php

final class BringIt
{

    private string $outputPath, $realPath;

    private array $scanned = [];

    public function __construct(string $outDir)
    {
        if (! is_dir($outDir))
            throw new \Error("Dir '$outDir' does not exists");

        $this->outputPath = $outDir;
        $this->realPath = \realpath($outDir);
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

        $scanned = scandirNoPoints($this->realPath);

        foreach ($scanned as $dirName) {
            $path = "$this->realPath/$dirName";

            if (is_dir($path)) {
                $this->scanned[] = $dirName;
            }
        }
        $dels = array_diff($lastScan, $this->scanned);

        foreach ($this->scanned as $dir) {
            $pathLink = "$this->realPath/" . $this->bringFileName($dir);
            $ref = "$dir/all_time.png";

            if (! is_file($pathLink) && is_file("$this->realPath/$ref")) {
                @symlink("./$ref", $pathLink);
            }
        }

        foreach ($dels as $del) {
            $pathLink = "$this->realPath/" . $this->bringFileName($del);

            if (is_link($pathLink)) {
                echo "Drop $del\n";
                unlink($pathLink);
            }
        }
    }
}
