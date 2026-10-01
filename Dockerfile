FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN mkdir -p storage/sessions storage/rate_limits storage/logs storage/jwks \
    && chmod -R 777 storage \
    && if [ -f storage/cacert.pem ]; then \
         echo "curl.cainfo=/app/storage/cacert.pem" > /usr/local/etc/php/conf.d/cams-ssl.ini \
         && echo "openssl.cafile=/app/storage/cacert.pem" >> /usr/local/etc/php/conf.d/cams-ssl.ini; \
       fi

ENV APP_ENV=production
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app"]
