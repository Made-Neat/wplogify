FROM php:8.3.9-fpm

# Copy the PHP configuration file into the container.
COPY wp-logify-php.ini /usr/local/etc/php/conf.d/

# Copy the custom PHP-FPM configuration file into the container.
COPY wp-logify-php-fpm.conf /usr/local/etc/php-fpm.d/

# Copy the custom PHP-FPM configuration file into the container.
COPY phpinfo.php /var/www/html/

# Install the MySQL extension as root.
RUN docker-php-ext-install mysqli

# Install the Xdebug extension as root.
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install WP-CLI.
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Copy the start.sh script into the container.
COPY wp-logify-start.sh /usr/local/bin/

# Make sure the script is executable.
RUN chmod +x /usr/local/bin/wp-logify-start.sh

# Use the start.sh script as the entrypoint
ENTRYPOINT ["wp-logify-start.sh"]
