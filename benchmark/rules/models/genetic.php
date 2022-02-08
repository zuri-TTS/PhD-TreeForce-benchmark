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

    private float $crossOver_nbLabels_factor_min = 0;

    private float $crossOver_nbLabels_factor_max = 0.8;

    private float $select_factor = .5;

    private float $select_factor_elite = .3;

    private float $mutation_factor = .8;

    private float $reproduction_factor = .6;

    private float $fresh_factor = .15;

    private bool $skip_existing = true;

    private bool $stopOnSolution = true;
    
    private bool $forceRetry = false;

    private bool $solutions_more = true;

    private bool $clean_solutions = false;

    private int $nbTry = 1;

    private int $currentTry = 1;

    private int $useModel = 0;

    private int $sort = 1;

    private int $init_rand_max = 10;

    private int $mutation_nb_max = 5;

    private int $mutation_rand_min = 1;

    private int $mutation_rand_max = 5;

    private $display = 0;

    private $display_offset = 0;

    private $it;

    // ========================================================================
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
        GrandMin($this->randMin):
        GrandMax($this->randMax):

        Query option:
        Q#query: number of reformulations for the query #query
        EOT;
    }

    private function displayConfig(): void
    {
        foreach (\get_object_vars($this) as $k => $v)
            if (\is_numeric($v))
                echo "$k($v)\n";
            elseif (\is_bool($v)) {
                $v = $v ? 'true' : 'false';
                echo "$k($v)\n";
            }
    }

    function validArgs(): bool
    {
        return $this->validArgs;
    }

    // ========================================================================
    function __construct(array $args)
    {
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
                $this->query_nbRewritings[$query]['range'] = ($range[1] - $range[0]);
                $this->query_nbRewritings[$query]['frange'] = (float) ($range[1] - $range[0] + 1);
            }
        }

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
        $this->init();
        $geneticModelPath = $this->getGeneticModelFilePath();

        if ($this->clean_solutions) {

            if (\is_file($geneticModelPath))
                unlink($geneticModelPath);
        }
        $model = $this->getGeneticModel();
        $generatedModel = [];
        $mustGenerate = empty($model);
        $nbTry = $this->nbTry;

        if ($this->solutions_more)
            $mustGenerate = true;

        if (! $this->skip_existing || empty($model)) {
            $this->currentTry = 0;

            while (($this->forceRetry || empty($generatedModel)) && $nbTry --) {
                $generatedModel = $this->doGeneration();
                $model = \array_merge($model, $generatedModel);
                $nb = \count($model);
                echo "solutions: $nb\n";
            }
        } else
            echo "(Skipped)\n";

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
        echo "total solutions: $nb\n";
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

    public function generate(\SplFileObject $writeTo)
    {
        $model = $this->getGeneticModel();

        if (! empty($model)) {

            foreach ($model[$this->useModel] as $label => $nb) {
                if (! \is_int($nb) || (-- $nb == 0))
                    continue;

                $writeTo->fwrite("$label $nb $nb\n");
            }
        }
    }

    private function writeGeneticModelFile(\SplFileObject $writeTo, array &$solutions): void
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
        $s = $groups = [];

        foreach ($this->query_nbRewritings as $k => $v) {
            if ($v[0] === $v[1])
                $v = $v[0];
            else
                $v = $v[0] . '-' . $v[1];

            $groups[$v][] = $k;
        }
        foreach ($groups as $r => $queries) {
            $s[] = "($r)" . implode('_', $queries);
        }
        return implode('-', $s);
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

    private function displayOne($p)
    {
        foreach ($p[self::i_qdistance] as $q => $dist)
            printf("$q:%12.2f ", $dist);

        echo "|";

        foreach ($p["#nb"] ?? [] as $q => $nb)
            printf(" $q:%5d", $nb);

        printf("|%4d d%8d", $p[self::i_dominants], $p[self::i_distance]);
        echo ' [' . implode(',', $p['o']) . ']';
    }

    private function displayFirsts($population, int $nb)
    {
        if (! $nb)
            return;

        $c = \count($population);
        $offset = $this->display_offset;

        if ($offset < 0)
            $offset = $c + $offset - 1;

        echo "(pop:$c)[$offset:" . (string) ($offset + $nb) . "] try:$this->currentTry/$this->nbTry i:$this->it/$this->nbLoops\n";

        for ($i = 0; $i < $nb; $i ++) {
            $k = $i + $offset;
            $this->displayOne($population[$k]);
            $population[$k]['o'] = \array_values($population[$k]['o']);
            echo "\n";
        }
        echo "\n";
    }

    // ========================================================================
    private static function randomItem(array $a)
    {
        return $a[\array_rand($a)];
    }

    // ========================================================================
    private array $labels;

    private array $query_labelsFreq;

    private const i_dominants = 'dom';

    private const i_distance = 'd';

    private const i_qdistance = 'qd';

    private const i_nbRefs = 'nb';

    private int $select_nb, $select_elites_nb;

    private int $mutations_nb;

    private int $reproduction_nb;

    private int $fresh_nb;

    private function init(): void
    {
        $this->labels = [];
        $path = getQueriesBasePath();

        foreach ($this->queries as $query) {
            $fpath = "$path/$query";

            if (! \is_file($fpath))
                continue;

            $q = \file_get_contents($fpath);
            \preg_match_all("#[\w@]+#", $q, $labels);
            $labels = \array_shift($labels);

            $this->labels = \array_merge($this->labels, $labels);
            $this->query_labelsFreq[$query] = \array_count_values($labels);
        }
        $this->labels = \array_unique($this->labels);

        $this->fresh_nb = (int) ($this->n * $this->fresh_factor);
        $this->select_nb = (int) ($this->n * $this->select_factor);
        $this->select_elites_nb = (int) ($this->select_nb * $this->select_factor_elite);
        $this->reproduction_nb = (int) ($this->n * $this->reproduction_factor);
        $this->mutations_nb = (int) ($this->reproduction_nb * $this->mutation_factor);
    }

    private function initPopulation(int $nb): array
    {
        $ret = [];

        while ($nb -- > 0) {
            $one = [];

            foreach ($this->labels as $label)
                $one[$label] = \mt_rand(1, $this->init_rand_max);

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
        // $one["#nb"] = $this->getDistances($one['o'], true);
    }

    private function sortPopulation(array &$population): void
    {
        \usort($population, [
            $this,
            'cmpDominants'
        ]);
    }

    private function cmpDominants(array $a, array $b)
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
            $q = $this->query_nbRewritings[$query];
            $nb = 1;

            foreach ($labels as $label => $freq) {
                $nb *= $one[$label] ** $freq;
            }
            if ($onlyNbRefs) {
                $v = $nb;
            } else {
                $min = ($q[0] - $nb);
                $max = ($q[1] - $nb);

                if ($min > 0)
                    $v = $min;
                elseif ($max < 0)
                    $v = $max;
                elseif ($q['range'])
                    $v = (- $min) / $q['frange'];
                else
                    $v = - $min;
            }
            $dists[$query] = $v;
        }
        return $dists;
    }

    private function getDistance(array $distances)
    {
        return \array_reduce($distances, fn ($a, $b) => abs((int) $a) + abs((int) $b), 0);
    }

    // ========================================================================
    private function isSolution($one)
    {
        $d = $one[self::i_distance];
        return $d >= 0.0 && $d < 1.0;
    }

    private function doGeneration(): array
    {
        $this->currentTry ++;
        echo "<", $this->getGeneticModelFileName(), ">\n";
        echo $this->displayConfig();
        echo "Try $this->currentTry/$this->nbTry\n";

        $solutions = [];
        $population = $this->initPopulation($this->n);
        $this->sortPopulation($population);
        $this->it = 0;

        for ($i = 0; $i < $this->nbLoops; $i ++) {
            $this->it ++;
            $population = $this->evolution($population);
            $this->displayFirsts($population, $this->display);
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
        $select = $this->selection($population, $this->select_nb, $this->select_elites_nb);
        $childs = $this->reproduction($select, $this->reproduction_nb);

        $fresh = $this->initPopulation($this->fresh_nb);

        $nbPadding = $this->n - ($this->select_nb + \count($childs) + $this->fresh_nb);
        $padding = $nbPadding > 0 ? $this->initPopulation($nbPadding) : [];

        $population = \array_merge($select, $childs, $fresh, $padding);
        $this->updateNotes($population);
        $this->sortPopulation($population);
        $population = \array_slice($population, 0, $this->n);
        $this->updateNotes($population);
        $this->sortPopulation($population);
        return $population;
    }

    private function selection($population, int $nbTotal, int $nbElites): array
    {
        $ret = \array_slice($population, 0, $nbElites);
        $other = \array_slice($population, $nbElites);
        \shuffle($other);
        return \array_merge($ret, \array_slice($other, 0, $nbTotal - $nbElites));
    }

    private function reproduction($population, int $nbChilds): array
    {
        $new = [];

        while ($nbChilds --) {
            $a = $this->randomItem($population);
            $b = $this->randomItem($population);
            $new[] = $this->crossOver($a, $b);
        }
        $this->mutate($new, $this->mutations_nb);
        return \array_unique($new, SORT_REGULAR);
    }

    private function crossOver($a, $b): array
    {
        $nbLabels = (int) (\count($a['o']) * (\mt_rand($this->crossOver_nbLabels_factor_min * 1000, $this->crossOver_nbLabels_factor_max * 1000) / 1000.0));
        $ret = $a;

        while ($nbLabels --) {
            $k = \array_rand($ret['o']);
            $ret['o'][$k] = $b['o'][$k];
        }
        $this->updateOne($ret);
        return $ret;
    }

    private function mutateOne(&$p): void
    {
        $one = &$p['o'];

        $minv = \min($p[self::i_qdistance]);
        $add = ($minv < 0) ? - 1 : 1;
        $nbMut = \mt_rand(1, $this->mutation_nb_max);

        for (; $nbMut;) {
            $label = self::randomItem($this->labels);
            $val = &$one[$label];
            $addf = \mt_rand($this->mutation_rand_min, $this->mutation_rand_max);
            $val = \max(1, $val + $add * $addf);
            $nbMut --;
        }
        $this->updateOne($p);
    }

    private function mutate($population, int $nb): void
    {
        while ($nb -- > 0) {
            $this->mutateOne($population[\array_rand($population)]);
        }
    }
};