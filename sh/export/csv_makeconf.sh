#!/bin/bash

# Create a conf.sh file

while [[ 0 < $# ]]; do
	dir="$1"
	shift
	[[ ! -d $dir ]] && continue;
	
	csvDir="$dir"
	parentDir=$(dirname $csvDir)
	outputFile="$parentDir/conf.sh"
	echo "# Create file $outputFile"
	echo -e \
	"#!/bin/bash\n" \
	"# Execute to create your config ; pass a cli argument to prefix the path of files\n" \
	"path=\${1='set your path with a cli argument'}" > $outputFile
	
	for file in $csvDir/*.csv
	do
		fileName=$(basename "$file" .csv)
		i=$(head -1 $file | sed 's/[^,]//g' | wc -c | xargs echo -n)
		echo "echo \"@source $fileName[$i]: load-csv(\\\"\$path/$fileName.csv\\\") .\"" >> $outputFile
	done
done
