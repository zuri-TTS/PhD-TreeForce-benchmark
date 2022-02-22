<?php
namespace Data;

final class LabelReplacer
{

    private array $replacements;

    private \PseudoGenerator\OfInt $intGen;

    public function __construct(array $replacements, int $seed)
    {
        $this->replacements = $replacements;
        $this->intGen = new \PseudoGenerator\OfInt($seed);
    }

    private function getReplacement(array $data): array
    {
        $ret = [];

        foreach ($data as $key => $val) {
            $replacement = $this->replacements[$key] ?? null;

            if ($replacement == null) {

                if (is_array($val))
                    $val = $this->getReplacement($val);

                $ret[$key] = $val;
            } else {
                $nbRepl = count($replacement);
                $index = ($this->intGen->currentNext() >> 8) % $nbRepl;
                $replacement = $replacement[$index];

                $ret[$replacement] = is_array($val) ? //
                $this->getReplacement($val) : //
                $val;
            }
        }
        return $ret;
    }

    public function __invoke($data)
    {
        return $this->getReplacement($data);
    }

    private static function _getRelabellings($ruleFile): array
    {
        $lines = file($ruleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ret = [];

        foreach ($lines as $line) {
            if (\trim($line)[0] === '#')
                continue;

            [
                $body,
                $head
            ] = \array_map('trim', explode('->', $line));
            $body = \explode('=', $body, 2)[0];
            $head = \explode('=', $head, 2)[0];

            $body = \trim($body, "'\"");
            $head = \trim($head, "'\"");

            // The head is in the replacement set
            if (! isset($ret[$head]))
                $ret[$head] = [
                    $head
                ];

            $ret[$head][] = $body;
        }
        return $ret;
    }

    public static function getReplacerForDataSet(\DataSet $dataSet, int $seed): callable
    {
        $rulesPath = $dataSet->rulesPath();
        $rulesFilePath = "$rulesPath/querying.txt";

        if (! \is_dir($rulesPath)) {
            echo "Warning: rule file $rulesFilePath does not exists (for $dataSet)\n";
            $rel = [];
        } else if (! \is_file($rulesFilePath)) {
            $rel = [];
        } else
            $rel = self::_getRelabellings($rulesFilePath);

        if (empty($rel))
            return fn ($d) => $d;
        else
            return new LabelReplacer($rel, $seed);
    }
}