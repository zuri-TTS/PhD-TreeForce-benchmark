<?php
namespace PseudoGenerator;

final class PRanges implements \Iterator
{

    private int $def = - 1;

    private int $maxChoice;

    private int $randValue;

    private array $nbs;

    private array $ranges;

    public function __construct(array ...$ranges)
    {
        $noProba = \array_filter_shift($ranges, fn ($v) => ! isset($v[2]));
        $nbNo = \count($noProba);

        $probas = \array_column($ranges, 2);
        $p = \array_sum($probas);

        if ($p > 1)
            throw new \Exception("proba $p > 1");

        $div = 1.0 / $nbNo;
        foreach ($noProba as &$np)
            $np[2] = 1.0 - $p;

        $this->ranges = \array_merge($ranges,$noProba);
        $this->maxChoice = \count($probas) * 1000;
        $this->nbs = [];

        foreach ($probas as $p)
            $this->nbs[] = $p * $this->maxChoice;

        $this->next();
    }

    public function currentNext(): int
    {
        $ret = $this->current();
        $this->next();
        return $ret;
    }

    public function rewind()
    {}

    public function current()
    {
        return $this->randValue;
    }

    public function key()
    {
        return null;
    }

    public function next()
    {
        $this->randValue = $this->rand();
    }

    public function rand(): int
    {
        $rand = \mt_rand(0, $this->maxChoice);
        $choice = 0;

        foreach ($this->nbs as $n) {

            if ($rand < $n)
                break;

            $rand -= $n;
            $choice ++;
        }
        $ranges = $this->ranges;

        if (! isset($ranges[$choice]))
            return $this->def;
        else {
            $range = $ranges[$choice];
            return \mt_rand($range[0], $range[1]);
        }
    }

    public function valid()
    {
        return true;
    }
}