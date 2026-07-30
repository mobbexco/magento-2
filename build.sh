#!/bin/sh
ver="5.0.2"

set -e

clean() {
    rm -rf vendor composer.lock tmp tmp-composer
}

# Standalone zip: php-plugins-sdk nested inside the module, autoload wired
# manually via registration.php, for installs without Composer.
build_standalone() {
    clean

    # Backup files
    mkdir -p tmp
    cp composer.json registration.php tmp

    # Require autoload on registration.php
    printf "\nrequire_once __DIR__ . '/vendor/autoload.php';" >> registration.php
    perl -i -0777pe 's/"autoload".*},/"autoload": {},/s' composer.json

    # Install dependencies
    composer install --no-dev

    # Compress archive
    if type 7z > /dev/null 2>&1; then
        7z a -tzip "mobbex.$ver.mag-2.zip" * -xr!.git -xr!.vscode -xr!tmp -x!*.zip -x!build.sh -x!README.md -x!.gitignore
    elif type zip > /dev/null 2>&1; then
        zip mobbex.$ver.mag-2.zip -r * -x .git/\* .vscode/\* "tmp/*" tmp \*.zip build.sh README.md .gitignore
    fi

    # Restore temporal and dep files
    mv -f tmp/* ./
    rm -rf tmp vendor composer.lock
}

# Composer zip: mobbexco/magento-2 and mobbexco/php-plugins-sdk as sibling
# packages, matching the layout `composer require mobbexco/magento-2` leaves
# under vendor/mobbexco/. Unzipping this and dropping its contents into
# vendor/mobbexco/ should be byte-equivalent to that real install.
build_composer() {
    clean

    # Fetch php-plugins-sdk the same way Composer does for a real install
    # (unpatched composer.json/registration.php, pinned version's dist archive)
    composer install --no-dev

    mkdir -p tmp-composer/magento-2
    git archive HEAD | tar -x -C tmp-composer/magento-2
    mv vendor/mobbexco/php-plugins-sdk tmp-composer/php-plugins-sdk

    (
        cd tmp-composer
        if type 7z > /dev/null 2>&1; then
            7z a -tzip "../mobbex.$ver.mag-2.composer.zip" magento-2 php-plugins-sdk
        elif type zip > /dev/null 2>&1; then
            zip -r "../mobbex.$ver.mag-2.composer.zip" magento-2 php-plugins-sdk
        fi
    )

    rm -rf tmp-composer vendor composer.lock
}

case "$1" in
    standalone) build_standalone ;;
    composer) build_composer ;;
    ""|all) build_standalone; build_composer ;;
    *) echo "Usage: $0 [standalone|composer|all]"; exit 1 ;;
esac
