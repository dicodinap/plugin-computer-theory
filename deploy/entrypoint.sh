#!/bin/bash
# GraphitoUBB — entrypoint de producción.
# 1) prepara moodledata, 2) espera la DB, 3) instala en el primer arranque,
# 4) corre upgrade (instala/actualiza plugins) en cada arranque, 5) purga caché,
# 6) entrega el control a Apache.
set -euo pipefail

DATAROOT="${MOODLE_DATAROOT:-/var/moodledata}"
WWWDIR="/var/www/html"
SENTINEL="${DATAROOT}/.graphitoubb-installed"

echo "[graphitoubb] Preparando moodledata en ${DATAROOT}..."
mkdir -p "${DATAROOT}"
chown -R www-data:www-data "${DATAROOT}"

# --- Esperar a la base de datos -------------------------------------------
echo "[graphitoubb] Esperando a la base de datos ${MOODLE_DB_HOST:-db}:${MOODLE_DB_PORT:-5432}..."
until php -r '
    $host = getenv("MOODLE_DB_HOST") ?: "db";
    $port = getenv("MOODLE_DB_PORT") ?: "5432";
    $db   = getenv("MOODLE_DB_NAME") ?: "moodle";
    $user = getenv("MOODLE_DB_USER") ?: "moodle";
    $pass = getenv("MOODLE_DB_PASS") ?: "moodle";
    $conn = @pg_connect("host=$host port=$port dbname=$db user=$user password=$pass connect_timeout=3");
    exit($conn ? 0 : 1);
' 2>/dev/null; do
    echo "[graphitoubb]   ...aún no responde, reintentando en 3s"
    sleep 3
done
echo "[graphitoubb] Base de datos lista."

# --- Instalación en el primer arranque ------------------------------------
if [ ! -f "${SENTINEL}" ]; then
    echo "[graphitoubb] Primer arranque: instalando Moodle + plugins..."
    # Ojo: install_database.php ya es no-interactivo; NO acepta --non-interactive.
    php "${WWWDIR}/admin/cli/install_database.php" \
        --agree-license \
        --adminuser="${MOODLE_ADMIN_USER:-admin}" \
        --adminpass="${MOODLE_ADMIN_PASS:-Admin123#}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
        --fullname="${MOODLE_SITE_FULLNAME:-GraphitoUBB}" \
        --shortname="${MOODLE_SITE_SHORTNAME:-graphitoubb}"
    touch "${SENTINEL}"
    chown www-data:www-data "${SENTINEL}"
    echo "[graphitoubb] Instalación completada."

    # Idioma del sitio (default es). Descarga el language pack desde
    # download.moodle.org; si no hay salida a internet, sigue en inglés.
    MOODLE_LANG="${MOODLE_LANG:-es}"
    if [ "${MOODLE_LANG}" != "en" ]; then
        echo "[graphitoubb] Instalando language pack '${MOODLE_LANG}'..."
        if php -r '
            define("CLI_SCRIPT", true);
            require "/var/www/html/config.php";
            $controller = new \tool_langimport\controller();
            $controller->install_languagepacks(getenv("MOODLE_LANG"));
        '; then
            php "${WWWDIR}/admin/cli/cfg.php" --name=lang --set="${MOODLE_LANG}"
            echo "[graphitoubb] Idioma del sitio: ${MOODLE_LANG}."
        else
            echo "[graphitoubb] AVISO: no se pudo instalar el language pack '${MOODLE_LANG}' (¿sin internet?); el sitio queda en inglés."
        fi
    fi
else
    echo "[graphitoubb] Moodle ya instalado: corriendo upgrade..."
    php "${WWWDIR}/admin/cli/upgrade.php" --non-interactive --allow-unstable || true
fi

# --- Seed del curso demo (opcional, MOODLE_SEED_DEMO=true) -----------------
# Idempotente: en arranques posteriores solo actualiza lo que ya existe.
if [ "${MOODLE_SEED_DEMO:-false}" = "true" ]; then
    echo "[graphitoubb] Seed demo activado: creando/actualizando curso de demostración..."
    php "${WWWDIR}/mod/graphitoubb/cli/seed_demo.php" \
        --password="${MOODLE_DEMO_PASS:-DemoDay2026#}" \
        || echo "[graphitoubb] AVISO: el seed demo falló; el sitio sigue operativo (revisa los logs de arriba)."
fi

# --- Ejecuta tareas adhoc pendientes (best-effort) ------------------------
# El upgrade encola tareas adhoc (p. ej. backfill de notas del gradebook que no
# puede correr durante el propio upgrade). En este despliegue autocontenido no
# hay cron externo, así que las drenamos al arrancar para que sus efectos
# (notas ya emitidas) aparezcan de inmediato. Idempotente: si no hay tareas, sale.
echo "[graphitoubb] Ejecutando tareas adhoc pendientes..."
php "${WWWDIR}/admin/cli/adhoc_task.php" --execute || true

# --- Purga de cachés (asegura que el nuevo código se sirva) ----------------
php "${WWWDIR}/admin/cli/purge_caches.php" || true

# Los CLI de arriba corren como root; re-asegura que Apache (www-data) pueda
# escribir todo lo que dejaron en moodledata (caches, localcache, etc.).
chown -R www-data:www-data "${DATAROOT}"

echo "[graphitoubb] Arrancando Apache."
# Encadena al entrypoint de la imagen base (moodlehq): configura el
# DocumentRoot de Apache (APACHE_DOCUMENT_ROOT), el php.ini, etc.
exec /usr/local/bin/moodle-docker-php-entrypoint "$@"
