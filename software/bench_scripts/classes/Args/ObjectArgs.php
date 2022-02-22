<?php
namespace Args;

final class ObjectArgs
{

    private object $obj;

    private string $prefix = '';

    private array $objArgs;

    private $fmapKeyToProperty;

    public function __construct(object $obj)
    {
        $this->obj = $obj;
        $this->makeArgs();
        $this->fmapKeyToProperty = fn ($k) => $k;
    }

    public function mapKeyToProperty(callable $mapKeyToProperty): ObjectArgs
    {
        $this->fmapKeyToProperty = $mapKeyToProperty;
        return $this;
    }

    public function setPrefix(string $prefix): ObjectArgs
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function updateAndShift(array &$args): void
    {
        $myArgs = $this->get();

        foreach ($cp = $args as $k => $val) {
            $prop = $this->keyToProperty($k);

            if (\property_exists($this->obj, $prop)) {
                $this->obj->{$prop} = $val;
                unset($args[$k]);
            }
        }
    }

    public function get(): array
    {
        return $this->objArgs;
    }

    public function checkEmpty(array $args, string $msg = '')
    {
        if (! empty($args)) {

            if (empty($msg))
                $msg = "\nValid cli arguments are:\n" . \get_ob(fn () => $this->display());

            throw new \Exception("Unknown argument(s):\n" . \var_export($args, true) . $msg);
        }
    }

    private function makeArgs(): void
    {
        $this->objArgs = [];
        $keys = \array_keys(\get_object_vars($this->obj));

        if (! empty($this->prefix))
            $keys = \array_filter($args, fn ($k) => $this->isProperty($name));

        foreach ($keys as $key)
            $this->objArgs[$key] = &$this->obj->{$key};
    }

    private function isProperty(string $name): bool
    {
        $prop = \str_starts_with($name, $this->prefix);
        return \property_exists($this->obj, $prop);
    }

    private function propertyToKey(string $prop): string
    {
        return \substr($prop, strlen($this->prefix));
    }

    private function keyToProperty(string $key): string
    {
        $key = ($this->fmapKeyToProperty)($key);
        return "$this->prefix$key";
    }

    public function display(): void
    {
        foreach ($this->get() as $k => $v) {
            if (! is_scalar($v))
                $v = "#!scalar";
            elseif (\is_bool($v))
                $v = $v ? 'true' : 'false';

            $k = $this->propertyToKey($k);
            echo "$k($v)\n";
        }
    }
}