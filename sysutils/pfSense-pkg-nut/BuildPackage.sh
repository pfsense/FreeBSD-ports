#!/bin/sh

DIR=$(dirname $(readlink -f "$0"))
echo "Using directory: ${DIR}"
cd $DIR

echo "Creating new package..."
make clean 
make package 
echo "Done"

echo "Uploading new package to PFSense test server (10.0.201.150)"
scp -P 2788 work/pkg/*.txz root@10.0.201.150:.
echo "Done"

exit 0