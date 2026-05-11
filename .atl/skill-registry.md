# Skill Registry — plugin-computer-theory (GraphitoUBB)

Generado: 2026-05-09

## User Skills (relevantes al proyecto)

| Skill | Trigger | Aplicabilidad |
|-------|---------|---------------|
| `prd-writer` | "draft PRD", "redactar PRD" | Para extender el PRD del plugin con secciones nuevas |
| `cognitive-doc-design` | "writing guides, READMEs, architecture docs" | Documentación del plugin, informes de tesis |
| `chained-pr` | "split into chained PRs >400 lines" | Cuando un incremento del plugin pase 400 líneas |
| `work-unit-commits` | "structuring commits as work units" | Commits del plugin (alinea con CLAUDE.md atómicos) |
| `branch-pr` | "creating PR, opening PR" | Cuando el código del plugin viva en repo git |
| `issue-creation` | "creating GitHub issue" | Issues del plugin si se publica |
| `comment-writer` | "drafting PR/review comments" | Code review interno |
| `humanizer` | "remove AI-generated writing signs" | Críticamente útil para informes de tesis |

## SDD Skills (workflow base)

`sdd-explore`, `sdd-propose`, `sdd-spec`, `sdd-design`, `sdd-tasks`, `sdd-apply`, `sdd-verify`, `sdd-archive`, `sdd-onboard`.

## Skills NO aplicables

- `frontend-design` — el frontend es AMD + Mustache de Moodle, no SPA moderna
- `go-testing` — proyecto PHP, no Go
- `supabase-postgres-best-practices` — usamos Postgres pero a través de Moodle DML API; no aplica directo

## Project Conventions

Fuentes de convenciones detectadas:
- `~/.claude/CLAUDE.md` (global) — modo enseñanza, commits atómicos sin Co-Authored-By, Engram protocol
- `docs/CONTEXT.md` (raíz del repo `informes/`) — contexto completo del proyecto GraphitoUBB
- `docs/prds/PRD.md` — requisitos del producto

Sin `agents.md`, `AGENTS.md` ni `.cursorrules` propios del subfolder `plugin-computer-theory/`.

## Compact Rules (para inyección en sub-agentes)

### Code Style (Moodle)
- PHP 8.3, namespaces tipo `local_graphitoubb\...`
- phpcs con `moodlehq/moodle-cs` — sin warnings
- Component slug en minúsculas `[a-z0-9_]+`
- Lang strings en español neutro (`lang/es/<component>.php` y `en/`)
- Boilerplate phpdoc Moodle al tope de cada archivo
- `defined('MOODLE_INTERNAL') || die();` en archivos no-clase
- BD vía DML API de Moodle (`$DB->...`), nunca SQL crudo salvo necesidad explícita
- Capabilities vacías en `local_*` y `qtype_*` (`$capabilities = [];`)

### Architecture
- Lógica de dominio (validación, simulación, grading) → `local_graphitoubb`
- `mod_graphitoubb` y `qtype_graphitoubb` son adapters delgados — delegan al núcleo
- Cada tema implementa `tool_interface` y se registra en `tool_registry`
- Agregar tema = `tool/<nombre>.php` + simulador + grader + schemas + módulo AMD; sin tocar adapters

### Security & Input (Moodle)
- Entry points (`*.php` accesibles vía URL): SIEMPRE `require_login()` y `require_capability()` cuando aplique
- Input: SOLO vía `required_param()` / `optional_param()` / `required_param_array()` con tipo (`PARAM_INT`, `PARAM_ALPHANUMEXT`, `PARAM_TEXT`, `PARAM_RAW` solo si justificado). NUNCA `$_GET`/`$_POST`/`$_REQUEST` directos
- Forms: usar `moodleform` + `sesskey()` (CSRF). En acciones destructivas, `confirm_sesskey()` o `require_sesskey()`
- AJAX/WS: declarar en `db/services.php`, `external_api` con `external_function_parameters` tipados; capabilities por función
- File uploads: `file_storage` API, nunca tocar `$_FILES` directo
- Paths: `clean_param($path, PARAM_PATH)`. Nunca concatenar input en rutas

### Output, Escaping & i18n
- Texto plano de DB → `s($value)` o `format_string()` (filtros + escape)
- HTML rico de DB → `format_text($value, $format, ['context' => $context])`
- URLs → `new moodle_url(...)` + `->out(false)` (false = sin encode HTML, para JS/AMD; true para HTML)
- Renderers: usar `$OUTPUT->render($templatable)` + Mustache. Nunca `echo "<div>$x</div>"` en código de negocio
- Strings: TODO en `lang/<lang>/<component>.php` con `get_string('key', 'component', $a)`. `$a` con placeholders nombrados (`{$a->name}`), nunca concatenar
- Sin strings hardcodeadas en UI, ni siquiera "OK"/"Error"

### DML / SQL (Moodle)
- Default: `$DB->get_record()`, `get_records()`, `insert_record()`, `update_record()`, `delete_records()`, `count_records()`
- SQL crudo solo cuando el helper no alcanza → `$DB->get_records_sql($sql, $params)` con placeholders `?` (posicional) o `:name` (nombrado). NUNCA concatenación de input
- Identificadores de tabla/columna que vengan de input → whitelist explícita, nunca pasarlos como string a SQL
- Transacciones: `$transaction = $DB->start_delegated_transaction(); ... $transaction->allow_commit();` con try/catch
- Schema: cambios SOLO vía `db/install.xml` + `db/upgrade.php` con bumps en `version.php`. Nunca ALTER manual
- Postgres-specific: el plugin debe seguir siendo cross-DB (MySQL/MariaDB/Postgres). Si necesitás algo Postgres-only, justificalo y aislalo

### Privacy API (GDPR — obligatorio si guarda datos de usuario)
- `classes/privacy/provider.php` implementando los interfaces correctos (`metadata\provider`, `core_userlist_provider`, `request\plugin\provider`)
- Declarar en metadata TODA tabla/subsistema/servicio externo donde se guardan datos de usuario
- Implementar export, delete-for-user y delete-for-context
- Si NO guarda datos personales: `null_provider` con razón

### APIs preferidas (no reinventar)
- Cron / background → **Tasks API**: `\core\task\scheduled_task` o `adhoc_task` registrados en `db/tasks.php`. NUNCA cron casero
- Reaccionar a acciones del sistema → **Events API**: observers en `db/events.php` apuntando a clases `\component\event\*` o core events
- Logging → `\core\notification` para UI; eventos para auditoría
- Caché → **MUC** (`cache::make('component', 'area')`), no `apcu_*` ni archivos manuales
- Archivos → File API (`file_storage`, `stored_file`), no filesystem directo
- HTTP externo → `\core\http_client` (Guzzle wrapper de Moodle), no `curl_*` ni `file_get_contents` con URLs

### PHP moderno (8.3)
- `declare(strict_types=1);` en TODOS los archivos de clase nuevos
- Type hints obligatorios: parámetros, retornos, properties (`public readonly int $id`)
- `final class` por default; abrir a herencia solo con razón explícita
- Constructor promotion (`public function __construct(private readonly Foo $foo) {}`)
- Enums (`enum Status: string`) en lugar de constantes sueltas o strings mágicos
- Match expressions sobre switch cuando hay retorno
- Nullsafe `?->` y null coalescing `??` en lugar de cadenas de `isset`
- Excepciones tipadas (`moodle_exception` con stringkey + component) — no genéricas

### Anti-patterns (rechazar en review)
- `$_GET`/`$_POST`/`$_REQUEST`/`$_SERVER` directos
- SQL con concatenación de variables
- `echo`/`print` de datos sin escapar
- Strings de UI hardcodeadas
- `eval()`, `extract()`, `@` (suprimir errores)
- Variables globales nuevas (`global $X` solo para los oficiales: `$DB`, `$CFG`, `$USER`, `$PAGE`, `$OUTPUT`, `$COURSE`)
- `die()`/`exit()` en medio de lógica — usar excepciones
- Lógica en templates Mustache (templates son tontos; lógica en `templatable::export_for_template`)
- Cron casero con `setInterval`/scripts externos
- Cambios de schema sin `upgrade.php` + version bump
- Helpers genéricos del estilo `utils.php` con funciones sueltas — preferí clases con responsabilidad
- Suprimir warnings de `phpcs` con comentarios en lugar de arreglar

### Testing
- PHPUnit 9.6 — tests en `tests/` por componente
- Tests deben pasar antes de cerrar tarea (29 tests AFD baseline)
- Verificar render Mustache en browser para cambios de template
- Tests usan `advanced_testcase` o `basic_testcase`; `resetAfterTest(true)` cuando tocan DB
- Generators (`tests/generator/lib.php`) para fixtures, no SQL inline en tests
- Cobertura priorizada: lógica de dominio en `local_graphitoubb` (validación, simulación, grading) > adapters

### Commits (override del default Claude Code)
- SIN footer `Co-Authored-By` ni "Generated with Claude Code"
- Atómicos: 1 commit = 1 cambio lógico
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`
- Si necesitás "y"/"and"/"también" para describir → son 2 commits

### Comunicación
- Español, registro técnico, conciso. Sin emojis. Bullets cortos.
- Modo enseñanza: explicar trade-offs y nombrar patrones cuando aplica
