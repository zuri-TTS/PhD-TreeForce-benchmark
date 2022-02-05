<?php
namespace Plotter;

final class GroupPlotter implements IPlotter
{

    private const template = __DIR__ . '/templates/group.plot.php';

    private \Plot $plot;

    private array $csvPaths;

    private string $basePath;

    private array $data;
    

    public function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getId(): string
    {
        return 'group';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_GROUP;
    }
    
    public function getCSVPaths():array{
        return $this->csvPaths;
    }
    
    public function getGroupPath():string{
        return $this->basePath;
    }

    public function getOutFileName(string $suffix = ""):string
    {
        return "all_time$suffix";
    }
    
    public function plot(array $csvPaths): void
    {
        $this->csvPaths = $csvPaths;
        $this->basePath = \getcwd();

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
    }

    // ========================================================================
}
