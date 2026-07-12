FROM wordpress:php8.2-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		git \
		unzip \
		curl \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
	&& chmod +x /usr/local/bin/wp

COPY docker/entrypoint.sh /usr/local/bin/meilisearch-entrypoint.sh
RUN chmod +x /usr/local/bin/meilisearch-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["meilisearch-entrypoint.sh"]
CMD ["apache2-foreground"]
