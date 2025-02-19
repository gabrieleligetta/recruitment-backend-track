# ──────────────────────────────────────────────────────────
# DEVELOPMENT STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm AS development

# Set working directory
WORKDIR /var/www/invoices_app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    nano \
    curl \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Set environment variables
ENV APP_ENV=development
ENV APP_DEBUG=true

# Copy composer files and install dependencies
COPY src/composer.json src/composer.lock ./
RUN composer install --no-scripts

# Copy Laravel application
COPY src/ /var/www/invoices_app

# Set correct permissions
RUN chown -R www-data:www-data /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache \
    && chmod -R 775 /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache

# Optimize autoload
RUN composer dump-autoload --optimize

# Command for development
CMD ["php-fpm"]

# ──────────────────────────────────────────────────────────
# PRODUCTION STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Set working directory
WORKDIR /var/www/invoices_app

# Install required dependencies
RUN apk add --no-cache bash curl zip libzip libpq

# Set environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false

# Copy Laravel application from development stage
COPY --from=development /var/www/invoices_app /var/www/invoices_app

# Remove dev dependencies and optimize autoload
RUN composer install --no-dev --no-scripts --no-autoloader \
    && composer dump-autoload --optimize

# Set correct permissions
RUN chown -R www-data:www-data /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache \
    && chmod -R 775 /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache

# Command for production
CMD ["php-fpm"]
