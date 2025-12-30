FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm

# Filament + Horizon + exports require these PHP extensions:
# - intl (filament/support)
# - pcntl (laravel/horizon)
# - zip (openspout/spatie backup)
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j"$(nproc)" intl pcntl zip \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

