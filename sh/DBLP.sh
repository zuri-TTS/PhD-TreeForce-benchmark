#!/bin/bash

com=$(sh/benchmark.sh 'DBLP[simplified]' 'outputs/DBLP' $*)
eval "php $com"
