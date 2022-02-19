<?php
namespace File;

final class MergeLineAccesses implements ILineAccess
{

    private array $accesses;

    private ?array $index = null;

    private int $nbLines;

    public function __construct(LineAccess ...$accesses)
    {
        $this->accesses = $accesses;
        $this->makeIndex();
    }

    public function setSkipEmpty(bool $skip = true): MergeLineAccesses
    {
        foreach ($this->accesses as $la)
            $la->setSkipEmpty($skip);

        $this->index = null;
        return $this;
    }

    private function getIndex(): array
    {
        if (null === $this->index)
            $this->makeIndex();

        return $this->index;
    }
    
    private function invalidRange(int $pos): void
    {
        $nb = $this->nbLines();
        throw new \Exception("Invalid line pos: $pos; must be in range [0,$nb]");
    }

    // ========================================================================
    public function nbLines(): int
    {
        $this->getIndex();
        return $this->nbLines;
    }

    public function getLine(int $pos): string
    {
        if ($pos < 0 || $pos >= $this->nbLines())
            $this->invalidRange($pos);

        foreach ($this->getIndex() as $k => $max) {
            
            if ($pos < $max)
                return $this->accesses[$k]->getLine($pos);

            $pos -= $max;
        }
        throw new \Exception(__CLASS__ . "Should never happens");
    }

    // ========================================================================
    private function makeIndex(): void
    {
        $index = [];
        $nbLines = 0;

        foreach ($this->accesses as $la) {
            $nbl = $la->nbLines();
            $index[] = $nbl;
            $nbLines += $nbl;
        }
        $this->index = $index;
        $this->nbLines = $nbLines;
    }
}