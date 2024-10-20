FROM php:8.0.30-fpm

# Copy the PHP configuration file into the container.
COPY logify-wp-php.ini /usr/local/etc/php/conf.d/

# Copy the custom PHP-FPM configuration file into the container.
COPY logify-wp-php-fpm.conf /usr/local/etc/php-fpm.d/

# Copy the phpinfo.php file into the container.
COPY phpinfo.php /var/www/html/

# Install necessary system dependencies and PHP extensions.
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && docker-php-ext-install zip intl

# Install the MySQL extension.
RUN docker-php-ext-install mysqli

# Install the Xdebug extension.
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install WP-CLI.
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Copy the start.sh script into the container.
COPY logify-wp-start.sh /usr/local/bin/

# Make sure the script is executable.
RUN chmod +x /usr/local/bin/logify-wp-start.sh

# Use the start.sh script as the entrypoint
ENTRYPOINT ["logify-wp-start.sh"]
