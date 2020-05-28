#!/bin/bash
# Requirements: git

DIR="repos"
if [ -d "$DIR" ]; then
    rm -rf $DIR
    mkdir $DIR && cd $DIR
fi

while read repo; do
    git clone --depth 1 "$repo"
done <../repos.txt
