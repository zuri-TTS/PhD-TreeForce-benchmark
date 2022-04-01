#!/bin/bash

scriptName=$(basename -s '.sh' "$0")
group=DBLP.colls

export SUMMARIES="key-type path"
export PARAMS="Psummary.filter.types=n"

com=$(sh/benchmark.sh "$group[simplified]" "outputs/$scriptName" $*)
eval "php $com"
