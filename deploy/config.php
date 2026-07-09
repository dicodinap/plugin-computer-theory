<?php
// GraphitoUBB — config.php de producción por variables de entorno.
// Se hornea en la imagen; los valores reales llegan desde docker-compose/.env.
// No editar aquí: cambia el .env y recrea el contenedor.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = getenv('MOODLE_DB_TYPE') ?: 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST') ?: 'db';
$CFG->dbname    = getenv('MOODLE_DB_NAME') ?: 'moodle';
$CFG->dbuser    = getenv('MOODLE_DB_USER') ?: 'moodle';
$CFG->dbpass    = getenv('MOODLE_DB_PASS') ?: 'moodle';
$CFG->prefix    = getenv('MOODLE_DB_PREFIX') ?: 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport'    => getenv('MOODLE_DB_PORT') ?: '5432',
    'dbsocket'  => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

// CRÍTICO: wwwroot debe ser EXACTAMENTE el host:puerto con que abres Moodle
// en el navegador (ej. http://192.168.1.50:8080). Si no coincide, se rompen
// el login, el CSS y las sesiones.
$CFG->wwwroot   = getenv('MOODLE_WWWROOT') ?: 'http://localhost:8080';
$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// Si algún día pones un reverse proxy con TLS delante, activa estos por env.
if (getenv('MOODLE_REVERSEPROXY') === 'true') {
    $CFG->reverseproxy = true;
}
if (getenv('MOODLE_SSLPROXY') === 'true') {
    $CFG->sslproxy = true;
}

require_once(__DIR__ . '/lib/setup.php');
