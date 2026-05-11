# Capability audit — mod_graphitoubb v1

Performed during S14. Every PHP entry point and WS endpoint verified for explicit access control.

## Entry points

| Entry point | Capability check | Status | Notes |
|---|---|---|---|
| `view.php` | `require_course_login` + `require_capability('mod/graphitoubb:view')` | OK | If user has neither `attempt` nor `viewreport`, a second `require_capability('mod/graphitoubb:attempt')` fires → nopermissions page |
| `report.php` | `require_login` + `require_capability('mod/graphitoubb:viewreport')` | OK | Hard gate; students cannot reach this page |
| WS `save_snapshot` | `has_capability('mod/graphitoubb:viewreport')` bypass; else `require_capability('mod/graphitoubb:attempt')` + ownership | **FIXED S14** | See finding F-1 below |
| WS `log_word` | same | **FIXED S14** | See finding F-1 below |
| WS `finish_attempt` | same | **FIXED S14** | See finding F-1 below |

## Finding F-1 — WS endpoints lacked explicit attempt-capability gate (FIXED)

**Affected files:**
- `classes/external/save_snapshot.php`
- `classes/external/log_word.php`
- `classes/external/finish_attempt.php`

**Root cause:** All three WS verified *ownership* (does this attempt belong to the caller?) but not *capability* (does the caller still hold `mod/graphitoubb:attempt`?). A student whose capability was revoked after creating an attempt could continue calling the WS with a known `attemptid`.

**Fix applied (S14):** Added `require_capability('mod/graphitoubb:attempt', $context)` in the non-bypass branch of each WS, before the ownership check:

```php
$canbypass = has_capability('mod/graphitoubb:viewreport', $context);
if (!$canbypass) {
    require_capability('mod/graphitoubb:attempt', $context);
}
if (!$canbypass && !$attemptservice->belongs_to($params['attemptid'], (int) $USER->id)) {
    throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
}
```

Teachers (`viewreport` capability) bypass both checks intentionally — they can inspect any attempt.

## Capability definitions

Defined in `db/access.php`:

| Capability | Default roles | Context |
|---|---|---|
| `mod/graphitoubb:view` | student, teacher, editingteacher | module |
| `mod/graphitoubb:attempt` | student | module |
| `mod/graphitoubb:viewreport` | teacher, editingteacher | module |
