<?php

function generateRules(array $config)
{
    $model = readModel($config);
    $qvocFile = new \SplFileObject($config["rules.q.path"], "w");
    $rvocFile = new \SplFileObject($config["rules.r.path"], "w");

    foreach ($model as $label => $m) {
        $nbQVoc = $m["qvoc"];
        $nbRVoc = $m["nb"] - $nbQVoc;
        writeARule($qvocFile, $label, $nbQVoc, "q_");
        writeARule($rvocFile, $label, $nbRVoc, "r_");
    }
}

function writeARule(\SplFileObject $file, string $label, int $nb, string $prefix = '')
{
    $file->fwrite("#$label $nb\n\n");
    
    while($nb--)
    {
        $s = "'$prefix${label}_$nb'=?x? --> '$label'=?x?\n";
        $file->fwrite($s);
    }
    $file->fwrite("\n");
}

function readModel(array $config): array
{
    $modelPath = $config['rules.model.path'];
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