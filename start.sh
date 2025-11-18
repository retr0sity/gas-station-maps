#!/bin/bash

# Navigate to the restApi directory
cd restApi

# Install PHP dependencies using Composer
composer install --no-dev --optimize-autoloader

# Start the PHP built-in server
php -S 0.0.0.0:$PORT index.php