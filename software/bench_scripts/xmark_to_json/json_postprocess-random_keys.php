<?php
if (! function_exists('replaceJsonData')) {

    function replaceJsonData(array $data, array $replacements, iterable $pseudoGenerator): array
    {
        $ret = [];

        foreach ($data as $key => $val) {
            $replacement = $replacements[$key] ?? null;

            if ($replacement == null) {

                if (is_array($val))
                    $val = replaceJsonData($val, $replacements, $pseudoGenerator);

                $ret[$key] = $val;
            } else {
                $nbRepl = count($replacement);
                $index = ($pseudoGenerator->current() >> 8) % $nbRepl;
                $replacement = $replacement[$index];
                $pseudoGenerator->next();

                if (is_array($val) && isset($val[0])) {
                    $newVal = [];

                    foreach ($val as $k => $subVal) {

                        if (! is_array($subVal))
                            $newVal[$k] = $subVal;
                        else
                            $newVal[$k] = replaceJsonData($subVal, $replacements, $pseudoGenerator);
                    }
                    $ret[$replacement] = $newVal;
                } else
                    $ret[$replacement] = $val;
            }
        }
        return $ret;
    }
}

return function (XMark2Json $converter, int $seed): callable {
    // We define an internal pseudo number generator to permit to generate an identical set of data for different persons.
    $pseudoGenerator = new class($seed) implements \Iterator {

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
    };

    return function (array $data) use ($converter, $pseudoGenerator) {
        $replacements = $converter->getRelabellings();
        return replaceJsonData($data, $replacements, $pseudoGenerator);
    };
};

