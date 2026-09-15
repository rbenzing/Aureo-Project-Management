#!/bin/sh
set -e

# Both supported layouts run from one image; only the document root differs.
#
#   AUREO_LAYOUT=public   document root is public/     (recommended)
#   AUREO_LAYOUT=dropin   document root is the app root (shared hosting)
#
# The drop-in layout is the one worth running: it is where the repository sits
# inside the web root and the .htaccess deny rules are the only thing standing
# between a visitor and .env.
case "${AUREO_LAYOUT:-public}" in
    public)
        docroot=/var/www/html/public
        ;;
    dropin)
        docroot=/var/www/html
        ;;
    *)
        echo "AUREO_LAYOUT must be 'public' or 'dropin', got '${AUREO_LAYOUT}'" >&2
        exit 1
        ;;
esac

sed -ri "s!DocumentRoot /var/www/html!DocumentRoot ${docroot}!" /etc/apache2/sites-available/000-default.conf

# log/ and var/ must be writable by the server or LoggerService silently
# swallows every error — the first thing this project tells you to check.
# Ownership rather than chmod 777, so the container behaves like a real host.
mkdir -p /var/www/html/log /var/www/html/var/cache
chown -R www-data:www-data /var/www/html/log /var/www/html/var 2>/dev/null || true

if [ ! -d /var/www/html/vendor ]; then
    echo "vendor/ is missing. Run: docker compose run --rm app composer install" >&2
fi

exec "$@"
