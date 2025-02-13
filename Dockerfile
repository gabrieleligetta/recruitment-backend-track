# ──────────────────────────────────────────────────────────
# BASE STAGE
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm AS base

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

# Set working directory
WORKDIR /var/www/invoices_app

# Copy Composer files first to leverage caching
COPY src/composer.json src/composer.lock ./

# Build argument to toggle dev or prod dependencies
ARG APP_ENV=production
RUN echo "Building with APP_ENV=$APP_ENV"

# Install dependencies (conditional dev vs. prod)
RUN if [ "$APP_ENV" = "development" ]; then \
    composer install --no-scripts; \
  else \
    composer install --no-dev --no-scripts --no-autoloader; \
  fi

# Copy the full Laravel app
COPY src/ /var/www/invoices_app

# Optimize autoload
RUN composer dump-autoload --optimize

# ──────────────────────────────────────────────────────────
# DEVELOPMENT STAGE
# ──────────────────────────────────────────────────────────
FROM base AS development

# Set environment for dev
ENV APP_ENV=development
ENV APP_DEBUG=true

# Fix storage permissions if needed
RUN chown -R www-data:www-data /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache \
    && chmod -R 775 /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache

# Command for dev: using php-fpm
CMD ["php-fpm"]

# ──────────────────────────────────────────────────────────
# PRODUCTION STAGE
# ──────────────────────────────────────────────────────────
FROM base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

# Fix storage permissions if needed
RUN chown -R www-data:www-data /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache \
    && chmod -R 775 /var/www/invoices_app/storage /var/www/invoices_app/bootstrap/cache

CMD ["php-fpm"]
