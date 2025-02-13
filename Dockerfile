# ──────────────────────────────────────────────────────────
# 1. BUILD STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-cli AS build

# Install system dependencies
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

# Copy Composer files first for caching
COPY src/composer.json src/composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy full Laravel project
COPY src/ /app/

# Optimize autoload
RUN composer dump-autoload --optimize


# ──────────────────────────────────────────────────────────
# 2. TEST STAGE
# ──────────────────────────────────────────────────────────
FROM build AS test

# Install dev dependencies for testing
RUN composer install --no-scripts

# Run the test suite
RUN vendor/bin/phpunit


# ──────────────────────────────────────────────────────────
# 3. DEVELOPMENT STAGE
# ──────────────────────────────────────────────────────────
FROM build AS development

# Install additional dependencies for debugging & development
RUN apt-get update && apt-get install -y \
    nano \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Set environment variables for development
ENV APP_ENV=local
ENV APP_DEBUG=true
ENV XDEBUG_MODE=debug

# Expose ports for development
EXPOSE 8000

# Start Laravel in development mode
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]


# ──────────────────────────────────────────────────────────
# 4. PRODUCTION STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Install only essential PHP extensions
RUN apk update && apk add --no-cache \
    libzip-dev \
    postgresql-dev \
    libpq \
    && docker-php-ext-install pdo_pgsql zip

# Set working directory
WORKDIR /var/www/html

# Copy the application from the build stage
COPY --from=build /app /var/www/html

# Start PHP-FPM in production mode
CMD ["php-fpm"]
