#!/bin/bash

export docstore=ArangoDB
export groups="10M 100M 1G"
export group2="!(1)*,!(10)*,!(100)*,!(250),!(500)*"

export parallel=-
export summaries=depth

export qBatchSize=70
export timeout=15m
export nbMeasures=5

export moreParams="+clean-db +clean-db-json"

./sh/tests.sh
