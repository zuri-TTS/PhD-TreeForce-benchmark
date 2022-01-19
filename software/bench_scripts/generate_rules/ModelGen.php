<?php
include __DIR__ . '/IModelGenerator.php';

class ModelGen
{

    private string $basePath;

    private string $inModelPath;

    private string $outModelPath;

    private string $outFileName;

    private IModelGenerator $generator;

    public function __construct(string $modelPath, array $args)
    {
        $this->basePath = getBenchmarkBasePath() . '/benchmark/rules';
        $this->inModelPath = "$this->basePath/models/$modelPath";
        $modelPath = "$this->inModelPath.php";

        if (! is_file($modelPath))
            throw new \Exception("The model $modelPath does not exists");

        $this->generator = (include $modelPath)($args);
        $this->outFileName = $this->generator->getOutputFileName();
        $this->outModelPath = "$this->basePath/models/$this->outFileName.model";
    }

    public function getInModelPath(): string
    {
        return $this->inModelPath;
    }

    public function generate(): bool
    {
        $outFile = $this->outModelPath;

//         if (\is_file($outFile)) {
//             echo "Already generated: $outFile\n";
//             return true;
//         }
        if ($this->generator->validArgs()) {
            echo "Generating $outFile\n";
            $this->generator->generate(new \SplFileObject($outFile, 'w'));
            return true;
        }
        echo $this->generator->usage();
        return false;
    }

    public function generateRules()
    {
        $outDirPath = "$this->basePath/$this->outFileName";

        if (! \is_dir($outDirPath))
            \mkdir($outDirPath);

        echo "Generate rules in $outDirPath\n";

        $model = $this->readModel($this->outModelPath);
        $qvocFile = new \SplFileObject("$outDirPath/querying.txt", 'w');
        $rvocFile = new \SplFileObject("$outDirPath/reasoning.txt", 'w');

        foreach ($model as $label => $m) {
            $nbQVoc = $m["qvoc"];
            $nbRVoc = $m["nb"] - $nbQVoc;
            $this->writeARule($qvocFile, $label, $nbQVoc, "q_");
            $this->writeARule($rvocFile, $label, $nbRVoc, "r_");
        }
    }

    private function writeARule(\SplFileObject $file, string $label, int $nb, string $prefix = '')
    {
        $file->fwrite("#$label $nb\n\n");

        while ($nb --) {
            $s = "'$prefix${label}_$nb'=?x? --> '$label'=?x?\n";
            $file->fwrite($s);
        }
        $file->fwrite("\n");
    }

    private function readModel(string $modelPath): array
    {
        $ret = [];

        foreach (\file($modelPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (($line[0] ?? '#') === '#')
                continue;

            $found = \preg_match("/^([\w@]+)\s+(\d+)\s+(\d+)$/", $line, $matches, PREG_OFFSET_CAPTURE);

            if (! $found) {
                echo "Error: invalid line: $line";
                exit(1);
            }
            $ret[$matches[1][0]] = [
                "nb" => (int) $matches[2][0],
                "qvoc" => (int) $matches[3][0]
            ];
        }
        return $ret;
    }
}