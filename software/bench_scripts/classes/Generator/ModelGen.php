<?php
namespace Generator;

final class ModelGen
{

    private string $basePath;

    private string $modelBasePath;

    private string $outFileName;

    private \Generator\IModelGenerator $generator;

    public function __construct(string $modelPath, array $args)
    {
        $this->basePath = \Rules::getStoragePath();
        $this->modelBasePath = \Rules::getModelsBasePath() . "/$modelPath";
        $modelFilePath = "$this->modelBasePath.php";

        if (! \is_file($modelFilePath))
            throw new \Exception("The model $modelFilePath does not exists");
        if (! \is_dir($this->modelBasePath))
            \mkdir($this->modelBasePath, 0777, true);

        $obj = &$this;
        return \wdOp($this->modelBasePath, function () use (&$obj, $modelFilePath, $args) {
            $obj->generator = (include $modelFilePath)($args);
            $obj->outFileName = $this->generator->getOutputFileName();
        });
    }

    public function getInModelPath(): string
    {
        return $this->inModelPath;
    }

    public function generate(): bool
    {
        return \wdOp($this->modelBasePath, function () {

            if ($this->generator->validArgs()) {
                echo "Generating model/$this->outFileName\n";
                $this->generator->generate("$this->outFileName.model");
                return true;
            }
            echo $this->generator->usage();
            return false;
        });
    }

    public function generateRules()
    {
        return \wdOp($this->basePath, function () {
            $outDirPath = "$this->basePath/$this->outFileName";

            echo "Generate rules in $outDirPath\n";

            echo "$this->modelBasePath/$this->outFileName.model\n";
            $model = $this->readModel("$this->modelBasePath/$this->outFileName.model");

            if (empty($model))
                echo "Nothing to generate, retry another time\n";

            if (! \is_dir($outDirPath))
                \mkdir($outDirPath);

            $qvocFile = new \SplFileObject("$outDirPath/querying.txt", 'w');
            $rvocFile = new \SplFileObject("$outDirPath/reasoning.txt", 'w');

            foreach ($model as $label => $m) {
                $nbQVoc = $m["qvoc"];
                $nbRVoc = $m["nb"] - $nbQVoc;
                $this->writeARule($qvocFile, $label, $nbQVoc, "q_");
                $this->writeARule($rvocFile, $label, $nbRVoc, "r_");
            }
        });
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