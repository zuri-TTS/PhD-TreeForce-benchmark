#!/bin/bash

# Execute one test on multiple summaries

# cli parameters
# dataset [output [cmd ...summaries]]

# ENV VARIABLES
# PARAMS: more parameters to send to the command
# SUMMARIES: summaries to use if the cli argument is not set


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
	default="'' 'key-type' 'path'"
	summaries="${SUMMARIES:-$default}"
fi


command=$script
for summary in $summaries
do
	command="$command \\$nl '$dataset' cmd: '$cmd' summary: $summary output: '$output' $PARAMS \;"
done

echo $"$command"

