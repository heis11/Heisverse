# =====================================================================
# HEISVERSE â€” Production-Ready PHP 8.2 + Apache Dockerfile
# =====================================================================

FROM php:8.2-apache

# Install required system packages and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules required for routing, security and caching
RUN a2enmod rewrite headers

# Configure PHP settings for media file uploads and memory
RUN { \
    echo 'upload_max_filesize = 2G'; \
    echo 'post_max_size = 2100M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 600'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.use_only_cookies = 1'; \
    echo 'session.cookie_samesite = "Lax"'; \
} > /usr/local/etc/php/conf.d/heisverse.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set correct ownership so Apache www-data user can write logs/uploads
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose HTTP port 80
EXPOSE 80

CMD ["apache2-foreground"]

