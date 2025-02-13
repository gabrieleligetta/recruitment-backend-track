# ──────────────────────────────────────────────────────────
# 1. BUILD STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-cli AS build

# Install system dependencies (git, zip, etc.) and required PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# --- Leverage layer caching: Copy composer files first ---
COPY src/composer.json src/composer.lock ./

# Install dependencies (production mode). Use --no-dev if you want to skip dev dependencies
RUN composer install --no-dev --no-scripts --no-autoloader

# Now copy the full Laravel project
COPY src/ /app/

# Generate optimized autoload files
RUN composer dump-autoload --optimize


# ──────────────────────────────────────────────────────────
# 2. TEST STAGE
# ──────────────────────────────────────────────────────────
FROM build AS test

# Install dev dependencies for testing
RUN composer install --no-scripts

# Run the test suite (e.g. PHPUnit, Pest, etc.)
# Ensure the path is correct for Laravel tests
RUN vendor/bin/phpunit


# ──────────────────────────────────────────────────────────
# 3. PRODUCTION STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Install only the essential PHP extensions for production
RUN apk update && apk add --no-cache \
    libzip-dev \
    postgresql-dev \
    libpq \
    && docker-php-ext-install pdo_pgsql zip

# Create the directory for the application
WORKDIR /var/www/html

# Copy the application from the build stage
COPY --from=build /app /var/www/html

# (Laravel Example) Adjust permissions for storage and cache if necessary
# RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run php-fpm in the foreground
CMD ["php-fpm"]
