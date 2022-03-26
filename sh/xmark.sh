#!/bin/bash

scriptName=$(basename -s '.sh' "$0")

export PARAMS="Psummary.filter.types=y +post-clean-db"

com=$(sh/benchmark.sh '[simplified]' "outputs/$scriptName" $*)
eval "php $com"
