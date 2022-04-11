<?php
namespace Help;

final class Thing
{

    private function __construct()
    {
        throw new Error();
    }

    // ========================================================================

    /**
     * Filter some objects/things from the return set of a callable.
     *
     * @param callable $getAllThings
     *            The method to call to get all things
     * @param unknown ...$args
     *            the arguments to use to get all things, and the selected ones; the last argument must be the id representing the filtered elements.
     * @return array The selected things.
     */
    public static function allThings(callable $getAllThings, ...$args): array
    {
        $ids = \array_pop($args);
        $expand = \Help\Arrays::first($args);

        if (\is_callable($expand))
            \array_shift($args);
        else
            $expand = null;

        if (empty($ids))
            return $getAllThings(...$args);

        if (\is_string($ids))
            $ids = (array) $ids;

        $things = [];
        $allThings = $getAllThings(...$args);

        foreach ($ids as $id)
            $things = \array_merge($things, self::oneThing($id, $allThings, $expand));

        \natcasesort($things);
        return \array_unique($things);
    }

    public static function oneThing(string $id, array $allThings, ?callable $expand = null): array
    {
        $expand = $expand ?? '\Help\Thing::expand';
        $things = empty($id) ? $allThings : \explode(',', $id);
        $things = \array_map_merge(fn ($t) => $expand($t, $allThings), $things);
        return $things;
    }

    public static function expand(string $id, array $allThings): array
    {
        $filter = null;

        if ($id[0] === '!') {
            $pattern = substr($id, 1);
            $filter = fn ($p) => \fnmatch($pattern, $p);
        } elseif ($id[0] === '~') {
            $filter = fn ($p) => \preg_match($id, $p);
        }

        if (null !== $filter)
            return \array_filter($allThings, $filter);

        return (array) $id;
    }
}