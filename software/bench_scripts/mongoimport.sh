#!/bin/bash

cd $1
files=*.json

collectionName=$(echo $1 | sed -e "s/\//_/g" -e 's:^benchmark_::' -e 's:^data_::' -e 's:_*$::')

echo $collectionName

echo "" | mongoimport -d treeforce -c $collectionName --drop

for f in $files; do
	cat $f | mongoimport -d treeforce -c $collectionName
done

