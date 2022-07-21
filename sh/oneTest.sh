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

output="$1"
shift

cmd="${1:-querying}"
shift

summaries="$@"

if [ -z "$summaries" ] ; then
	default=""
	summaries="${SUMMARIES:-$default}"
fi
summaries=$(printf '%q' "[$summaries]")

command="$script $command '$dataset' cmd: '$cmd' summary: $summaries output: '$output' $PARAMS \;"
echo $"$command"
