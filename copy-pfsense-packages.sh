#!/bin/bash

# Set your target directory here
TARGET_DIR="/tmp/pfsense-packages"

# Make sure target directory exists
mkdir -p $TARGET_DIR

# Find and copy folders
find . -type d -name 'pfSense*' -print0 | while IFS= read -r -d '' dir; do
    cp -r "$dir" $TARGET_DIR
done
