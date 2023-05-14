#!/bin/bash

# MongoDB config
export docstore=MongoDB
export qBatchSize=1000
###

# ArangoDB config (uncomment)
# export docstore=ArangoDB
# export qBatchSize=70
###


# Uncomment to enable partitioning
# partitioning=".L2"

export groups="GH22_5G$partitioning"


# - without parallelisation
# + with parallelisation
export parallel=-

export summaries="depth label path"


export timeout=10m
export nbMeasures=5

# Uncomment to enable 5-prefix summary (for our tests: only with 'path')
# export strPrefSize=5

export moreParams="Pleaf.checkTerminal=y -clean-db -clean-ds"

./sh/tests.sh
