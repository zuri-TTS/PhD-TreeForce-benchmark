#!/bin/bash

# This file is used by the main script doTheTest.sh

export_format="$1"
shift

if [[ $export_format != "csv" && $export_format != "dlgp" ]]; then
	echo "Unknown export format '$export_format'" >&2
	exit 1
fi

wd=$(pwd)
scriptDir=$(dirname "$(realpath $0)")

jar="$scriptDir/hi-rule-0.1-SNAPSHOT.jar"
configFile="$scriptDir/export_tmp"

while [[ 0 < $# ]] ; do
  file="$1"
  shift
  fname="$(basename "$file" ".json")"
  relbdir="$(dirname "$file")"
  absbdir="$(realpath "$relbdir")"

  outputDirName="$fname.json.$export_format"
  outputPath="$relbdir/$outputDirName"


config=$(cat << EOD
base.path=$absbdir
export.trees.input.file=file:///\${base.path}/$fname.json
export.format=$export_format
output.measures=std://out
export.trees.pattern.default=$outputDirName/%1\$s.%2\$s
EOD
)
	echo "Processing $file"
  	echo "$config" > "$configFile"

  	cmd="java -jar '$jar' export -c '$configFile'"
  	eval "$cmd"
  
	if [[ "$export_format" == "csv" ]]; then
		rm "$outputPath/root.csv"
		subDir="$outputPath/files"
		echo "Move csv files into $subDir"

		if [[ -d "$subDir" ]]; then
			rm -r "$subDir"
		fi

		mkdir "$subDir"
		mv $outputPath/*.csv "$subDir"

 		bash $scriptDir/csv_makeconf.sh "$subDir" &&
 		bash "$outputPath/conf.sh" "$outputPath" > "$outputPath/conf"
	fi
done
