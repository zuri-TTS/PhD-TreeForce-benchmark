#!/bin/bash

com=$(sh/benchmark.sh 'DBLP[simplified,prefix-colls]' 'outputs/DBLP.colls' $*)
eval "php $com"
