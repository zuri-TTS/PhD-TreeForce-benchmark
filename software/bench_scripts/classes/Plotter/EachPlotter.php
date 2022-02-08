<?php
namespace Plotter;

final class EachPlotter implements IPlotter
{

    private const template = __DIR__ . '/templates/each.plot.php';

    private \Plot $plot;

    private string $csvPath;

    private string $fileName;

    private string $basePath;

    private array $data;

    public function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getId(): string
    {
        return 'time';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_EACH;
    }

    public function plot(array $csvPaths): void
    {
        $plot = $this->plot;
        $this->csvPath = $csvPath = $csvPaths[0];
        $this->fileName = \baseName($csvPath, ".csv");
        $this->basePath = \dirname($csvPath);
        $this->data = $this->plot->getData()[$this->csvPath];

        $this->writeDat();

        $contents = \get_include_contents(self::template, [
            'PLOT' => $this->plot,
            'PLOTTER' => $this
        ]);
        $plotFileName = $this->getFileName('.plot');
        \file_put_contents($plotFileName, $contents);

        $outFileName = $this->getFileName('.png');
        $cmd = "gnuplot '$plotFileName' > '$outFileName'";
        echo "plotting $outFileName\n";

        system($cmd);
    }

    // ========================================================================
    public function getFileName(string $suffix = ''): string
    {
        return \Plot::plotterFileName($this, $this->csvPath, $suffix);
    }

    public function getData(): array
    {
        return $this->data;
    }

    // ========================================================================
    private function echoDat(array $data)
    {
        echo "measure real cpu\n";

        foreach (\array_filter($data, 'Plot::isTimeMeasure') as $group => $measure) {
            $r = max(0, $measure['r']);
            $c = max(0, $measure['c']);
            $realRemain = max(0, $r - $c);
            $group = $this->plot->gnuplotSpecialChars($group);
            echo "\"$group\" $r $c\n";
        }
    }

    private function writeDat()
    {
        $file = $this->getFileName('.dat');
        echo "Writing $file\n";
        $content = \get_ob(fn () => $this->echoDat($this->data));

        \file_put_contents($file, $content);
    }
}
