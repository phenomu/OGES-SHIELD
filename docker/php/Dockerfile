# OGES-SHIELD — image web (PHP 8.3 + Apache mod_php)
# Layout di container sengaja mirror repo: /app/{public,src,orchestrator,storage,scripts}
# supaya BASE_PATH / path tools.json / storage tetap valid tanpa patch kode.
FROM php:8.3-apache

ENV PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d

# ext: pdo_mysql (PDO), curl (settings health probe), mbstring (mbTrunc helper)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libcurl4-openssl-dev \
      libonig-dev \
      pkg-config \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql curl mbstring \
 && rm -rf /var/lib/apt/lists/*

# UID/GID 1000 disamakan dengan service orchestrator (`oges`) dan user host,
# supaya bind mount storage/ + orchestrator/config bisa ditulis kedua container.
RUN groupmod -o -g 1000 www-data \
 && usermod -o -u 1000 -g 1000 www-data

# DocumentRoot + routing front controller
COPY docker/php/oges-apache.conf /etc/apache2/sites-available/oges.conf
RUN a2dissite 000-default >/dev/null && a2ensite oges >/dev/null \
 && a2enmod rewrite >/dev/null

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-oges.ini

# Aplikasi (layout mirror repo: /app/{public,src,orchestrator,storage,scripts})
COPY . /app

RUN mkdir -p /app/storage/reports /app/storage/evidence \
 && chown -R www-data:www-data /app/storage /app/orchestrator/config \
 && chmod -R u+rwX,g+rwX /app/storage /app/orchestrator/config

COPY docker/php/entrypoint.sh /usr/local/bin/oges-entrypoint
RUN chmod +x /usr/local/bin/oges-entrypoint

WORKDIR /app
EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=25s --retries=5 \
  CMD php -r 'exit(@fsockopen("127.0.0.1", 80) ? 0 : 1);'

ENTRYPOINT ["oges-entrypoint"]
CMD ["apache2-foreground"]
