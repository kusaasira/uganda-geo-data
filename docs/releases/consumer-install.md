# Independent Composer consumer verification

This check installs a copy of Uganda Geo into a disposable consumer project
using a Composer path repository with symlinks disabled. It verifies the package
installation and autoload contract instead of relying on development autoloading.

Run in an environment with Composer 2 and PHP 8.4:

    php tools/ci/consumer-install.php
    php tools/ci/consumer-install.php --laravel

The Laravel variant installs the Laravel 12 framework and resolves the SDK
through its container without inventing a service provider. Both commands exit
nonzero on failure and clean up the disposable consumer directory.

This validates the branch as a local path package, not a stable Packagist tag.
A separate post-tag smoke test must install v2.0.0 from Packagist.
