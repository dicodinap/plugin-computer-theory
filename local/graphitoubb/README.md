# local_graphitoubb — Tool Registry

Tool registry infrastructure for the GraphitoUBB plugin family.

## Purpose

Provides a shared registry that decouples tool implementations from the host plugins that consume them. Host plugins (`mod_graphitoubb`, `qtype_graphitoubb`) request tools by ID; they never instantiate tool classes directly.

## Public API

### `tool_interface`

Every tool MUST implement `local_graphitoubb\tool_interface`. Key methods:

| Method | Returns | Description |
|---|---|---|
| `get_id()` | `string` | Stable machine ID (`[a-z0-9_]+`) |
| `get_name()` | `string` | Human label (lang string key) |
| `get_version()` | `int` | Domain contract version — bump when persistence shape changes |
| `get_capabilities()` | `array` | Feature flags (`edit`, `simulate`, `snapshot`, `wordbank`) |
| `validate(array $definition)` | `validation_result` | Pure well-formedness check |
| `serialize(array $definition)` | `string` | Canonical JSON of the artifact |
| `deserialize(string $json)` | `array` | Inverse of serialize; throws `tool_serialization_exception` on shape mismatch |
| `render_editor(array $context)` | `string` | HTML+AMD markup for the editor surface |
| `render_player(array $context)` | `string` | HTML+AMD markup for the read-only/simulation surface |

`validation_result` exposes: `is_valid(): bool`, `get_errors(): string[]`.

### `tool_registry`

Singleton. Stores and retrieves registered tool instances.

```php
$registry = \local_graphitoubb\tool_registry::instance();
$tool = $registry->get('afd');   // returns tool_interface or null
$all  = $registry->all();        // returns tool_interface[]
```

### `tool_descriptor`

Value object returned by `tool_registry::describe()`. Carries `id`, `name`, `version`, and `capabilities` for serialization to the JS layer.

### `bootstrap`

Registers the default tool set. Called by `local/graphitoubb/db/events.php` on plugin init, and defensively by `mod_graphitoubb\tool_factory` to handle edge-case load order.

```php
\local_graphitoubb\bootstrap::register_default_tools();
```

## First tool: AfdTool (AFD v1)

Located in `classes/tools/afd/`. Full domain stack:

| Class | Responsibility |
|---|---|
| `domain/automaton` | Value object: states, alphabet, transitions, initial, finals |
| `domain/validator` | Pure validation — detects nondeterminism, unreachable states, bad refs |
| `domain/simulator` | Step-by-step execution; returns trace + accepted flag |
| `domain/serializer` | Canonical JSON encode/decode; deterministic key order |
| `afd_tool` | Implements `tool_interface`; wires domain classes |

Capabilities exposed: `['edit', 'simulate', 'snapshot', 'wordbank']`.

## Second tool: TruthTableTool (truth_table v1) — iter 1

Located in `classes/tools/truth_table/`. Three exercise modes: `complete`, `equivalence`, `classify`.

Domain stack:

| Class/File | Responsibility |
|---|---|
| `domain/lexer` + `domain/parser` | Tokenise and parse propositional formulas to AST |
| `domain/ast/` | AST node types: `var_node`, `const_node`, `not_node`, `and_node`, `or_node`, `xor_node`, `impl_node`, `iff_node` |
| `domain/evaluator` | Pure Boolean evaluator over AST nodes |
| `domain/truth_table_builder` | Builds truth table rows for all variable combinations |
| `domain/canonicalizer` | Reserializes formula with explicit parentheses |
| `domain/validator` | Bounds checks (variables ≤ 8, formula ≤ 512 chars) |
| `domain/serializer` | Canonical JSON for problem and submission payloads |
| `grader/complete_grader` | Cell-by-cell grading with propagated-error detection |
| `grader/equivalence_grader` | Radio + optional justification table |
| `grader/classify_grader` | Radio + optional justification table |
| `grader/grading_result` + `grader/feedback_item` | Value objects returned by graders |
| `schema/` | JSON schemas for problem and submission payloads (v1) |
| `truth_table_tool` | Implements `tool_interface`; wires domain and graders |

Capabilities exposed: `['edit', 'submit', 'autosave']`.

Operator precedence (highest to lowest):
1. `¬` (negation)
2. `∧` (conjunction)
3. `∨` (disjunction), `⊕` (XOR)
4. `→` (implication)
5. `↔` (biconditional)

## How to add a new tool

1. Create a namespace under `classes/tools/<toolid>/`.
2. Implement `tool_interface` in `classes/tools/<toolid>/<toolid>_tool.php`.
3. Register in `bootstrap::register_default_tools()`:

```php
$registry->register(new \local_graphitoubb\tools\<toolid>\<toolid>_tool());
```

4. Bump `version.php` (`$plugin->version`).
5. Add lang strings for the tool's `get_name()` key.

## Privacy

`null_provider` — this plugin stores no personal data. All user data lives in `mod_graphitoubb` tables.

## Running PHPUnit

```bash
# From Moodle root
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_graphitoubb_testsuite
```

POC parity coverage is documented in `PARITY.md`.
