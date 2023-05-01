#!/bin/bash

scriptName=$(basename -s '.sh' "$0")


# The document-store to use (mongodb|arangodb)
[ -z ${docstore+x} ] &&
docstore=MongoDB


# The datasets to test:
## DBLP
## DBLP.colls:  DBLP with physical partitioning
## DBLP.Lcolls: DBLP with logical partitioning
## 10M|100M|1G|10G|50G: XMARK
## 10M.Pcolls:  XMARK with physical paritioning
## 10M.LPcolls: XMARK with logical paritioning
[ -z ${groups+x} ] &&
groups="10M 10M.colls"


# Precise the 'ruleset/query' to test. Let it empty for testing all rulesets and queries.
[ -z ${group2+x} ] &&
group2=


# Parallelise the evaluation of partitions (boolean arg: -parallel=false, +parallel=true)
[ -z ${parallel+x} ] &&
parallel=-


# Summaries to use
[ -z ${summaries+x} ] &&
summaries="depth label path"


# Size of the n-prefix summary.
# Let the value 0 for not using a prefix summary.
[ -z ${strPrefSize+x} ] &&
strPrefSize=0 # In our tests we used 5-prefix with label and path summaries


# Number of repetitions of a same test
[ -z ${nbMeasures+x} ] &&
nbMeasures=5

# Query execution timeout; empty or 0 for undefined
[ -z ${timeout+x} ] &&
timeout=

# Output directory
[ -z ${out+x} ] &&
out=""

[ ! -d "$out" ] && mkdir -p "$out"

#### Nothing usefull to change here

# Partition id field name
[ -z ${id+x} ] &&
id=pid
# Filter the reformulations to process (val: 'noempty', 'empty', '')
[ -z ${filter+x} ] &&
filter=
# Number of reformulations per batch.
[ -z ${qBatchSize+x} ] &&
qBatchSize=1000

[ -z ${batchesNbThreads+x} ] &&
batchesNbThreads=1
####



# More parameters to use: (boolean: +=true -=false)
## +skip-existing      Do not execute an already existing test (from a previous execution of the script).
## +clean-db           Clean the (mongodb) dataset collection before and after the tests using it.
## +clean-db-json      Clean the json files used to load the (mongodb) collection after the tests. Usefull for big datasets.
[ -z ${moreParams+x} ] &&
moreParams="-skip-existing +clean-db -clean-db-json"


export SUMMARIES="$summaries"
export PARAMS="Psummary.filter.types=y Psummary.filter.stringValuePrefix: '[$strPrefSize]' Ppartition.id: '[$id]' Pquerying.filter: '[$filter]' Pquery.batches.nbThreads: '[$batchesNbThreads]' ${parallel}parallel  Pquerying.timeout='$timeout' bench-measures-nb=$nbMeasures Pquery.batchSize: '[$qBatchSize]' documentstore: '$docstore' Pquerying.config.print=y $moreParams"

for group in $groups
do
	com=$(sh/oneTest.sh "$group/$group2[simplified]" "$out" $*)

	eval "php $com"
done

