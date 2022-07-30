<?php
namespace Data;

final class PrefixPartition extends PhysicalPartition
{

    private string $prefix_s;

    private array $prefix;

    private array $prefix_regex;

    private string $cname;

    private bool $regexCheck = true;

    public function __construct(\DataSet $ds, string $collectionName, string $id, string $prefix, ?IPartitioning $logical = null)
    {
        parent::__construct($id, '', $logical);
        $this->cname = $collectionName;

        $this->prefix = \explode('.', $prefix);
        $this->prefix_s = $prefix;
        $this->prefix_regex = \array_map(fn ($k) => [
            $k,
            "q_($k)_\d+"
        ], $this->prefix);
    }

    public function setRegexCheck(bool $check)
    {
        $this->regexCheck = $check;
    }

    public function getPrefix(): string
    {
        return $this->prefix_s;
    }

    public function getCollectionName(): string
    {
        return $this->cname;
    }

    public function contains(array $data): bool
    {
        $noPrefix = (object) null;

        if ($this->regexCheck)
            $f = \array_ufollow($data, $this->prefix_regex, $noPrefix, function ($item_regex, $array) {
                list ($prefix, $regex) = $item_regex;

                if (\array_key_exists($prefix, $array))
                    return $prefix;

                $keys = [];

                foreach (\array_keys($array) as $k) {
                    if (\preg_match("#^$regex$#", $k))
                        $keys[] = $k;
                }

                if (count($keys) === 0)
                    return false;
                if (count($keys) > 1)
                    throw new \Exception("Error, more than one key to follow: " . \print_r($keys, true));

                return $keys[0];
            });
        else
            $f = \Help\Arrays::follow($data, $this->prefix, $noPrefix);

        return $f !== $noPrefix;
    }
}