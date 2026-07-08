# Desplegar GraphitoUBB (Moodle 4.5) en un servidor con Docker

Paquete **autocontenido**: construye una imagen de Moodle 4.5 con los tres
plugins de GraphitoUBB (`local_`, `mod_`, `qtype_`) **horneados dentro**, más
PostgreSQL. No depende de rutas locales, así que funciona en cualquier VPS Linux
con Docker.

> Esto es distinto de `~/moodle-docker` (tu entorno de desarrollo, que
> _bind-montea_ los plugins desde tu disco). Aquí el código va dentro de la
> imagen.

## Requisitos en el servidor

- Docker Engine + plugin `compose` (`docker compose version` debe responder).
- Puerto elegido (por defecto `8080`) abierto en el firewall si accederás por IP.

## Pasos

```bash
# 1. Clona el repo en el servidor
git clone https://github.com/dicodinap/plugin-computer-theory.git
cd plugin-computer-theory/deploy

# 2. Configura
cp .env.example .env
nano .env        # edita MOODLE_WWWROOT y las contraseñas (ver abajo)

# 3. Construye y levanta
docker compose up -d --build

# 4. Sigue el arranque (instala Moodle + plugins en el primer boot)
docker compose logs -f web
```

Cuando veas `[graphitoubb] Arrancando Apache.`, abre en el navegador la URL
exacta que pusiste en `MOODLE_WWWROOT` y entra con el usuario/clave de admin.

## El único ajuste que NO puedes equivocar: `MOODLE_WWWROOT`

Moodle exige que `wwwroot` sea **idéntico** al host:puerto con que abres el
sitio. Si el servidor tiene IP interna `192.168.1.50` y publicas en el puerto
`8080`:

```env
MOODLE_WWWROOT=http://192.168.1.50:8080
MOODLE_HTTP_PORT=8080
```

Si luego cambias la IP/puerto: edita `.env`, luego
`docker compose up -d` para recrear el contenedor (no basta reiniciar).

## Operación

```bash
docker compose ps                 # estado
docker compose logs -f web        # logs de Moodle
docker compose down               # detener (conserva datos en volúmenes)
docker compose down -v            # detener y BORRAR datos (¡destructivo!)
```

## Actualizar los plugins tras cambiar código

```bash
git pull
docker compose up -d --build      # reconstruye imagen; el entrypoint corre upgrade.php
```

El `entrypoint` detecta que Moodle ya está instalado, ejecuta
`admin/cli/upgrade.php` (aplica nuevas versiones de los plugins) y purga cachés.

## Idioma

`MOODLE_LANG=es` (default) instala el language pack español en el primer
arranque (descarga desde download.moodle.org, requiere internet) y lo fija como
idioma del sitio. Si la descarga falla, el sitio queda operativo en inglés y
puedes instalar el pack luego desde Administración del sitio → Idioma.

## Curso demo (demo day)

Con `MOODLE_SEED_DEMO=true` en `.env` (el default del `.env.example`), en cada
arranque el entrypoint ejecuta `mod/graphitoubb/cli/seed_demo.php`, que crea
—de forma idempotente— el curso **"Estructuras Discretas — Demo GraphitoUBB"**:

- Una sección por herramienta con ejercicios curados del catálogo de presets:
  tablas de verdad (3), Karnaugh (3), relaciones (2), grafos (5), árboles (3)
  y AFD (3).
- Un quiz construido con preguntas `qtype_graphitoubb` del banco precargado
  (7 preguntas, una por sabor de herramienta).
- Usuarios demo ya matriculados: `profesor.demo` (profesor con edición),
  `estudiante.demo` y `estudiante2.demo` (estudiantes). La contraseña de los
  tres es `MOODLE_DEMO_PASS`.
- **Intentos calificados de ejemplo** (`--grades=1`, default): Ana
  (`estudiante.demo`) resuelve bien 4 actividades (Karnaugh, grafo, árbol,
  relaciones → 100%) y Benjamín (`estudiante2.demo`) comete errores clásicos
  (40%, 0%, 40%, 85%), pasando por los graders reales. Así el Informe del
  calificador, el informe docente y la vista del alumno ya tienen datos para
  mostrar en la demo.

Para el piloto real: pon `MOODLE_SEED_DEMO=false` (el curso ya creado no se
borra) y gestiona usuarios reales por CSV o a mano. También puedes correr el
seed manualmente:

```bash
docker compose exec web php mod/graphitoubb/cli/seed_demo.php --help
```

## Persistencia

- `db_data`   → base de datos PostgreSQL.
- `moodledata`→ archivos de Moodle (`/var/moodledata`) y el sentinel de
  instalación. Sobreviven a `down`/`up`; se borran solo con `down -v`.

## Notas de seguridad (importante para exponer más allá de la LAN)

Este setup es **HTTP plano, pensado para red interna / IP**. Si algún día lo
publicas a Internet, pon delante un reverse proxy con HTTPS (Caddy/Traefik/nginx)
y activa en `.env`:

```env
MOODLE_REVERSEPROXY=true
MOODLE_SSLPROXY=true
```

(ajustando `MOODLE_WWWROOT` a `https://tu-dominio`). Y cambia **todas** las
contraseñas por defecto.
