<?php
namespace Plotter;

final class GroupPlotter implements IGroupPlotter
{

    private const template = __DIR__ . '/templates/group.plot.php';

    private \Plot $plot;

    private array $data;

    private string $dirName;

    private array $queriesName;

    public function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getId(): string
    {
        return 'group';
    }

    public function getData()
    {
        return $this->data;
    }

    public function getDirName()
    {
        return $this->dirName;
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_GROUP;
    }

    public function getOutFileName(string $suffix = ""): string
    {
        return "all_time$suffix";
    }

    public function plot(string $dirName, array $queriesName): void
    {
        \wdPush($dirName);
        $this->dirName = $dirName;
        $this->queriesName = $queriesName;
        $this->data = \array_combine($queriesName, $this->plot->getData());
        $contents = \get_include_contents(self::template, [
            'PLOT' => $this->plot,
            'PLOTTER' => $this
        ]);
        $plotFilePath = $this->getOutFileName('.plot');
        \file_put_contents($plotFilePath, $contents);

        $outFilePath = $this->getOutFileName('.png');
        $cmd = "gnuplot '$plotFilePath' > '$outFilePath'";
        echo "writing $outFilePath\n";

        system($cmd);
        \wdPop();
    }

    // ========================================================================
    public static function extractFirstNb(string $s): int
    {
        \preg_match("#\((\d+)\)#", $s, $matches);
        return $matches[1] ?? 0;
    }
}
