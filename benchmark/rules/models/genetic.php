<?php
namespace Generator;

return fn (array $args) => new class($args) extends AbstractModelGenerator {

    private array $queriesPath;

    private array $queries;

    private array $query_nbRewritings;

    // ========================================================================
    public array $range = [];

    public int $n = 150;

    public int $nbLoops = 1000;

    public float $crossOver_nbLabels_factor_min = 0;

    public float $crossOver_nbLabels_factor_max = 0.5;

    public float $select_factor = .5;

    public float $select_factor_elite = .5;

    public float $mutation_factor = .2;

    public float $reproduction_factor = .5;

    public float $fresh_factor = 0.01;

    public bool $skip_existing = true;

    public bool $stopOnSolution = true;

    public bool $forceRetry = false;

    public bool $solutions_more = true;

    public bool $clean_solutions = false;

    public int $nbTry = 1;

    public int $currentTry = 1;

    public int $useModel = 0;

    public int $sort = 1;

    public array $init_ranges = [
        [
            1,
            50
        ],
        // [
        // 90,
        // 100,
        // .1
        // ],
        [
            200,
            500,
            .02
        ],
        [
            100,
            200,
            .3
        ]
    ];

    public array $mutation_ranges = [
        [
            1,
            10
        ],
        [
            10,
            30,
            .5
        ],
        [
            90,
            100,
            .25
        ],
//         [
//             400,
//             500,
//             .1
//         ]
    ];

    public array $mutation_nb_ranges = [
        [
            2,
            5
        ],
        [
            10,
            15,
            .2
        ],
//         [
//             30,
//             .1
//         ]
    ];

    public $display = 0;

    public $display_qperline = 5;

    public $display_offset = 0;

    public string $prefix = '';

    private $it;

    private \PseudoGenerator\PRanges $init_gen;

    private \PseudoGenerator\PRanges $mutation_gen;

    private \PseudoGenerator\PRanges $mutation_nb_gen;

    // ========================================================================
    function __construct(array $args)
    {
        parent::__construct($args, '');

        $queriesPaths = \array_filter_shift($this->invalidArgs, 'is_int', ARRAY_FILTER_USE_KEY);
        $queriesPath = [];

        foreach ($queriesPaths as $path) {
            $path = \getBenchmarkBasePath() . "/$path";

            if (! \is_dir($path))
                throw new \Exception("$path is not a directory");

            $queriesPath = \array_merge($queriesPath, \scandirNoPoints($path, true));
        }
        $this->queriesPath = $queriesPath;
        $this->queries = \array_map('basename', $queriesPath);
        // \array_combine($groups, \array_map('\DataSets::getAllQueries', $groups));
        $this->query_nbRewritings = [];
        $this->range = self::expandRange($this->range);
        $this->init_gen = new \PseudoGenerator\PRanges(...$this->init_ranges);
        $this->mutation_gen = new \PseudoGenerator\PRanges(...$this->mutation_ranges);
        $this->mutation_nb_gen = new \PseudoGenerator\PRanges(...$this->mutation_nb_ranges);

        // Prepare queries' range
        {
            foreach ($this->queries as $query) {

                $range = $this->range;
                $k = "$query";
                $this->query_nbRewritings[$k] = $range;
                $this->query_nbRewritings[$k]['range'] = ($range[1] - $range[0]);
                $this->query_nbRewritings[$k]['frange'] = (float) ($range[1] - $range[0] + 1);
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
            $file = new \SplFileObject($geneticModelPath, 'w');
            $this->writeGeneticModelFile($file, $model);
        }
        // must be after writeGeneticModelFile that uniquify solutions
        $nb = \count($model);
        echo "total solutions: $nb\n";
    }

    private static function expandRange(array $range): array
    {
        $c = \count($range);

        if ($c === 0)
            throw new \Exception("'range must be set");
        if ($c === 1)
            $range[] = $range[0];

        return \array_map(fn ($v) => (int) $v, $range);
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

    public function generate(string $filePath)
    {
        $writeTo = new \SplFileObject($filePath, 'w');
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
        return $this->prefix . implode('-', $s);
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
        return "php/{$this->getGeneticModelFileName()}";
    }

    private function getGeneticModelFileName(): string
    {
        return "{$this->getSuffixName()}.php";
    }

    public function getOutputFileName(): string
    {
        $s = $this->getSuffixName();
        return "{$s}";
    }

    private function displayOne($p)
    {
        $nb = 0;

        foreach ($p[self::i_qdistance] as $q => $dist) {

            if ($nb ++ === $this->display_qperline) {
                echo "\n";
                $nb = 1;
            }
            printf("|$q:%7.2f", $dist);
        }

        echo "\n";

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

        foreach ($this->queriesPath as $k => $fpath) {

            if (! \is_file($fpath))
                continue;

            $query = $this->queries[$k];
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

    private function initHPopulation(): array
    {
        $max = \max(\array_merge(...$this->init_ranges));

        $ret = [];

        while ($max > 0) {
            $one = [];

            foreach ($this->labels as $label)
                $one[$label] = \max(1, $max --);

            $one = [
                'o' => $one
            ];
            $this->updateOne($one);

            $ret[] = $one;
        }
        $this->updateNotes($ret);
        return $ret;
    }

    private function initPopulation(int $nb): array
    {
        $ret = [];

        while ($nb -- > 0) {
            $one = [];

            foreach ($this->labels as $label)
                $one[$label] = $this->init_gen->rand();

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
        echo $this->getObjectArgs()->display();
        echo "Try $this->currentTry/$this->nbTry\n";

        $solutions = [];
        $population = $this->initPopulation($this->n);
        // $population = \array_merge($population, $this->initHPopulation());
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
        $fresh = $this->initPopulation($this->fresh_nb);
        $childs = $this->reproduction(\array_merge($select, $fresh), $this->reproduction_nb);

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

        // $minv = \min($p[self::i_qdistance]);
        // $add = ($minv < 0) ? - 1 : 1;
        $nbMut = $this->mutation_nb_gen->rand();

        for (; $nbMut;) {
            $add = \mt_rand(0, 1) ? - 1 : 1;
            $label = self::randomItem($this->labels);
            $val = &$one[$label];
            $addf = $this->mutation_gen->rand();
            $val = \max(1, $val + $add * $addf);
            $nbMut --;
        }
        $this->updateOne($p);
    }

    private function mutate(&$population, int $nb): void
    {
        while ($nb -- > 0) {
            $this->mutateOne($population[\array_rand($population)]);
        }
    }
};