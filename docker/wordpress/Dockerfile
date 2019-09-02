FROM wordpress:php5.6

LABEL maintainer="luca@theaveragedev.com"

COPY ./vendor/wordpress/wordpress /var/www/html

RUN chown -R www-data:www-data /var/www/html

COPY ./docker/wordpress/wp-cli.yml /wp-cli.yml

COPY . /project

