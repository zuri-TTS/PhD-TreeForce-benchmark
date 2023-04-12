#!/bin/bash

scriptName=$(basename -s '.sh' "$0")

# The document-store to use (mongodb|arangodb)
docstore=MongoDB

# The datasets to test:
## DBLP
## DBLP.colls:  DBLP with physical partitioning
## DBLP.Lcolls: DBLP with logical partitioning
## 10M|100M|1G|10G|50G: XMARK
## 10M.Pcolls:  XMARK with physical paritioning
## 10M.LPcolls: XMARK with logical paritioning
groups="10M 10M.colls"

# Precise the 'ruleset/query' to test. Let it empty for testing all rulesets and queries.
group2=

# Parallelise the evaluation of partitions (boolean arg: -parallel=false, +parallel=true)
parallel=-parallel

# Summaries to use
summaries="depth label path"

# Size of the n-prefix summary.
# Let the value 0 for not using a prefix summary.
strPrefSize=0 # In our tests we used 5-prefix with label and path summaries

# Number of repetitions of a same test
nbMeasures=5

# Number of measures to forget after all repetitions of a same test.
# The (ordered) $nbForget first and last values are forget; so at the end $nbForget*2 are unused.
nbForget=1


#### Nothing usefull to change here

# Partition id field name
id=pid
# Filter the reformulations to process (val: 'noempty', 'empty', '')
filter=
# Number of reformulations per batch.
qBatchSize=1000

batchesNbThreads=1
####



# More parameters to use: (boolean: +=true -=false)
## +skip-existing      Do not execute an already existing test (from a previous execution of the script).
## +clean-db           Clean the (mongodb) dataset collection before and after the tests using it.
## +clean-db-json      Clean the json files used to load the (mongodb) collection after the tests. Usefull for big datasets.
moreParams="+skip-existing +clean-db -clean-db-json"


export SUMMARIES="$summaries"
export PARAMS="Psummary.filter.types=y Psummary.filter.stringValuePrefix: '[$strPrefSize]' Ppartition.id=$id Pquerying.filter: '[$filter]' Pquery.batches.nbThreads: '[$batchesNbThreads]' $parallel bench-measures-nb=$nbMeasures bench-measures-forget=$nbForget Pquery.batchSize: '[$qBatchSize]' documentstore: '$docstore' $moreParams"

for group in $groups
do
	out=""
	com=$(sh/oneTest.sh "$group/$group2[simplified]" "$out" $*)

	eval "php $com"
done

