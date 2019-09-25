FROM wordpress:php5.6

LABEL maintainer="luca@theaveragedev.com"

# Fill the /var/www/html directory with the externally set up WordPress installation.
# Due to how the WordPress container works, this will skip the setup phase of the container entirely.
COPY ./vendor/wordpress/wordpress /var/www/html

RUN chown -R www-data:www-data /var/www/html

COPY ./docker/wordpress/wp-cli.yml /wp-cli.yml
