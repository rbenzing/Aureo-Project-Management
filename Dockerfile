# Reproducible environment for the two supported deployment layouts.
#
# This is NOT a published production image. It exists because both layouts were
# only ever exercised by hand — the containers for the validation audit were
# written from scratch each time — and because the drop-in layout's protection
# lives in a .htaccess file that only a real Apache can enforce. Unit tests can
# check those rules as patterns (tests/Unit/DropInDenyRulesTest.php); only a
# running server can check them as behaviour.
#
# PHP 8.2 on purpose: it is the floor composer.json pins, so anything that works
# here works on every supported version.
FROM php:8.2-apache

# pdo and mbstring and json are already built in; pdo_mysql is not.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# unzip and git are Composer's; the rest of the image stays lean.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# AllowOverride All is the load-bearing line. Without it Apache ignores
# .htaccess, and the drop-in layout serves .env, .git/config, vendor/ and the
# rest of the repository to anyone who asks — the exact failure the deny rules
# exist to prevent. Testing that layout with AllowOverride None would prove the
# opposite of what it looks like it proves.
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/aureo.conf \
    && a2enconf aureo

# php.ini-production is the one that hides errors; matching it here means a
# misconfiguration shows up in the container rather than only in production.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/entrypoint.sh /usr/local/bin/aureo-entrypoint
RUN chmod +x /usr/local/bin/aureo-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["aureo-entrypoint"]
CMD ["apache2-foreground"]
