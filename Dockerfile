FROM php:8.3.36-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
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

RUN docker-php-ext-install opcache && { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

RUN a2enmod rewrite

COPY config/apache.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

WORKDIR /var/www

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
# vendor pre-built locally: composer install --no-scripts --ignore-platform-reqs --no-dev
# (--ignore-platform-reqs needed for sc0vu/web3.php which declares php ^7.1 but works on 8.3)
COPY vendor      vendor
COPY web         html
COPY yii         yii
COPY requirements.php html/requirements.php

RUN chown -R www-data:www-data /var/www
