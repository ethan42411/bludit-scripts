#!/bin/bash
# Requirements: git

DIR="repos"
if [ -d "$DIR" ]; then
    rm -rf $DIR
fi
mkdir $DIR && cd $DIR

while read repo; do
    git clone --depth 1 "$repo"
done <../repos.txt
