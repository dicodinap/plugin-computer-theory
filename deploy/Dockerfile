# GraphitoUBB — imagen Moodle 4.5 autocontenida con los 3 plugins horneados.
#
# Build context DEBE ser la raíz del repo (donde viven local/ mod/ qtype/):
#   docker build -f deploy/Dockerfile -t graphitoubb-moodle .
# (el docker-compose de deploy/ ya lo hace con context: ..)

ARG MOODLE_PHP_TAG=8.3
FROM moodlehq/moodle-php-apache:${MOODLE_PHP_TAG}

# Rama estable de Moodle a clonar. MOODLE_405_STABLE = Moodle 4.5 LTS,
# que es lo que exigen los version.php de los plugins (requires 2024100700).
ARG MOODLE_BRANCH=MOODLE_405_STABLE

# --- 1. Código base de Moodle ---------------------------------------------
# WORKDIR / porque la imagen base usa /var/www/html como cwd; si lo borramos
# estando dentro, git falla con "Unable to read current working directory".
WORKDIR /
RUN rm -rf /var/www/html && \
    git clone --depth 1 --branch ${MOODLE_BRANCH} \
        https://github.com/moodle/moodle.git /var/www/html
WORKDIR /var/www/html

# --- 2. Plugins GraphitoUBB (horneados) -----------------------------------
# Ojo con la ruta de qtype: en Moodle va en question/type/, no en qtype/.
COPY local/graphitoubb /var/www/html/local/graphitoubb
COPY mod/graphitoubb   /var/www/html/mod/graphitoubb
COPY qtype/graphitoubb /var/www/html/question/type/graphitoubb

# --- 3. Config por variables de entorno + entrypoint ----------------------
COPY deploy/config.php     /var/www/html/config.php
COPY deploy/entrypoint.sh  /usr/local/bin/graphitoubb-entrypoint.sh
RUN chmod +x /usr/local/bin/graphitoubb-entrypoint.sh && \
    chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/graphitoubb-entrypoint.sh"]
CMD ["apache2-foreground"]
