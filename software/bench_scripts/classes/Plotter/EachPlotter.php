<?php
namespace Plotter;

final class EachPlotter implements IGroupPlotter
{

    private const template = __DIR__ . '/templates/each.plot.php';

    private \Plot $plot;

    private string $dirName;

    private string $queryName;

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

    public function plot(string $dirName, array $queriesName): void
    {
        \wdPush($dirName);
        $plot = $this->plot;
        $this->dirName = $dirName;
        $this->queryName = $queriesName[0];
        $this->data = $this->plot->getData()["$dirName/{$this->queryName}"];

        $this->writeDat();

        $contents = \get_include_contents(self::template, [
            'PLOT' => $this->plot,
            'PLOTTER' => $this
        ]);

        $plotFileName = $this->getFileName('.plot');
        if (! \is_file($plotFileName))
            \file_put_contents($plotFileName, $contents);

        $outFileName = $this->getFileName('.png');
        if (! \is_file($outFileName)) {
            $cmd = "gnuplot '$plotFileName' > '$outFileName'";
            echo "plotting $outFileName\n";
            system($cmd);
        }
        \wdPop();
    }

    // ========================================================================
    public function getFileName(string $suffix = ''): string
    {
        return \Plot::plotterFileName($this, $this->queryName, $suffix);
    }

    public function getData(): array
    {
        return $this->data;
    }

    // ========================================================================
    private function echoDat(array $data)
    {
        echo "measure real cpu\n";

        foreach (\array_filter($data, '\Measures::isTimeMeasure') as $group => $measure) {
            $measure = \Measures::decodeTimeMeasure($measure);
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

        if (\is_file($file))
            return;

        echo "Writing $file\n";
        $content = \get_ob(fn () => $this->echoDat($this->data));

        \file_put_contents($file, $content);
    }
}
