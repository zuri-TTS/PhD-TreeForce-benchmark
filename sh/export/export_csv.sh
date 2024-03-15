#!/bin/bash

# This file is used by the main script doTheTest.sh

script_dir=$(dirname "$(realpath $0)")

"$script_dir/export.sh" csv $@
