<?php
namespace Data;

final class XMarkLoader implements ILoader
{

    private string $group;

    private \Args\ObjectArgs $oargs;

    public string $conf_cc;

    public string $conf_bin_path;

    public string $conf_program_name;

    public int $conf_seed;

    public float $conf_xmark_factor;

    public function __construct(string $group, array $config)
    {
        $this->group = $group;

        $this->conf_program_name = 'xmark.gen';
        $this->conf_cc = "gcc '%bin/unix.c' -o '%bin/%program'";
        $this->conf_bin_path = \getSoftwareBinBasePath() . '/xmark';

        $oa = $this->oargs = (new \Args\ObjectArgs($this))-> //
        setPrefix('conf_')-> //
        mapKeyToProperty(fn ($k) => \str_replace('.', '_', $k));
        $oa->updateAndShift($config);
        $oa->checkEmpty($config);
    }

    private const unwind = [
        'site.regions.$.*',
        'site.categories.*',
        'site.catgraph.*',
        'site.people.*',
        'site.open_auctions.*',
        'site.closed_auctions.*'
    ];

    public function getUnwindConfig(): array
    {
        return self::unwind;
    }

    private const is_list = [
        'text',
        'bold',
        'emph',
        'keyword',
        'parlist',
        'listitem',
        'incategory',
        'mail',
        'interest',
        'watch',
        'bidder'
    ];

    function isList(string $name): bool
    {
        return \in_array($name, self::is_list);
    }

    function getOut(string $name, string $subVal): bool
    {
        return false;
    }

    function isObject(string $name): bool
    {
        return false;
    }

    function isText(string $name): bool
    {
        return false;
    }

    function isMultipliable(string $name): bool
    {
        return false;
    }

    public function deleteXMLFile(): bool
    {
        $path = $this->getXMLFilePath();

        if (\is_file($path))
            return \unlink($path);

        return true;
    }

    public function getXMLReader(): \XMLReader
    {
        return \XMLReader::open($this->getXMLFilePath());
    }

    private function getXMLFilePath(): string
    {
        $xmarkFilePath = $this->XMarkFilePath();
        $this->generateXMark($xmarkFilePath);
        return $xmarkFilePath;
    }

    private function XMarkFilePath(): string
    {
        $groupPath = \DataSets::getGroupPath($this->group);
        return "$groupPath/xmark.xml";
    }

    private function generateXMark(string $xmarkFilePath)
    {
        if (\is_file($xmarkFilePath))
            return;

        $this->binProgramCompile();
        echo "Generate $xmarkFilePath\n";
        $basePath = getBenchmarkBasePath();
        $xmarkCmd = $this->binProgramPath();
        $cmd = "'$xmarkCmd' -f {$this->conf_xmark_factor} -o '$xmarkFilePath'";
        echo "Execute $cmd";
        \system($cmd);
    }

    public function getLabelReplacerForDataSet(\DataSet $dataSet): callable
    {
        return LabelReplacer::getReplacerForDataSet($dataSet, $this->conf_seed);
    }

    // ========================================================================
    private function binProgramPath(): string
    {
        return "$this->conf_bin_path/$this->conf_program_name";
    }

    private function binProgramCompile(): void
    {
        if ($this->binProgramExists())
            return;

        $cmd = \str_format($this->conf_cc, [
            'bin' => $this->conf_bin_path,
            'program' => $this->conf_program_name
        ]);

        if ($code = \simpleExec($cmd, $output, $err))
            throw new \Exception("Error executing cmd: $cmd; return code: $code");
    }

    private function binProgramExists(): bool
    {
        return \is_file($this->binProgramPath());
    }
}
