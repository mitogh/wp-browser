ARG BUILD_PHP_VERSION=7.3

FROM php:${BUILD_PHP_VERSION}-cli-buster

LABEL maintainer="luca@theaveragedev.com"

# Install required system packages
RUN apt-get update && \
    apt-get -y install \
            git \
            zlib1g-dev \
			libzip-dev \
            libssl-dev \
            default-mysql-client \
            sudo less \
            libpng-dev \
        --no-install-recommends && \
        apt-get clean && \
        rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install php extensions
RUN docker-php-ext-install \
    bcmath \
    zip \
    gd \
    pdo_mysql

# Add mysql driver required for wp-browser
RUN docker-php-ext-install mysqli

RUN pecl install xdebug

# Configure php
RUN echo "date.timezone = UTC" >> /usr/local/etc/php/php.ini

WORKDIR /project

COPY ./codecept.sh /usr/local/bin/codecept

RUN chmod +x /usr/local/bin/codecept

ENTRYPOINT ["/usr/local/bin/codecept"]
