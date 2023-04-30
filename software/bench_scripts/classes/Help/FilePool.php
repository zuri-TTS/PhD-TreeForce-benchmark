<?php
namespace Help;

final class FilePool
{

    private string $suffix;

    private int $nbMax;

    private array $files = [];

    private array $opened = [];

    public function __construct(int $nbMax, string $suffix = '.json')
    {
        if ($nbMax < 0)
            throw new \ErrorException("nbMax($nbMax) < 0");

        $this->nbMax = $nbMax;
        $this->suffix = $suffix;
    }

    public function get(string $id, array $modes = [
        'w',
        'a'
    ]): \SplFileInfo
    {
        $finfo = $this->files[$id] ?? null;

        if (null != $finfo)
            return $finfo;

        if (\count($this->files) == $this->nbMax) {
            $f = \array_pop($this->files);
            $f->fflush();
            unset($f);
        }

        $opened = $this->opened[$id] ?? null;
        $file = "$id$this->suffix";

        if (null == $opened) {
            $opened = $this->opened[$id] = new \SplFileInfo($file);
            $mode = $modes[0];
        } else
            $mode = $modes[1];

        return $this->files[$id] = $opened->openFile($mode);
    }

    public function clean()
    {
        foreach ($this->files as $f)
            $f->fflush();

        $this->files = [];
    }
}