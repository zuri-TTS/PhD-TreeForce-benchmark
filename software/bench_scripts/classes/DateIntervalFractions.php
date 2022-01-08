<?php

class DateIntervalFractions extends DateInterval
{
    public $milliseconds;

    public function __construct($interval_spec)
    {
        $this->milliseconds = 0;
        $matches = array();
        preg_match_all("#([0-9]*[.,]?[0-9]*)[S]#", $interval_spec, $matches);

        foreach ($matches[0] as $result) {
            $original = $result;
            $explode = explode(".", substr($result, 0, - 1));

            if (count($explode) != 2)
                continue;

            list ($seconds, $milliseconds) = $explode;
            $this->milliseconds = $milliseconds / pow(10, strlen($milliseconds) - 3);
            $interval_spec = str_replace($original, $seconds . "S", $interval_spec);
        }
        parent::__construct($interval_spec);
    }
}