#!/bin/bash

scriptName=$(basename -s '.sh' "$0")

export PARAMS="Psummary.filter.types=n"

com=$(sh/benchmark.sh 'DBLP[simplified]' "outputs/$scriptName" $*)
eval "php $com"
