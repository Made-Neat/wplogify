FROM php:8.3.9-fpm

# Switch to the root user. We should be already, but just in case.
USER root

# Install the MySQL extension as root.
RUN docker-php-ext-install mysqli

# Install the Xdebug extension as root.
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install WP-CLI.
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Change ownership of site files to www-data.
RUN chown -R www-data:www-data /var/www/html

# Switch to the non-root user.
USER www-data
