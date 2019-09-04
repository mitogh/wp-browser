SHELL := /bin/bash

TRAVIS_WP_FOLDER ?= "vendor/wordpress/wordpress"
TRAVIS_WP_URL ?= "http://wp.test"
TRAVIS_WP_DOMAIN ?= "wp.test"
TRAVIS_DB_NAME ?= "test_site"
TRAVIS_TEST_DB_NAME ?= "test"
TRAVIS_WP_TABLE_PREFIX ?= "wp_"
TRAVIS_WP_ADMIN_USERNAME ?= "admin"
TRAVIS_WP_ADMIN_PASSWORD ?= "admin"
TRAVIS_WP_SUBDOMAIN_1 ?= "test1"
TRAVIS_WP_SUBDOMAIN_1_TITLE ?= "Test Subdomain 1"
TRAVIS_WP_SUBDOMAIN_2 ?= "test2"
TRAVIS_WP_SUBDOMAIN_2_TITLE ?= "Test Subdomain 2"
TRAVIS_WP_VERSION ?= "latest"
COMPOSE_FILE ?= docker-compose.yml
CODECEPTION_VERSION ?= "^2.5"
PROJECT := $(shell basename ${CURDIR})

.PHONY: wp_dump \
	cs_sniff \
	cs_fix  \
	cs_fix_n_sniff  \
	ci_before_install  \
	ci_before_script \
	ci_docker_restart \
	ci_install  \
	ci_local_prepare \
	ci_run  \
	ci_script \
	pre_commit \
	require_codeception_2.5 \
	require_codeception_3 \
	test_parallel_execution \
	pll_test \
	pll_w_failures \
	pll_docker_builds

define wp_config_extra
if ( filter_has_var( INPUT_SERVER, 'HTTP_HOST' ) ) {
	if ( ! defined( 'WP_HOME' ) ) {
		define( 'WP_HOME', 'http://' . \$_SERVER['HTTP_HOST'] );
	}
	if ( ! defined( 'WP_SITEURL' ) ) {
		define( 'WP_SITEURL', 'http://' . \$_SERVER['HTTP_HOST'] );
	}
}
endef

# PUll all the Docker images this repository will use in building images or running processes.
docker_pull:
	images=( \
		'texthtml/phpcs' \
		'composer/composer:master-php5' \
		'phpstan/phpstan' \
		'wordpress:cli' \
		'billryan/gitbook' \
		'mhart/alpine-node:11' \
		'php:5.6' \
		'selenium/standalone-chrome' \
		'mariadb:latest' \
		'wordpress:php5.6' \
		'andthensome/alpine-surge-bash' \
		'martin/wait' \
	); \
	for image in "$${images[@]}"; do \
		docker pull "$$image"; \
	done;

# Builds the Docker-based parallel-lint util.
docker/parallel-lint/id:
	docker build --force-rm --iidfile docker/parallel-lint/id docker/parallel-lint --tag lucatume/parallel-lint:5.6

# Lints the source files with PHP Parallel Lint, requires the parallel-lint:5.6 image to be built.
lint: docker/parallel-lint/id
	docker run --rm -v ${CURDIR}:/app lucatume/parallel-lint:5.6 \
		--exclude /app/src/tad/WPBrowser/Compat/PHPUnit/Version8 \
		--colors \
		/app/src

cs_sniff:
	vendor/bin/phpcs --colors -p --standard=phpcs.xml $(SRC) --ignore=src/data,src/includes,src/tad/scripts,src/tad/WPBrowser/Compat -s src

cs_fix:
	vendor/bin/phpcbf --colors -p --standard=phpcs.xml $(SRC) --ignore=src/data,src/includes,src/tad/scripts -s src tests

cs_fix_n_sniff: cs_fix cs_sniff

# Updates Composer dependencies using PHP 5.6.
composer_update: composer.json
	docker run --rm -v ${CURDIR}:/app composer/composer:master-php5 update

# Runs phpstan on the source files.
phpstan: src
	docker run --rm -v ${CURDIR}:/app phpstan/phpstan analyse -l 5 /app/src/Codeception /app/src/tad

ci_setup_db:
	# Start just the database container.
	docker-compose -f docker/${COMPOSE_FILE} up -d db
	# Wait until DB is initialized.
	docker-compose -f docker/${COMPOSE_FILE} run --rm db_waiter
	# Create the databases that will be used in the tests.
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists test_site"'
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists test"'
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists mu_subdir_test"'
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists mu_subdomain_test"'
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists empty"'

ci_setup_wp:
	# Clone WordPress in the vendor folder if not there already.
	if [ ! -d vendor/wordpress/wordpress ]; then mkdir -p vendor/wordpress && git clone https://github.com/WordPress/WordPress.git vendor/wordpress/wordpress; fi
	# Make sure the WordPress folder is write-able.
	sudo chmod -R 0777 vendor/wordpress

ci_before_install: ci_setup_db ci_setup_wp
	# Start the WordPress container.
	docker-compose -f docker/${COMPOSE_FILE} up -d wp
	# Fetch the IP address of the WordPress container in the containers network.
	# Start the Chromedriver container using that information to have the *.wp.test domain bound to the WP container.
	WP_CONTAINER_IP=`docker inspect -f '{{ .NetworkSettings.Networks.docker_default.IPAddress }}' wpbrowser_wp` \
	docker-compose -f docker/${COMPOSE_FILE} up -d chromedriver

ci_install:
	# Update Composer using the host machine PHP version.
	composer require codeception/codeception:"${CODECEPTION_VERSION}"
	# Copy over the wp-cli.yml configuration file.
	docker cp docker/wp-cli.yml wpbrowser_wp:/var/www/html/wp-cli.yml
	# Copy over the wp-config.php file.
	docker cp docker/wp-config.php wpbrowser_wp:/var/www/html/wp-config.php
	# Install WordPress in multisite mode.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp core multisite-install \
		--url=${TRAVIS_WP_URL} \
		--base=/ \
		--subdomains \
		--title=Test \
		--admin_user=${TRAVIS_WP_ADMIN_USERNAME} \
		--admin_password=${TRAVIS_WP_ADMIN_PASSWORD} \
		--admin_email=admin@${TRAVIS_WP_DOMAIN} \
		--skip-email \
		--skip-config
	# Copy over the multisite htaccess file.
	docker cp docker/htaccess wpbrowser_wp:/var/www/html/.htaccess
	# Create sub-domain 1.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp site create \
		--slug=${TRAVIS_WP_SUBDOMAIN_1} \
		--title=${TRAVIS_WP_SUBDOMAIN_1_TITLE}
	# Create sub-domain 2.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp site create \
		--slug=${TRAVIS_WP_SUBDOMAIN_2} \
		--title=${TRAVIS_WP_SUBDOMAIN_2_TITLE}
	# Update WordPress database to avoid prompts.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp core update-db \
		--network
	# Empty the main site of all content.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp site empty --yes
	# Install the Airplane Mode plugin to speed up the Driver tests.
	if [ ! -d vendor/wordpress/wordpress/wp-content/plugins/airplane-mode ]; then \
		git clone https://github.com/norcross/airplane-mode.git \
			vendor/wordpress/wordpress/wp-content/plugins/airplane-mode; \
	fi
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp plugin activate airplane-mode
	# Make sure everyone can write to the tests/_data folder.
	sudo chmod -R 777 tests/_data
	# Export a dump of the just installed database to the _data folder of the project.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp db export \
		/project/tests/_data/dump.sql

ci_before_script:
	# Build Codeception modules.
	vendor/bin/codecept build

ci_script:
	vendor/bin/codecept run acceptance
	vendor/bin/codecept run cli
	vendor/bin/codecept run climodule
	vendor/bin/codecept run functional
	vendor/bin/codecept run muloader
	vendor/bin/codecept run unit
	vendor/bin/codecept run webdriver
	vendor/bin/codecept run wpfunctional
	vendor/bin/codecept g:wpunit wploadersuite UnitWrapping
	vendor/bin/codecept run wploadersuite
	vendor/bin/codecept run wploader_multisite
	vendor/bin/codecept run wpmodule
	vendor/bin/codecept run wploader_wpdb_interaction
	docker-compose -f test_runner.compose.yml run waiter
	docker-compose -f test_runner.compose.yml run test_runner bash -c 'cd /project; vendor/bin/codecept run wpcli_module'

# Restarts the project containers.
ci_docker_restart:
	docker-compose -f docker/${COMPOSE_FILE} restart

# Make sure the host machine can ping the WordPress container
ensure_pingable_hosts:
	set -o allexport &&  source .env.testing &&  set +o allexport && \
	echo $${TEST_HOSTS} | \
	sed -e $$'s/ /\\\n/g' | while read host; do echo "\nPinging $${host}" && ping -c 1 "$${host}"; done

ci_prepare: ci_before_install ensure_pingable_hosts ci_install ci_before_script

ci_local_prepare: sync_hosts_entries ci_before_install ensure_pingable_hosts ci_install ci_before_script

ci_run: lint sniff ci_prepare ci_script

# Gracefully stop the Docker containers used in the tests.
down:
	docker-compose -f docker/docker-compose.yml down

# Builds the Docker-based markdown-toc util.
docker/markdown-toc/id:
	docker build --force-rm --iidfile docker/markdown-toc/id docker/markdown-toc --tag lucatume/md-toc:latest

# Re-builds the Readme ToC.
toc: docker/markdown-toc/id
	docker run --rm -it -v ${CURDIR}:/project lucatume/md-toc markdown-toc -i /project/README.md

# Produces the Modules documentation in the docs/modules folder.
module_docs: composer.lock src/Codeception/Module
	mkdir -p docs/modules
	for file in ${CURDIR}/src/Codeception/Module/*.php; \
	do \
		name=$$(basename "$${file}" | cut -d. -f1); \
		if	[ $${name} = "WPBrowserMethods" ]; then \
			continue; \
		fi; \
		class="Codeception\\Module\\$${name}"; \
		file=${CURDIR}/docs/modules/$${name}.md; \
		if [ ! -f $${file} ]; then \
			echo "<!--doc--><!--/doc-->" > $${file}; \
		fi; \
		echo "Generating documentation for module $${class} in file $${file}..."; \
		docs/bin/wpbdocmd generate \
			--visibility=public \
			--methodRegex="/^[^_]/" \
			--tableGenerator=tad\\WPBrowser\\Documentation\\TableGenerator \
			$${class} > doc.tmp; \
		if [ 0 != $$? ]; then rm doc.tmp && exit 1; fi; \
		echo "${CURDIR}/doc.tmp $${file}" | xargs php ${CURDIR}/docs/bin/update_doc.php; \
		rm doc.tmp; \
	done;

docker/gitbook/id:
	docker build --force-rm --iidfile docker/gitbook/id docker/gitbook --tag lucatume/gitbook:latest

duplicate_gitbook_files:
	cp ${CURDIR}/docs/welcome.md ${CURDIR}/docs/README.md

gitbook_install: docs/node_modules
	docker run --rm -v "${CURDIR}/docs:/gitbook" lucatume/gitbook gitbook install

gitbook_serve: docker/gitbook/id duplicate_gitbook_files module_docs gitbook_install
	docker run --rm -v "${CURDIR}/docs:/gitbook" -p 4000:4000 -p 35729:35729 lucatume/gitbook gitbook serve --live

gitbook_build: docker/gitbook/id duplicate_gitbook_files module_docs gitbook_install
	docker run --rm -v "${CURDIR}/docs:/gitbook" lucatume/gitbook gitbook build . /site
	rm -rf ${CURDIR}/docs/site/bin

remove_hosts_entries:
	echo "Removing project ${PROJECT} hosts entries (and backing up /etc/hosts to /etc/hosts.orig...)"
	sudo sed -i.orig '/^## ${PROJECT} project - Start ##/,/## ${PROJECT} project - End ##$$/d' /etc/hosts

sync_hosts_entries: remove_hosts_entries
	echo "Adding project ${project} hosts entries..."
	set -o allexport &&  source .env.testing &&  set +o allexport && \
	sudo -- sh -c "echo '## ${PROJECT} project - Start ##' >> /etc/hosts" && \
	sudo -- sh -c "echo '127.0.0.1 $${TEST_HOSTS}' >> /etc/hosts" && \
	sudo -- sh -c "echo '## ${PROJECT} project - End ##' >> /etc/hosts"

# Export a dump of WordPressdatabase to the _data folder of the project.
wp_dump:
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli wp db export \
		/project/tests/_data/dump.sql

pre_commit: lint cs_sniff

require_codeception_2.5:
	rm -rf composer.lock vendor/codeception vendor/phpunit vendor/sebastian \
		&& composer require codeception/codeception:^2.5

require_codeception_3:
	rm -rf composer.lock vendor/codeception vendor/phpunit vendor/sebastian \
		&& composer require codeception/codeception:^3.0


build_test_containers:
	docker-compose build --build-arg BUILD_XDEBUG_ENABLE=0 test_runner

build_test_containers_w_xdebug:
	docker-compose build --build-arg BUILD_XDEBUG_ENABLE=1 test_runner

build_wordpress_container:
	docker-compose build wp

wordpress_setup:
	# Stop and remove any previous database instance.
	if [[ "$$(docker ps | grep wp_install_db)" != '' ]]; then docker stop wp_install_db; fi
	# Spin up a MariaDB container to install, configure and generate the WordPress dump.
	docker run \
			-p 4406:3306 \
			--name wp_install_db \
			-e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
			-e MYSQL_DATABASE=wordpress \
			--rm -d \
			mariadb
	# Remove any previuosly installed version and prepare t
	if [ -d vendor/wordpress/wordpress ]; then \
		rm -rf vendor/wordpress/wordpress && mkdir -p vendor/wordpress/wordpress; \
	fi
	# Download WordPress in the vendor folder.
	vendor/bin/wp core download --path=${CURDIR}/vendor/wordpress/wordpress
	# Wait for the db container to come up.
	docker run --link wp_install_db:db -e TARGETS=db:3306 waisbrot/wait
	# Create a configuration file; temporarily pointing to the database above.
	vendor/bin/wp config create \
		--path=${CURDIR}/vendor/wordpress/wordpress \
		--dbname=wordpress \
		--dbuser=root \
		--dbpass= \
		--dbhost=127.0.0.1:4406 \
		--dbprefix=wp_ \
		--force
	# Reset the database.
	vendor/bin/wp db reset --yes --path=${CURDIR}/vendor/wordpress/wordpress
	# Install WordPress in multisite mode.
	vendor/bin/wp core multisite-install \
		--path=${CURDIR}/vendor/wordpress/wordpress \
		--url=http://wp \
		--base=/ \
		--subdomains \
		--title=Test \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@wp.test \
		--skip-email
	# Copy over the multisite htaccess file.
	cp docker/wordpress/htaccess vendor/wordpress/wordpress/.htaccess
	# Copy over wp-cli configuration file.
	cp docker/wordpress/wp-cli.yml ${CURDIR}/vendor/wordpress/wordpress/wp-cli.yml
	# Create sub-domain 1.
	vendor/bin/wp site create --path=${CURDIR}/vendor/wordpress/wordpress --slug=test1 --title="Test 1"
	# Create sub-domain 2.
	vendor/bin/wp site create --path=${CURDIR}/vendor/wordpress/wordpress --slug=test2 --title="Test 2"
	# Update WordPress database to avoid prompts.
	vendor/bin/wp core update-db --network --path=${CURDIR}/vendor/wordpress/wordpress
	# Empty the main site of all content.
	vendor/bin/wp site empty --yes --uploads --path=${CURDIR}/vendor/wordpress/wordpress
	# Make sure everyone can read/write/execute the tests/_data folder and the WordPress folder.
	# This is a cheap work-around for how users work differently on Linux and Mac/Windows version of Docker.
	# This is not ideal, it's a security kludge, but it's fine in testing.
	sudo chmod -R 777 tests/_data
	sudo chmod -R 777 vendor/wordpress/wordpress
	# Export a dump of the just installed database to the _data folder of the project.
	vendor/bin/wp db export ${CURDIR}/tests/_data/dump.sql --path=${CURDIR}/vendor/wordpress/wordpress
	# Kill the installation database.
	docker stop wp_install_db
	# Copy in the configuration file overriding the one used to set up the installation.
	cp docker/wordpress/wp-config.php vendor/wordpress/wordpress/wp-config.php

wordpress_healthcheck:
	docker run --rm --network container:${PROJECT}_wp_1 alpine ping -c 1 wp

chromedriver_healthcheck:
	docker-compose run --rm chromedriver bash -c 'curl -I http://wp.test/ && curl -I http://test1.wp.test/ && curl -I http://test2.wp.test/'

unit_suites = unit
functional_suites = climodule
web_suites = webdriver

$(unit_suites): %:
	@docker-compose run --rm test_runner run $@ -f --ext DotReporter

$(functional_suites): %:
	@docker-compose run --rm wp_test_runner run $@ -f --ext DotReporter

$(web_suites): %:
	@docker-compose run --rm web_test_runner run $@ -f --ext DotReporter

pll_tests: $(unit_suites) $(functional_suites) $(web_suites)
