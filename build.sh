#!/bin/sh
ver="3.15.1"

# Remove installed packages
rm -rf vendor composer.lock

# Now, exit on errors
set -e

# Backup files
mkdir -p tmp
cp composer.json registration.php tmp

# Require autoload on registration.php
printf "\nrequire_once __DIR__ . '/vendor/autoload.php';" >> registration.php
perl -i -0777pe 's/"autoload".*},/"autoload": {},/s' composer.json

# Install dependencies
composer install --no-dev

# Compress archive
if type 7z > /dev/null; then
    7z a -tzip "mobbex.$ver.mag-2.zip" * -xr!.git -xr!.vscode -xr!tmp -x!*.zip -x!build.sh -x!README.md -x!.gitignore
elif type zip > /dev/null; then
    zip mobbex.$ver.mag-2.zip -r * -x .git .vscode tmp *.zip build.sh README.md .gitignore
fi

# Restore temporal and dep files
mv -f tmp/* ./
rm -r tmp vendor composer.lock