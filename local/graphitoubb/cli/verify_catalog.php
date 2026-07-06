<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Dev/CI sanity check for the preset catalogue (run via php CLI, no Moodle bootstrap).
 *
 * Parses every truth_table preset with the real domain classes and asserts that the
 * declared expected_equivalent / expected_class match the computed truth values, and
 * that every AFD preset has a non-trivial, two-sided test set. Exits non-zero on any
 * mismatch so it can gate a build.
 *
 * Usage:  php local/graphitoubb/cli/verify_catalog.php
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Minimal PSR-4-ish autoloader for the local_graphitoubb\ namespace — no Moodle needed.
spl_autoload_register(static function (string $class): void {
    $prefix = 'local_graphitoubb\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $path = __DIR__ . '/../classes/' . str_replace('\\', '/', $rel) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

use local_graphitoubb\tools\truth_table\domain\parser;
use local_graphitoubb\tools\truth_table\domain\evaluator;
use local_graphitoubb\tools\truth_table\schema\schema_loader;

$catalogdir = __DIR__ . '/../catalog';
$failures   = [];
$checked    = 0;

/**
 * Parse a formula string into an AST using the real parser (lexes internally).
 */
$parse = static function (string $formula) {
    return (new parser())->parse($formula);
};

/**
 * Return the column of truth values for a formula across the canonical row order,
 * as an ordered array of bools, in canonical (variable-sorted) row order.
 */
$column = static function (string $formula) use ($parse): array {
    $ast      = $parse($formula);
    $vars     = $ast->variables();
    sort($vars);
    $eval     = new evaluator();
    $rows     = [];
    $n        = count($vars);
    for ($i = 0; $i < (1 << $n); $i++) {
        $assign = [];
        for ($b = 0; $b < $n; $b++) {
            // Most-significant bit = first variable (canonical TT order).
            $assign[$vars[$b]] = (bool) (($i >> ($n - 1 - $b)) & 1);
        }
        $rows[] = $eval->evaluate($ast, $assign);
    }
    return $rows;
};

// ---- truth_table presets ----
$ttentries = json_decode((string) file_get_contents($catalogdir . '/truth_table.json'), true);
foreach ($ttentries as $e) {
    $key  = $e['key'];
    $p    = $e['payload'];
    $type = $p['type'];
    $checked++;

    // JSON-Schema structural validation (same loader the qtype/mod use on save).
    $schemaresult = (new schema_loader())->validate($p, $type, 'problem');
    if (!$schemaresult->ok) {
        $failures[] = "$key: schema validation failed: " . implode('; ', $schemaresult->errors);
    }

    try {
        if ($type === 'complete') {
            $parse($p['config']['formula']); // Must parse.
        } else if ($type === 'equivalence') {
            $c1  = $column($p['config']['formula_1']);
            $c2  = $column($p['config']['formula_2']);
            // Equivalence requires the two formulas share the same variable set / arity.
            $f1vars = (function () use ($parse, $p) { $a = $parse($p['config']['formula_1']); $v = $a->variables(); sort($v); return $v; })();
            $f2vars = (function () use ($parse, $p) { $a = $parse($p['config']['formula_2']); $v = $a->variables(); sort($v); return $v; })();
            $equiv  = ($f1vars === $f2vars) && ($c1 === $c2);
            $decl   = (bool) $p['config']['expected_equivalent'];
            if ($equiv !== $decl) {
                $failures[] = "$key: expected_equivalent declared=" . var_export($decl, true) . " actual=" . var_export($equiv, true);
            }
        } else if ($type === 'classify') {
            $col   = $column($p['config']['formula']);
            $alltrue  = !in_array(false, $col, true);
            $allfalse = !in_array(true, $col, true);
            $actual   = $alltrue ? 'tautology' : ($allfalse ? 'contradiction' : 'contingency');
            $decl     = $p['config']['expected_class'];
            if ($actual !== $decl) {
                $failures[] = "$key: expected_class declared=$decl actual=$actual";
            }
        }
    } catch (\Throwable $ex) {
        $failures[] = "$key: PARSE/EVAL ERROR: " . $ex->getMessage();
    }
}

// ---- AFD presets: structural sanity (two-sided, >=4 words, symbols in alphabet) ----
$afdentries = json_decode((string) file_get_contents($catalogdir . '/afd.json'), true);
foreach ($afdentries as $e) {
    $key   = $e['key'];
    $cfg   = $e['payload']['config'];
    $alpha = $cfg['alphabet'];
    $words = $cfg['test_words'];
    $checked++;
    $acc = 0; $rej = 0;
    foreach ($words as $w) {
        $w['accept'] ? $acc++ : $rej++;
        foreach (preg_split('//u', (string) $w['word'], -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (!in_array($ch, $alpha, true)) {
                $failures[] = "$key: word '" . $w['word'] . "' uses symbol '$ch' outside alphabet";
            }
        }
    }
    if (count($words) < 4 || $acc === 0 || $rej === 0) {
        $failures[] = "$key: weak test set ($acc accept, $rej reject, " . count($words) . " total)";
    }
}

echo "Checked $checked presets.\n";
if ($failures) {
    echo count($failures) . " FAILURE(S):\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "ALL OK\n";
exit(0);
