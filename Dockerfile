FROM php:8.5-fpm

# Dipendenze di sistema (gettext-base per envsubst, usato in docker-entrypoint.sh
# per renderizzare i limiti PHP da .env; libarchive-tools per bsdtar, usato dal
# comando "sisma tabelle-f24" per estrarre l'archivio RAR delle tabelle F24
# pubblicato dall'Agenzia delle Entrate; poppler-utils per pdftocairo e
# pdftotext, usati dal comando "sisma modelli-f24" per convertire in SVG i
# modelli F24 ufficiali usati come sfondo della stampa)
RUN apt-get update && apt-get install -y unzip git libzip-dev libpng-dev libjpeg-dev libfreetype6-dev gettext-base libarchive-tools poppler-utils && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Estensioni PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install mysqli pdo pdo_mysql zip gd && docker-php-ext-enable mysqli
RUN pecl install pcov && docker-php-ext-enable pcov
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configurazione Xdebug
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY php-limits.ini.template /usr/local/etc/php/conf.d/limits.ini.template

# Script di avvio per impostare i permessi
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Sisma CLI
ENV PATH="/var/www/html/SismaFramework/Console:${PATH}"

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
