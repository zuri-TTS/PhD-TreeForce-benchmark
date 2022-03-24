#!/bin/bash

nl=$'\n'

script=software/bench_scripts/benchmark.php
dataset="$1"
shift

output="${1:-output}"
shift

if [ ! -d $output ] ; then
	mkdir $output
fi


cmd="${1:-querying}"
shift

summaries="$@"

if [ -z "$summaries" ] ; then
	summaries="'' 'key-type' 'path'"
fi


command=$script
for summary in $summaries
do
	command="$command \\$nl '$dataset' cmd: '$cmd' summary: $summary output: $output \;"
done

echo $"$command"

