#!/bin/bash

scriptName=$(basename -s '.sh' "$0")
groups="10M"
group2=""
id=pid
filter=
parallel=-parallel
strPrefSize=0
nbMeasures=5
nbForget=1
more=

export SUMMARIES="'' label path"
export PARAMS="Psummary.filter.types=y Psummary.filter.stringValuePrefix=$strPrefSize Ppartition.id=$id Pquerying.filter=$filter $parallel bench-measures-nb=$nbMeasures bench-measures-forget=$nbForget +clean-db"

[ "$parallel" = "-parallel" ] && parallel="" || parallel="-parallel"
[ "$id" = "_id" ] && id="" || id="-$id"
[ -z "$filter" ] || filter="-$filter"
[[ "$strPrefSize" == "" || "$strPrefSize" == "0" ]] && strPrefSize="" || strPrefSize="-prefix_$strPrefSize"


for group in $groups
do
  out="outputs/$nbMeasures/$group$id$parallel$strPrefSize$filter$more"
  com=$(sh/oneTest.sh "$group$group2[simplified]" "$out" $*)

  mkdir -p "$out"
  eval "php $com"
 # php software/bench_scripts/load_xml.php $group[simplified] -generate +pre-clean-db
done

