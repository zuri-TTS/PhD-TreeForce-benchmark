<?php
namespace Generator;

abstract class AbstractModelGenerator implements IModelGenerator
{

    private \Args\ObjectArgs $oargs;

    protected array $invalidArgs = [];

    public function __construct(array $args, string $argPrefix = '_arg')
    {
        $this->oargs = (new \Args\ObjectArgs($this))->setPrefix($argPrefix);
        $this->oargs->updateAndShift($args);

        if (! empty($args))
            $this->invalidArgs = $args;
    }

    public function validArgs(): bool
    {
        return empty($this->invalidArgs);
    }

    public function usage(): string
    {
        $invalid = $this->invalidArgs;

        return \get_ob(function () use ($invalid) {
            
            if (! empty($invalid))
                echo "Invalid argument(s):\n" . \var_export($invalid, true), "\n";

            echo "Parameters:\n";
            $this->oargs->display();
        });
    }
    
    protected function getObjectArgs():\Args\ObjectArgs
    {
        return $this->oargs;
    }
}