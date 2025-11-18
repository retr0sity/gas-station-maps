#!/bin/bash

# Install Composer if not exists
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# Install dependencies
cd restApi
composer install --no-dev --optimize-autoloader

# Start the server
php -S 0.0.0.0:$PORT index.php