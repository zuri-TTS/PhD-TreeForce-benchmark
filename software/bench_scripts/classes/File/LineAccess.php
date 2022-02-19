<?php
namespace File;

final class LineAccess implements ILineAccess
{

    private bool $skipEmptyLines = false;

    private bool $cacheIt = false;

    // ========================================================================
    private $file;

    private string $filePath;

    private ?array $index = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function __destruct()
    {
        $this->closeFile();
    }

    public function setSkipEmpty(bool $skip = true): LineAccess
    {
        $this->skipEmptyLines = $skip;
        $this->index = null;
        return $this;
    }
    
    public function clearCache(): void
    {
        $cache = $this->cachePath();
        
        if(\is_file($cache))
            \unlink($cache);
    }

    public function setCache(bool $cache = true): LineAccess
    {
        $this->cacheIt = $cache;

        if ($cache)
            $this->makeCache();
        
        return $this;
    }

    private function getIndex(): array
    {
        if (null === $this->index)
            $this->makeIndex();

        return $this->index;
    }

    // ========================================================================
    public function getLine(int $pos): string
    {
        $index = $this->getIndex();

        if (! isset($index[$pos]))
            $this->invalidRange($pos);

        $file = $this->getFile();
        \fseek($file, $index[$pos]);
        return \fgets($file);
    }

    public function nbLines(): int
    {
        return \count($this->getIndex());
    }

    private function invalidRange(int $pos): void
    {
        $nb = $this->nbLines() - 1;
        throw new \Exception("Invalid line pos: $pos; must be in range [0,$nb]");
    }

    // ========================================================================
    private function getFile()
    {
        if ($this->file !== null)
            return $this->file;

        return $this->file = \fopen($this->filePath, 'r');
    }

    public function closeFile(): bool
    {
        if (null === $this->file)
            return true;

        $ret = \fclose($this->file);

        if ($ret)
            $this->file = null;

        return $ret;
    }

    // ========================================================================
    private function cachePath(): string
    {
        $skip = $this->skipEmptyLines ? 'skip.' : '';
        return "$this->filePath.index.${skip}php";
    }

    private function makeCache(): void
    {
        \printPHPFile($this->cachePath(), $this->getIndex(), true);
    }

    private function makeIndex(): void
    {
        if ($this->cacheIt) {
            $cachePath = $this->cachePath();

            if (\is_file($cachePath)) {
                $this->index = include $cachePath;
                return;
            }
        }
        $file = $this->getFile();
        \rewind($file);
        $index = [
            0 => 0
        ];

        while (($s = \fgets($file)) !== false) {
            $s = \trim($s);

            if (! $this->skipEmptyLines || ! empty($s)) {
                $index[] = \ftell($file);
            }
        }
        unset($index[\count($index) - 1]);

        if (! feof($file))
            throw new \Exception("fgets files for $filePath");

        $this->index = $index;
    }
}