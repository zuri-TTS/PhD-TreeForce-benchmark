<?php
namespace PseudoGenerator;

final class OfInt implements \Iterator
{

    // LCG
    private $a = 1103515245;

    private $b = 12345;

    private $c = 1024 ** 3 * 2;

    private $seed;

    private $randValue;

    function __construct(int $seed)
    {
        $this->seed = $seed;
        $this->randValue = $seed;
    }

    public function currentNext(): int
    {
        $ret = $this->current();
        $this->next();
        return $ret;
    }

    public function yesNoNext(float $proba = .5): bool
    {
        $ret = $this->yesno($proba);
        $this->next();
        return $ret;
    }

    public function yesNo(float $proba = .5): bool
    {
        $v = $this->current();
        $part = (int) (($this->c - 1) * $proba);
        return \in_range($v, 0, $part);
    }

    public function rewind()
    {
        $this->randValue = $this->seed;
    }

    public function current()
    {
        return (int) $this->randValue;
    }

    public function key()
    {
        return null;
    }

    public function next()
    {
        $this->randValue = ($this->randValue * $this->a + $this->b) % $this->c;
    }

    public function valid()
    {
        return true;
    }
}