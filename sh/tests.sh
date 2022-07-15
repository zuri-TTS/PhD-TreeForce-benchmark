#!/bin/bash

scriptName=$(basename -s '.sh' "$0")
groups="10M"
group2=
id=pid
filter=
parallel=-parallel
strPrefSize=0
nbMeasures=5
nbForget=1
batchesNbThreads=1
qBatchSize=1000
moreParams=+clean-db

export SUMMARIES="'' label path"
export PARAMS="Psummary.filter.types=y Psummary.filter.stringValuePrefix=$strPrefSize Ppartition.id=$id Pquerying.filter: '[$filter]' Pquery.batches.nbThreads: '[$batchesNbThreads]' $parallel bench-measures-nb=$nbMeasures bench-measures-forget=$nbForget Pquery.batchSize: '[$qBatchSize]' $moreParams"

for group in $groups
do
	out=""
	com=$(sh/oneTest.sh "$group/$group2[simplified]" "$out" $*)

	eval "php $com"
done
