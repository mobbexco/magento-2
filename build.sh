#!/bin/sh
ver="3.2.1"

# Compress archive
if type 7z > /dev/null; then
    7z a -tzip "mobbex.$ver.mag-2.zip" * -xr!.git -xr!.vscode -x!*.zip -x!build.sh -x!README.md -x!.gitignore
elif type zip > /dev/null; then
    zip mobbex.$ver.mag-2.zip -r * -x .git .vscode *.zip build.sh README.md .gitignore
fi