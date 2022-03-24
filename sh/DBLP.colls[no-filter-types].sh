#!/bin/bash

scriptName=$(basename -s '.sh' "$0")

export SUMMARIES="key-type path"
export PARAMS="Psummary.filter.types=n"

com=$(sh/benchmark.sh 'DBLP[simplified,prefix-colls]' "outputs/$scriptName" $*)
eval "php $com"
