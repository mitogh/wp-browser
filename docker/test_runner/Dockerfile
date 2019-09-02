# Take the BUILD_PHP_VERSION argument into account, default to 7.3.
ARG BUILD_PHP_VERSION=7.3

# Build from the php-cli image based on Debian Buster.
FROM php:${BUILD_PHP_VERSION}-cli-buster

LABEL maintainer="luca@theaveragedev.com"

## Install required system packages.
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

## Install php extensions.
RUN docker-php-ext-install \
    bcmath \
    zip \
    gd \
    pdo_mysql

## Add mysql driver required for wp-browser
RUN docker-php-ext-install mysqli

# Install XDebug extension.
RUN pecl install xdebug

# Create the /project directory and change to it.
WORKDIR /project

# Create an executable wrapper to execute the project vendor/bin/codecept binary appending command arguments to it.
RUN echo '#! /usr/bin/env bash\n\
# Run the command arguments on the project Codeception binary.\n\
vendor/bin/codecept $@\n' \
> /usr/local/bin/codecept \
&&  chmod +x /usr/local/bin/codecept

# By default run the wrapper when the container runs.
ENTRYPOINT ["/usr/local/bin/codecept"]

# At the end to leverage Docker build cache.
COPY . /project

# Allow for the customization of other PHP parameters.
ARG BUILD_PHP_TIMEZONE=UTC
ARG BUILD_PHP_IDEKEY=PHPSTORM
ARG BUILD_XDEBUG_ENABLE=1

# Set some ini values for PHP.
# Among them configure the XDebug extension.
RUN echo "date.timezone=${BUILD_PHP_TIMEZONE:-UTC}\n\
\n\
[xdebug]\n\
zend_extension=xdebug.so\n\
xdebug.idekey=${BUILD_PHP_IDEKEY:-PHPSTORM}\n\
xdebug.remote_autostart=1\n\
xdebug.remote_enable=${BUILD_XDEBUG_ENABLE:-1}\n\
" >> /usr/local/etc/php/conf.d/99-overrides.ini

RUN cat  /usr/local/etc/php/conf.d/99-overrides.ini
