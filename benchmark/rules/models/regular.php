<?php
return fn (array $args) => new class($args) implements IModelGenerator {

    private int $k = - 1, $q = - 1;

    function __construct(array $args)
    {
        $this->k = (int) \argShift($args, 'k', - 1);
        $this->q = (int) \argShift($args, 'q', - 1);
    }

    function validArgs(): bool
    {
        return $this->k > 0 && $this->q >= 0;
    }

    function usage(): string
    {
        return <<<EOT
        Generate a model where each key has the same number of labels and number of querying labels
        
        Parameters:
        k: total number of labels
        q: number of querying labels
        EOT;
    }

    function generate(\SplFileObject $writeTo)
    {
        $k = $this->k;
        $q = $this->q;

        $s = <<<EOT
        #label nbkeys nbQueryVocKey
        
        people $k $q
        person $k $q
        @id    $k $q
        
        emph $k $q
        bold $k $q
        closed_auctions $k $q
        closed_auction  $k $q
        
        regions $k $q
        africa $k $q
        item $k $q
        bold $k $q
        
        
        address $k $q
        city $k $q
        emailaddress $k $q
        homepage $k $q
        EOT;
        $writeTo->fwrite($s);
        return true;
    }

    function getOutputFileName(): string
    {
        return "reg_{$this->k}_$this->q";
    }
};