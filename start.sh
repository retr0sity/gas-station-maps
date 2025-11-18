#!/bin/bash

# Copy the existing composer.json from restApi if needed
cp restApi/composer.json ./

# Install dependencies
composer install --no-dev --optimize-autoloader

# Start the server from restApi directory
cd restApi
php -S 0.0.0.0:$PORT index.php