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
export PARAMS="Psummary.filter.types=y Psummary.filter.stringValuePrefix=$strPrefSize Ppartition.id=$id Pquerying.filter=$filter Pquery.batches.nbThreads=$batchesNbThreads $parallel bench-measures-nb=$nbMeasures bench-measures-forget=$nbForget Pquery.batchSize=$qBatchSize $moreParams"

[ "$parallel" = "-parallel" ] && parallel="" || parallel="-parallel"
[ "$id" = "_id" ] && id="" || id="-$id"
[ -z "$filter" ] || filter="-$filter"
[[ "$strPrefSize" == "" || "$strPrefSize" == "0" ]] && strPrefSize="" || strPrefSize="-prefix_$strPrefSize"

if [[ $batchesNbThreads < 1 ]] ; then
  batchesNbThreads=""
else
  batchesNbThreads="-t$batchesNbThreads-qb$qBatchSize"
fi

for group in $groups
do
  out="outputs/$nbMeasures/$group$id$parallel$strPrefSize$filter$batchesNbThreads"
  com=$(sh/oneTest.sh "$group/$group2[simplified]" "$out" $*)

  mkdir -p "$out"
  eval "php $com"
done
