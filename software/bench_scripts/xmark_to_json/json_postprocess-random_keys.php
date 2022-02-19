<?php
if (! function_exists('replaceJsonData')) {

    function replaceJsonData(array $data, array $replacements, iterable $pseudoGenerator): array
    {
        $ret = [];

        foreach ($data as $key => $val) {
            $replacement = $replacements[$key] ?? null;

            if ($replacement == null) {

                if (is_array($val))
                    $val = replaceJsonData($val, $replacements, $pseudoGenerator);

                $ret[$key] = $val;
            } else {
                $nbRepl = count($replacement);
                $index = ($pseudoGenerator->current() >> 8) % $nbRepl;
                $replacement = $replacement[$index];
                $pseudoGenerator->next();

                $ret[$replacement] = is_array($val) ? //
                replaceJsonData($val, $replacements, $pseudoGenerator) : //
                $val;
            }
        }
        return $ret;
    }
}

return function (DataSet $dataSet, XMark2Json $converter): callable {
    // We define an internal pseudo number generator to permit to generate an identical set of data for different persons.
    $pseudoGenerator = new \PseudoGenerator\OfInt($converter->getSeed());
    $replacements = $converter->getRelabellings($dataSet);

    return function (array $data) use ($replacements, $pseudoGenerator) {
        return replaceJsonData($data, $replacements, $pseudoGenerator);
    };
};

