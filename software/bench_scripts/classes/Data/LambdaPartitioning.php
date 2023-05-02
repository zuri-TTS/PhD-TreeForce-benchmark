<?php
namespace Data;

final class LambdaPartitioning implements IPartitioning
{

    private array $partitions = [];

    private \DataSet $dataset;

    public function __construct(\DataSet $dataset, array $partitions)
    {
        $this->dataset = $dataset;
        $this->partitions = $partitions;
    }

    public function getDataSet(): \DataSet
    {
        return $this->dataset;
    }

    public function getPartitions(): array
    {
        return $this->partitions;
    }

    public function getID(): string
    {
        return $this->dataset->group_partitioning();
    }

    // ========================================================================
    public static function getBuilder(int $depth, \DataSet $dataset): IPartitioningBuilder
    {
        return new class($depth, $dataset) implements IPartitioningBuilder {

            private const SAVE_FILE = 'partition.php';

            private int $depth;

            private \DataSet $dataset;

            private IJsonLoader $jsonloader;

            public function __construct(int $depth, \DataSet $dataset)
            {
                $this->depth = $depth;
                $this->dataset = $dataset;
            }

            public function getDataSet(): \DataSet
            {
                return $this->dataset;
            }

            public function save(): IPartitioning
            {
                $tmp = [];

                foreach ($this->partitions as $p)
                    $tmp[$p->getID()] = $p->getDepthKeys();

                \wdPush($this->dataset->path());
                \printPHPFile(self::SAVE_FILE, $this->partitions);
                \wdPop();
                return new LambdaPartitioning($this->dataset, $this->partitions);
            }

            public function load(): IPartitioning
            {
                $file = $this->dataset->path() . '/' . self::SAVE_FILE;

                if (! \is_file($file))
                    $this->dataset->getJsonloader()->generateJson();

                if (! \is_file($file))
                    throw new \ErrorException("Can't find the file `$file`");

                return new LambdaPartitioning($this->dataset, include $file, $this->dataset);
            }

            private int $pid = 1;

            public function getPartitionFor(array $document): IPartition
            {
                $dkeys = \Help\Arrays::getDepthKeys($document, $this->depth);
                $key = \implode('-', \array_map(fn ($e) => \implode(',', $e), $dkeys));

                $partition = $this->partitions[$key] ?? null;

                if (null === $partition) {
                    $partition = new LambdaPartition($dkeys, self::getId($dkeys), $this->pid ++);
                    $this->partitions[$key] = $partition;
                }
                return $partition;
            }

            private function getId(array $dkeys): string
            {
                $tmp = \array_unique(\array_map('\count', $dkeys));
                $isPath = \count($tmp) === 1 && \array_pop($tmp) === 1;

                if ($isPath)
                    return \implode('.', \array_map(fn ($a) => $a[0], $dkeys));

                return $this->pid;
            }
        };
    }
}