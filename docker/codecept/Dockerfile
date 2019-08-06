ARG BUILD_PHP_VERSION=7.3

FROM php:${BUILD_PHP_VERSION}-cli-buster

LABEL maintainer="luca@theaveragedev.com"

WORKDIR /project

COPY ./codecept.sh /usr/local/bin/codecept

RUN chmod +x /usr/local/bin/codecept

ENTRYPOINT ["/usr/local/bin/codecept"]
