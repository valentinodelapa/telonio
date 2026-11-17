# Build multi-stage: "base" contiene tutto ciò che serve all'applicativo,
# "dev" aggiunge gli strumenti di debug e coverage (Xdebug, pcov), "prod" è la
# sola base. Senza target esplicito viene costruito l'ultimo stage ("prod");
# docker-compose.dev.yml seleziona "dev".

# --- MOTORE ISA: "Il tuo ISA 2026 CPB" dell'Agenzia delle Entrate ---
# Software ufficiale per il calcolo degli indici sintetici di affidabilità
# fiscale (versione 1.0.2 dell'11/06/2026, Linux x64 con JVM), usato in
# modalità batch ("invisibile") dal modulo Dichiarativi: calcolo, esito e
# stampa dei modelli ISA allegati a Redditi. L'installer (InstallAnywhere)
# contiene un archivio zip con le librerie e la JVM: se ne verifica
# l'impronta SHA-256 pubblicata dall'Agenzia e se ne estraggono i file in
# /opt/iltuoisa/2026 (jar, risorse e JVM in jre/). Pagina del software:
# https://www.agenziaentrate.gov.it/portale/software-indici-sintetici-di-affidabilit%C3%A0-il-tuo-isa-2026-cpb
# Il server di download non invia il certificato intermedio di Sectigo
# (i browser lo scaricano dall'indirizzo indicato nel certificato del
# server): lo si scarica da Sectigo, si verifica che sia firmato da una
# radice di sistema e lo si aggiunge per il solo download.
FROM debian:trixie-slim AS iltuoisa
ARG ISA2026_URL=https://swdownload1.agenziaentrate.gov.it/pub/entrate/inst_isa2026_vm_102_x64.bin
ARG ISA2026_SHA256=3e09dbfdb380bee2d639c7f408f9e6f4b1a17a2e8a615ea20794b3f3a812abcd
ARG SECTIGO_INTERMEDIO=http://crt.sectigo.com/SectigoPublicServerAuthenticationCAOVR36.crt
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl unzip openssl && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL -o /tmp/intermedio.der "$SECTIGO_INTERMEDIO" \
    && openssl x509 -inform DER -in /tmp/intermedio.der -out /tmp/intermedio.pem \
    && openssl verify -CAfile /etc/ssl/certs/ca-certificates.crt /tmp/intermedio.pem \
    && cat /etc/ssl/certs/ca-certificates.crt /tmp/intermedio.pem > /tmp/certificati.pem \
    && curl -fsSL --cacert /tmp/certificati.pem -o /tmp/isa2026.bin "$ISA2026_URL" \
    && echo "$ISA2026_SHA256  /tmp/isa2026.bin" | sha256sum -c - \
    && mkdir -p /tmp/isa2026 && cd /tmp/isa2026 \
    && (unzip -q /tmp/isa2026.bin || [ $? -eq 1 ]) \
    && APP='/tmp/isa2026/$IA_PROJECT_DIR$/applicazione' \
    && mkdir -p /opt/iltuoisa/2026/jre \
    && cp "$APP"/libs/*.jar "$APP"/jars/*.jar /opt/iltuoisa/2026/ \
    && cp -r "$APP"/res /opt/iltuoisa/2026/res \
    && tar xzf /tmp/isa2026/'$ISA_PROJECTS_JRES$'/zulu/linux/zulu*-linux_x64.tar.gz -C /opt/iltuoisa/2026/jre --strip-components=1 \
    && /opt/iltuoisa/2026/jre/bin/java -version \
    && rm -rf /tmp/isa2026 /tmp/isa2026.bin /tmp/intermedio.* /tmp/certificati.pem

# --- BASE COMUNE ---
FROM php:8.5-fpm AS base

# Dipendenze di sistema (gettext-base per envsubst, usato in docker-entrypoint.sh
# per renderizzare i limiti PHP da .env; libarchive-tools per bsdtar, usato dal
# comando "sisma tabelle-f24" per estrarre l'archivio RAR delle tabelle F24
# pubblicato dall'Agenzia delle Entrate; poppler-utils per pdftocairo e
# pdftotext, usati dal comando "sisma modelli-f24" per convertire in SVG i
# modelli F24 ufficiali usati come sfondo della stampa; qpdf, usato dallo
# stesso comando per riscrivere senza object stream i PDF ufficiali che fanno
# da sfondo al PDF delle deleghe; catdoc per xls2csv, usato dal comando
# "sisma specifiche-dichiarativi" per leggere le specifiche tecniche degli ISA,
# pubblicate in file Excel)
RUN apt-get update && apt-get install -y unzip git libzip-dev libpng-dev libjpeg-dev libfreetype6-dev gettext-base libarchive-tools poppler-utils qpdf catdoc && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Motore ISA (stage "iltuoisa")
COPY --from=iltuoisa /opt/iltuoisa /opt/iltuoisa

# Estensioni PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install mysqli pdo pdo_mysql zip gd && docker-php-ext-enable mysqli

# Limiti PHP (renderizzati da docker-entrypoint.sh)
COPY php-limits.ini.template /usr/local/etc/php/conf.d/limits.ini.template

# Script di avvio per impostare i permessi
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Sisma CLI
ENV PATH="/var/www/html/SismaFramework/Console:${PATH}"

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# --- SVILUPPO/TEST: base + debug e coverage ---
FROM base AS dev

RUN pecl install pcov xdebug && docker-php-ext-enable pcov xdebug

# Configurazione Xdebug
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# --- PRODUZIONE: sola base ---
FROM base AS prod
