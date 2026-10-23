# Target:
#   docker build --target prod -t napay/poawallet:<versione> .   (default se --target omesso)
#   docker build --target dev  -t napay/poawallet:dev .
# Configurazione e segreti solo a runtime da variabili d'ambiente (.env, vedi .env.example).

FROM php:8.3.36-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libpng-dev \
    libgmp-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl pdo_mysql gd zip bcmath gmp sockets opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
    && a2enmod rewrite

COPY config/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www

# --- dipendenze composer (solo build, composer non finisce nell'immagine finale) ---
FROM base AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
# --ignore-platform-reqs: sc0vu/web3.php e bacon/bacon-qr-code dichiarano php ^7 ma girano su 8.3
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    --optimize-autoloader --ignore-platform-reqs

FROM vendor AS vendor-dev
RUN composer install --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# --- sorgenti applicazione ---
FROM base AS app

COPY assets      assets
COPY commands    commands
COPY components  components
COPY config      config
COPY controllers controllers
COPY daemons     daemons
COPY mail        mail
COPY logs        logs
COPY messages    messages
COPY migrations  migrations
COPY models      models
COPY runtime     runtime
COPY views       views
COPY widgets     widgets
COPY web         html
COPY yii         yii

# --- sviluppo: debug/gii, errori a video ---
FROM app AS dev

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=vendor-dev /var/www/vendor vendor
COPY requirements.php html/requirements.php
ENV YII_ENV=dev YII_DEBUG=1
RUN chown -R www-data:www-data /var/www

# --- produzione (ultimo stage = default) ---
FROM app AS prod

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=vendor /var/www/vendor vendor
ENV YII_ENV=prod YII_DEBUG=0
# codice di proprieta root; scrivibili da Apache solo runtime, asset pubblicati e log
RUN chown -R www-data:www-data /var/www/runtime /var/www/html/assets /var/www/logs
