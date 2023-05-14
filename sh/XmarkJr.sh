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

export groups="10M$partitioning"

# All datasets
# export groups="10M$partitioning 100M$partitioning 1G$partitioning 10G$partitioning 50G$partitioning"

# restricted rulesets (comment for all rulesets)
export group2="!(1)*,!(10)*,!(100)*,!(250)*,!(500)*"


# - without parallelisation
# + with parallelisation
export parallel=-

export summaries="depth label path"


export timeout=10m
export nbMeasures=5

# Uncomment to enable 5-prefix summary (for our tests: only with 'path')
# export strPrefSize=5

export moreParams="-clean-db -clean-ds"

./sh/tests.sh
