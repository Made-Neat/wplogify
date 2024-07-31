#!/bin/bash

# Set ownership and permissions.
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;

# Run php-fpm in the foreground.
php-fpm -F
