<?php
namespace Args;

final class ObjectArgs
{

    private object $obj;

    private string $prefix = '';

    private array $objArgs;

    public function __construct(object $obj)
    {
        $this->obj = $obj;
        $this->makeArgs();
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