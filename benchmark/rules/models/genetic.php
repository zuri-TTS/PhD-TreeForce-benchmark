<?php
return fn (array $args) => new class($args) implements IModelGenerator {

    private const default_nbRefs_range = [
        100,
        110
    ];

    private array $queries;

    private array $query_nbRewritings;

    private bool $validArgs = true;

    // ========================================================================
    private int $n = 150;

    private int $nbLoops = 1000;

    private float $mutation_factor = 0.5;

    private float $select_factor = 0.75;

    private float $select_factor_elite = 0.7;

    private bool $stopOnSolution = true;

    private int $nbTry = 1;

    private int $currentTry = 1;

    private int $useModel = 0;

    private int $sort = 1;

    // ========================================================================
    private const default_options = [
        // Clean solutions file before begins
        'clean.solutions' => false,
        // Add more solutions to the existing ones
        'solutions.more' => false
    ];

    function usage(): string
    {
        return <<<EOT
        Generate a model to reach a precise number of reformulations
        
        Parameters:
        clean.solutions(false):
        solutions.more(false):

        Genetic options:
        Gn($this->n): population size
        GnbLoops($this->nbLoops): number of iterations
        GnbTry($this->nbTry):
        Gsort($this->sort):
        Gmutation.factor($this->mutation_factor):
        Gselect.factor($this->select_factor):
        Gselect.factor.elite($this->select_factor_elite):
        GstopOnSolution($this->stopOnSolution):
        GuseModel($this->useModel): index of the model to use at the end

        Query option:
        Q#query: number of reformulations for the query #query
        EOT;
    }

    function validArgs(): bool
    {
        return $this->validArgs;
    }

    // ========================================================================
    function __construct(array $args)
    {
        $args += self::default_options;

        $this->query_nbRewritings = [];
        $this->queries = getQueries(false);
        // Prepare queries' range
        {
            $user_q = argPrefixed($args, 'Q');

            foreach ($user_q as &$q) {

                if (\is_array($q))
                    continue;

                $tmp = \explode(',', $q);
                $q = [
                    (int) $tmp[0],
                    (int) ($tmp[1] ?? $tmp[0])
                ];
            }
            foreach ($this->queries as $query) {

                $range = $user_q[$query] ?? $user_q['default'] ?? self::default_nbRefs_range;
                $this->query_nbRewritings[$query] = $range;
            }
        }
        $this->init();

        // Prepare object's properties
        {
            $myParams = argPrefixed($args, 'G');
            try {
                updateObject($myParams, $this);
            } catch (\Exception $e) {
                echo $e->getMessage(), "\n";
                $this->validArgs = false;
                return;
            }
        }
        $geneticModelPath = $this->getGeneticModelFilePath();

        if ($args['clean.solutions']) {

            if (\is_file($geneticModelPath))
                unlink($geneticModelPath);
        }
        $model = $this->getGeneticModel();
        $mustGenerate = empty($model);
        $nbTry = $this->nbTry;

        if ($args['solutions.more']) {
            $mustGenerate = true;

            while ($nbTry --)
                $model = \array_merge($model, $this->doGeneration());
        } else {
            while (empty($model) && $nbTry --) {
                $model = $this->doGeneration();
            }
        }

        if (! empty($model) && $this->sort !== 0) {
            $mustGenerate = true;
            $cmp = $this->sort === 1 ? 'self::cmpResult' : fn ($a, $b) => - self::cmpResult($a, $b);
            \usort($model, $cmp);
        }

        if ($mustGenerate && ! empty($model)) {
            $file = new SPLFileObject($geneticModelPath, 'w');
            $this->writeGeneticModelFile($file, $model);
        }
        // must be after writeGeneticModelFile that uniquify solutions
        $nb = \count($model);
        echo "solutions: $nb\n";
    }

    private static function cmpResult($a, $b): int
    {
        foreach ($a as $k => $va) {
            if (! \is_int($va))
                continue;

            $vb = $b[$k];

            $v = $va - $vb;
            if ($v)
                return $v;
        }
        return 0;
    }

    function __set($name, $val)
    {
        if (! \property_exists($this, $name))
            throw new \Exception("Invalid option $name");

        $this->$name = $val;
    }

    function generate(\SplFileObject $writeTo)
    {
        $model = $this->getGeneticModel();

        if (! empty($model)) {

            foreach ($model[0] as $label => $nb) {
                if (! \is_int($nb) || (-- $nb == 0))
                    continue;

                $writeTo->fwrite("$label $nb $nb\n");
            }
        }
    }

    function writeGeneticModelFile(\SplFileObject $writeTo, array &$solutions): void
    {
        if (empty($solutions))
            $writeTo->fwrite('<?php return [];');
        else {
            $solutions = \array_unique($solutions, SORT_REGULAR);
            $s = [];

            foreach ($solutions as $sol) {
                $s[] = var_export($sol, true);
            }
            $writeTo->fwrite('<?php return [' . implode(',', $s) . '];');
        }
    }

    private function getSuffixName(): string
    {
        $s = [];

        foreach ($this->query_nbRewritings as $k => $v) {
            if ($v[0] === $v[1])
                $v = $v[0];
            else
                $v = implode('-', $v);

            $s[] = "{$k}($v)";
        }
        return implode('_', $s);
    }

    private function getGeneticModel(): array
    {
        $file = $this->getGeneticModelFilePath();

        if (! is_file($file))
            return [];

        return (array) (include $file);
    }

    private function getGeneticModelFilePath()
    {
        return __DIR__ . '/' . $this->getGeneticModelFileName();
    }

    private function getGeneticModelFileName(): string
    {
        $s = $this->getSuffixName();
        return "genetic_{$s}.php";
    }

    function getOutputFileName(): string
    {
        $s = $this->getSuffixName();
        return "{$s}";
    }

    // ========================================================================
    private static function randomItem(array $a)
    {
        \shuffle($a);
        return $a[0];
    }

    // ========================================================================
    private array $labels;

    private array $query_labelsFreq;

    private const i_dominants = 'dom';

    private const i_distance = 'd';

    private const i_qdistance = 'qd';

    private const i_nbRefs = 'nb';

    private function init(): void
    {
        $this->labels = [];
        $path = getQueriesBasePath();

        foreach ($this->queries as $query) {
            $fpath = "$path/$query";

            if (! \is_file($fpath))
                continue;

            $q = \file_get_contents($fpath);
            preg_match_all("#[\w@]+#", $q, $labels);
            $labels = \array_shift($labels);

            $this->labels = \array_merge($this->labels, $labels);
            $this->query_labelsFreq[$query] = \array_count_values($labels);
        }
        $this->labels = \array_unique($this->labels);
    }

    private function initPopulation(int $nb): array
    {
        $ret = [];

        for ($i = 0; $i < $nb; $i ++) {
            $one = [];

            foreach ($this->labels as $label)
                $one[$label] = \rand(1, 2);

            $one = [
                'o' => $one
            ];
            $this->updateOne($one);
            $ret[] = $one;
        }
        $this->updateNotes($ret);
        return $ret;
    }

    // ========================================================================
    private function updateOne(array &$one): void
    {
        $one[self::i_qdistance] = $this->getDistances($one['o']);
        $one[self::i_distance] = $this->getDistance($one[self::i_qdistance]);
    }

    private function sortPopulation(array &$population): void
    {
        \usort($population, [
            $this,
            'cmpDominants'
        ]);
    }

    private function cmpDominants(array $a, array $b): int
    {
        $tmp = $a[self::i_dominants] - $b[self::i_dominants];

        if ($tmp)
            return $tmp;

        return $a[self::i_distance] - $b[self::i_distance];
    }

    private function isDominatedBy(array $a, array $b): bool
    {
        foreach ($this->queries as $query) {
            $va = $a[self::i_qdistance][$query];
            $vb = $b[self::i_qdistance][$query];

            $va = abs($va);
            $vb = abs($vb);

            if ($va < $vb)
                return false;
        }
        return true;
    }

    private function updateNotes(array &$population): void
    {
        $c = \count($population);

        while ($c --) {
            $nbDominants = 0;
            $one = \array_shift($population);

            foreach ($population as $anotherOne)
                if ($this->isDominatedBy($one, $anotherOne))
                    $nbDominants ++;

            $one[self::i_dominants] = $nbDominants;
            $population[] = $one;
        }
    }

    private function getDistances(array $one, bool $onlyNbRefs = false): array
    {
        $dists = [];

        foreach ($this->query_labelsFreq as $query => $labels) {
            $nb = 1;

            foreach ($labels as $label => $freq)
                $nb *= $one[$label] * $freq;

            if ($onlyNbRefs)
                $v = $nb;
            else {
                $min = ($this->query_nbRewritings[$query][0] - $nb);
                $max = ($this->query_nbRewritings[$query][1] - $nb);

                if ($min > 0)
                    $v = $min;
                elseif ($max < 0)
                    $v = $max;
                else
                    $v = 0;
            }
            $dists[$query] = $v;
        }
        return $dists;
    }

    private function getDistance(array $distances): int
    {
        return \array_reduce($distances, fn ($a, $b) => abs($a) + abs($b), 0);
    }

    // ========================================================================
    private function isSolution($one)
    {
        return $one[self::i_distance] == 0;
    }

    private function doGeneration(): array
    {
        echo "<", $this->getGeneticModelFileName(), ">\n";
        echo "Try $this->currentTry/$this->nbTry nbLoops:$this->nbLoops n:$this->n\n";
        $this->currentTry ++;

        $solutions = [];
        $population = $this->initPopulation($this->n);
        $this->sortPopulation($population);

        for ($i = 0; $i < $this->nbLoops; $i ++) {
            $population = $this->evolution($population);
            $s = 0;
            $hasSol = false;

            while ($this->isSolution($sol = $population[$s ++])) {
                $solutions[] = $sol;
                $hasSol = true;
            }

            if ($hasSol && $this->stopOnSolution)
                break;
        }
        $solutions = \array_unique($solutions, SORT_REGULAR);
        $nb = \count($solutions);
        return \array_map(function ($s) {
            $ret = $s['o'];
            $ret["#nb"] = $this->getDistances($ret, true);
            return $ret;
        }, $solutions);
    }

    private function evolution($population): array
    {
        $select = $this->selection($population);
        $this->mutation($select);

        $new = $this->initPopulation($this->n - \count($select));

        $population = array_merge($select, $new);
        $this->updateNotes($population);
        $this->sortPopulation($population);
        return $population;
    }

    private function selection($population): array
    {
        $nb = (int) ($this->n * $this->select_factor);
        $nbElite = (int) ($nb * $this->select_factor_elite);

        $ret = \array_slice($population, 0, $nbElite);
        $othersId = \range($nbElite, $this->n - 1);

        \shuffle($othersId);
        $c = $nb - $nbElite;

        while ($c --)
            $ret[] = $population[$othersId[$c]];

        \shuffle($ret);
        return $ret;
    }

    private const mutationVal = [
        1,
        2
    ];

    private function mutation(&$population): void
    {
        $c = \count($population);
        $nb = (int) ($c * $this->mutation_factor);
        $ids = \range(0, $c - 1);
        \shuffle($ids);
        $nbLabels = \count($this->labels);

        while ($c --) {
            $p = &$population[$ids[$c]];
            $one = &$p['o'];

            $minv = \min($p[self::i_qdistance]);
            $add = ($minv < 0) ? - 1 : 1;
            for (;;) {
                $label = self::randomItem($this->labels);
                $val = &$one[$label];

                if ($minv < 0 && $val == 1)
                    continue;

                $val = \max(1, $val + $add);
                break;
            }
            $this->updateOne($p);
        }
    }
};