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
 * Teacher-facing problem editor for mod_graphitoubb.
 *
 * Sets the tool, exercise type, formula(s), and scoring config for a graphitoubb
 * activity instance. Inserts or updates the graphitoubb_problem row, validates the
 * payload against the JSON Schema and the domain validator.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm       = get_coursemodule_from_id('graphitoubb', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('graphitoubb', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title('Edit problem — ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$existing = $DB->get_record('graphitoubb_problem', ['instanceid' => $instance->id]);

// Pre-fill from existing payload if present.
$prevpayload = null;
if ($existing) {
    $prevpayload = json_decode($existing->payload, true);
}

// Preset catalogue prefill: ?preset=KEY loads a curated exercise into the editor so
// the teacher starts from a ready-made statement instead of a blank form. GET only —
// it must never silently overwrite a just-saved problem on POST.
$presetkey       = optional_param('preset', '', PARAM_RAW_TRIMMED);
$presetloadedmsg = null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $presetkey !== '') {
    $loadedpreset = (new \local_graphitoubb\catalog\preset_catalog())->get($presetkey);
    if ($loadedpreset !== null) {
        $prevpayload     = $loadedpreset->payload;
        $presetloadedmsg = get_string('preset_loaded', 'mod_graphitoubb', $loadedpreset->title);
    }
}

/**
 * Render the collapsible preset-catalogue browser shown above the problem form.
 *
 * Lists every curated exercise grouped by tool. Each row links back to this page
 * with ?preset=KEY so the form prefills from the catalogue. The currently loaded
 * preset (if any) is highlighted.
 *
 * @param  int    $cmid          Course-module id, for the load links.
 * @param  string $activepreset  Key of the preset currently loaded (highlight), or ''.
 * @return string HTML.
 */
function render_preset_catalog_browser(int $cmid, string $activepreset): string {
    $catalog = new \local_graphitoubb\catalog\preset_catalog();
    $groups  = [
        'afd'         => get_string('preset_group_afd', 'mod_graphitoubb'),
        'truth_table' => get_string('preset_group_truth_table', 'mod_graphitoubb'),
        'grafo'       => get_string('preset_group_grafo', 'mod_graphitoubb'),
        'arbol'       => get_string('preset_group_arbol', 'mod_graphitoubb'),
        'karnaugh'    => get_string('preset_group_karnaugh', 'mod_graphitoubb'),
        'relations'   => get_string('preset_group_relations', 'mod_graphitoubb'),
    ];
    $difflabels = [
        'easy'   => get_string('preset_difficulty_easy', 'mod_graphitoubb'),
        'medium' => get_string('preset_difficulty_medium', 'mod_graphitoubb'),
        'hard'   => get_string('preset_difficulty_hard', 'mod_graphitoubb'),
    ];
    $diffclass = ['easy' => 'badge-success', 'medium' => 'badge-warning', 'hard' => 'badge-danger'];

    $out  = '<details class="mod-graphitoubb-preset-catalog card card-body mb-3"'
          . ($activepreset !== '' ? ' open' : '') . '>';
    $out .= '<summary><strong>' . s(get_string('preset_catalog_title', 'mod_graphitoubb'))
          . '</strong></summary>';
    $out .= '<p class="form-text text-muted mt-2">'
          . s(get_string('preset_catalog_help', 'mod_graphitoubb')) . '</p>';

    foreach ($groups as $tool => $grouplabel) {
        $presets = $catalog->all($tool);
        if (empty($presets)) {
            continue;
        }
        // The wrapper's data-tool lets the same client-side toggle that drives the
        // form sections show only the preset group matching the selected tool.
        $out .= '<div class="mod-graphitoubb-preset-group" data-tool="' . s($tool) . '">';
        $out .= '<h4 class="h6 mt-3">' . s($grouplabel) . ' '
              . '<span class="text-muted">(' . count($presets) . ')</span></h4>';
        $out .= '<ul class="list-unstyled mb-0">';
        foreach ($presets as $p) {
            $url = new \moodle_url('/mod/graphitoubb/edit_problem.php', [
                'id'     => $cmid,
                'preset' => $p->key,
            ]);
            $badgeclass = $diffclass[$p->difficulty] ?? 'badge-secondary';
            $difflabel  = $difflabels[$p->difficulty] ?? $p->difficulty;
            $isactive   = ($p->key === $activepreset);
            $out .= '<li class="d-flex align-items-start justify-content-between py-1'
                  . ($isactive ? ' bg-light rounded px-2' : '') . '">';
            $out .= '<span class="mr-2">'
                  . '<span class="badge ' . $badgeclass . ' mr-2">' . s($difflabel) . '</span>'
                  . '<strong>' . s($p->title) . '</strong>'
                  . '<br><small class="text-muted">' . s($p->summary) . '</small>'
                  . '</span>';
            $out .= '<a class="btn btn-sm btn-outline-primary flex-shrink-0" href="'
                  . $url->out(false) . '">'
                  . s(get_string('preset_load', 'mod_graphitoubb')) . '</a>';
            $out .= '</li>';
        }
        $out .= '</ul>';
        $out .= '</div>';
    }

    $out .= '</details>';
    return $out;
}

$error = null;
$savedmsg = null;
$warningmsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $tool = optional_param('tool', 'truth_table', PARAM_ALPHAEXT);
}

// C1: AFD authoring branch — prompt + alphabet + expected-verdict test words.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'afd') {
    $afd_prompt       = optional_param('afd_prompt', '', PARAM_TEXT);
    $afd_alphabet_raw = optional_param('afd_alphabet', '', PARAM_RAW_TRIMMED);
    $afd_words_raw    = optional_param('afd_test_words', '', PARAM_RAW);

    // Alphabet: distinct single alphanumeric characters, in input order.
    preg_match_all('/[a-zA-Z0-9]/', $afd_alphabet_raw, $am);
    $alphabet = array_values(array_unique($am[0]));

    // Test words: one per line, "VERDICT:WORD" (accept|reject|+|-|t|f|1|0…).
    $testwords  = [];
    $wordserror = [];
    foreach (preg_split('/\r\n|\r|\n/', $afd_words_raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        // A leading "*" marks the word as a visible example shown to students.
        $isexample = false;
        if (strpos($line, '*') === 0) {
            $isexample = true;
            $line      = ltrim(substr($line, 1));
        }
        $parts   = explode(':', $line, 2);
        $verdict = strtolower(trim($parts[0]));
        $word    = isset($parts[1]) ? trim($parts[1]) : '';
        $accept  = in_array($verdict, ['accept', 'a', '+', 't', 'true', '1', 'yes'], true);
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (!in_array($ch, $alphabet, true)) {
                $wordserror[] = '"' . $word . '" (symbol "' . $ch . '")';
                break;
            }
        }
        $testwords[] = ['word' => $word, 'accept' => $accept, 'example' => $isexample];
    }

    if ($afd_prompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if (empty($alphabet)) {
        $error = 'Define at least one alphabet symbol.';
    } else if (empty($testwords)) {
        $error = 'Add at least one test word (format: accept:WORD or reject:WORD).';
    } else if (!empty($wordserror)) {
        $error = 'Some test words use symbols outside the alphabet: ' . implode('; ', $wordserror);
    }

    if (!$error) {
        // Non-blocking robustness warning: a small or one-sided test set lets a
        // wrong automaton pass (grading is only as strong as the hidden words).
        $acc = 0;
        $rej = 0;
        foreach ($testwords as $tw) {
            if (!empty($tw['accept'])) {
                $acc++;
            } else {
                $rej++;
            }
        }
        if (count($testwords) < 4 || $acc === 0 || $rej === 0) {
            $warningmsg = 'Weak test set: include more words and both accept and reject '
                . 'cases so a wrong automaton cannot slip through (currently '
                . $acc . ' accept, ' . $rej . ' reject).';
        }

        $payload = [
            'tool'           => 'afd',
            'schema_version' => 1,
            'type'           => 'language',
            'config'         => [
                'prompt'     => $afd_prompt,
                'alphabet'   => $alphabet,
                'test_words' => $testwords,
            ],
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'afd', 'language', $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

// grafo authoring branch — construct/decision/traversal.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'grafo') {
    $gtype    = required_param('grafo_type', PARAM_ALPHA);
    $gprompt  = optional_param('grafo_prompt', '', PARAM_TEXT);
    $gdirected = (bool) optional_param('grafo_directed', 0, PARAM_INT);

    $config = ['prompt' => $gprompt, 'directed' => $gdirected];

    // Read the given-graph JSON (drawn on the authoring canvas / preset), falling
    // back to the previously-saved graph when the hidden field is empty.
    $givenraw   = optional_param('given_graph', '', PARAM_RAW);
    $givengraph = json_decode($givenraw, true);
    if (!is_array($givengraph) || empty($givengraph['nodes'])) {
        $givengraph = $prevpayload['config']['given_graph'] ?? null;
    }
    if (is_array($givengraph)) {
        $givengraph['directed'] = $gdirected;
    }

    if ($gtype === 'construct') {
        $constraints = [];
        $nv = optional_param('grafo_c_nvertices', '', PARAM_RAW_TRIMMED);
        $ne = optional_param('grafo_c_nedges', '', PARAM_RAW_TRIMMED);
        if ($nv !== '' && is_numeric($nv)) {
            $constraints['n_vertices'] = (int) $nv;
        }
        if ($ne !== '' && is_numeric($ne)) {
            $constraints['n_edges'] = (int) $ne;
        }
        $ds = optional_param('grafo_c_degseq', '', PARAM_RAW_TRIMMED);
        if ($ds !== '') {
            preg_match_all('/\d+/', $ds, $dm);
            if (!empty($dm[0])) {
                $constraints['degree_sequence'] = array_map('intval', $dm[0]);
            }
        }
        foreach (['connected', 'bipartite', 'acyclic', 'is_tree', 'eulerian'] as $bkey) {
            $val = optional_param('grafo_c_' . $bkey, 'ignore', PARAM_ALPHA);
            if ($val === 'yes') {
                $constraints[$bkey] = true;
            } else if ($val === 'no') {
                $constraints[$bkey] = false;
            }
        }
        $config['constraints'] = $constraints;
    } else if ($gtype === 'decision') {
        $config['given_graph'] = $givengraph;
        $config['question']    = optional_param('grafo_question', 'has_euler_circuit', PARAM_ALPHANUMEXT);
    } else if ($gtype === 'traversal') {
        $config['given_graph'] = $givengraph;
        $config['walk_kind']   = optional_param('grafo_walkkind', 'euler_circuit', PARAM_ALPHANUMEXT);
        $sv = optional_param('grafo_startvertex', '', PARAM_RAW_TRIMMED);
        if ($sv !== '') {
            $config['start_vertex'] = $sv;
        }
    }

    if ($gprompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if (($gtype === 'decision' || $gtype === 'traversal') && empty($givengraph['nodes'])) {
        $error = 'Draw or load a given graph (it has no vertices).';
    } else if ($gtype === 'construct' && empty($config['constraints'])) {
        $warningmsg = 'No constraints set: any non-empty graph will score 100%.';
    }

    if (!$error) {
        $payload = [
            'tool'           => 'grafo',
            'schema_version' => 1,
            'type'           => $gtype,
            'config'         => $config,
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'grafo', $gtype, $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

// arbol authoring branch — bst_build/traversal_answer/reconstruct.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'arbol') {
    $atype   = required_param('arbol_type', PARAM_ALPHA);
    $aprompt = optional_param('arbol_prompt', '', PARAM_TEXT);
    $config  = ['prompt' => $aprompt];

    $parsenums = static function (string $raw): array {
        preg_match_all('/-?\d+/', $raw, $m);
        return array_map('intval', $m[0]);
    };

    if ($atype === 'bst_build') {
        $config['insertions'] = $parsenums(optional_param('arbol_insertions', '', PARAM_RAW_TRIMMED));
    } else if ($atype === 'traversal_answer') {
        $config['order'] = optional_param('arbol_order', 'in', PARAM_ALPHA);
        $giventreeraw    = optional_param('given_tree', '', PARAM_RAW);
        $giventree       = json_decode($giventreeraw, true);
        if (!is_array($giventree) || empty($giventree['nodes'])) {
            $giventree = $prevpayload['config']['given_tree'] ?? null;
        }
        $config['given_tree'] = $giventree;
    } else if ($atype === 'reconstruct') {
        $config['pair'] = optional_param('arbol_pair', 'pre_in', PARAM_ALPHANUMEXT);
        $config['a']    = $parsenums(optional_param('arbol_a', '', PARAM_RAW_TRIMMED));
        $config['b']    = $parsenums(optional_param('arbol_b', '', PARAM_RAW_TRIMMED));
    }

    if ($aprompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if ($atype === 'bst_build' && empty($config['insertions'])) {
        $error = 'Provide at least one insertion value.';
    } else if ($atype === 'reconstruct'
            && (empty($config['a']) || count($config['a']) !== count($config['b'])
                || count(array_unique($config['a'])) !== count($config['a']))) {
        $error = 'Reconstruct requires two equal-length sequences of DISTINCT values.';
    } else if ($atype === 'traversal_answer' && empty($config['given_tree']['nodes'])) {
        $error = 'Draw or load a given tree (it has no nodes).';
    }

    if (!$error) {
        $payload = [
            'tool'           => 'arbol',
            'schema_version' => 1,
            'type'           => $atype,
            'config'         => $config,
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'arbol', $atype, $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

// karnaugh authoring branch — define f by truth table (minterms) or formula shortcut.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'karnaugh') {
    $knvars   = (int) optional_param('karnaugh_nvars', 3, PARAM_INT);
    $knvars   = max(2, min(4, $knvars));
    $kprompt  = optional_param('karnaugh_prompt', '', PARAM_TEXT);
    $kvarsraw = optional_param('karnaugh_varnames', '', PARAM_RAW_TRIMMED);
    $kminraw  = optional_param('karnaugh_minterms', '', PARAM_RAW_TRIMMED);
    $kformula = optional_param('karnaugh_formula', '', PARAM_RAW_TRIMMED);
    $kmin     = optional_param('karnaugh_require_minimal', 0, PARAM_INT);
    $kfw      = (int) optional_param('karnaugh_fill_weight', 40, PARAM_INT);
    $kgw      = (int) optional_param('karnaugh_grouping_weight', 60, PARAM_INT);

    // Variable names: single uppercase letters, MSB→LSB. Default A,B,C,D.
    preg_match_all('/[A-Za-z]/', strtoupper($kvarsraw), $vm);
    $varnames = array_slice(array_values(array_unique($vm[0])), 0, $knvars);
    while (count($varnames) < $knvars) {
        $varnames[] = chr(65 + count($varnames));
    }

    // Minterms: either from a formula shortcut (auto-fill) or the index list.
    $minterms = [];
    $formulaerror = null;
    if ($kformula !== '') {
        try {
            $ast = (new \local_graphitoubb\tools\truth_table\domain\parser())->parse($kformula);
            $evaluator = new \local_graphitoubb\tools\truth_table\domain\evaluator();
            for ($i = 0; $i < (1 << $knvars); $i++) {
                $assignment = [];
                for ($pos = 0; $pos < $knvars; $pos++) {
                    $bit = $knvars - 1 - $pos;
                    $assignment[$varnames[$pos]] = (bool) (($i >> $bit) & 1);
                }
                if ($evaluator->evaluate($ast, $assignment)) {
                    $minterms[] = $i;
                }
            }
        } catch (\Throwable $e) {
            $formulaerror = $e->getMessage();
        }
    } else {
        preg_match_all('/\d+/', $kminraw, $mm);
        foreach ($mm[0] as $m) {
            $mi = (int) $m;
            if ($mi >= 0 && $mi < (1 << $knvars)) {
                $minterms[$mi] = true;
            }
        }
        $minterms = array_keys($minterms);
        sort($minterms);
    }

    if ($kprompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if ($formulaerror !== null) {
        $error = 'Formula shortcut failed to parse: ' . $formulaerror;
    } else if (empty($minterms)) {
        $error = 'Define at least one 1-cell (a contradiction has nothing to simplify).';
    } else if (count($minterms) === (1 << $knvars)) {
        // Tautology allowed but warn.
        $warningmsg = 'The function is a tautology (all 1s): the correct answer is one full-map group.';
    }
    if (!$error && ($kfw + $kgw) !== 100) {
        $error = 'fill_weight + grouping_weight must equal 100 (got ' . ($kfw + $kgw) . ').';
    }

    if (!$error) {
        $payload = [
            'tool'           => 'karnaugh',
            'schema_version' => 1,
            'type'           => 'simplify',
            'config'         => [
                'prompt'          => $kprompt,
                'n_vars'          => $knvars,
                'var_names'       => $varnames,
                'minterms'        => array_values($minterms),
                'require_minimal' => (bool) $kmin,
                'scoring'         => ['fill_weight' => $kfw, 'grouping_weight' => $kgw],
            ],
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'karnaugh', 'simplify', $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

// relations authoring branch — base set + relation pairs + properties + weights.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'relations') {
    $rprompt  = optional_param('relations_prompt', '', PARAM_TEXT);
    $rsetraw  = optional_param('relations_baseset', '', PARAM_RAW_TRIMMED);
    $rpairraw = optional_param('relations_pairs', '', PARAM_RAW);
    $rreq     = optional_param('relations_required_rep', 'any', PARAM_ALPHA);
    $raskraw  = optional_param('relations_ask', '', PARAM_RAW);
    $rrw      = (int) optional_param('relations_rep_weight', 40, PARAM_INT);
    $rpw      = (int) optional_param('relations_prop_weight', 60, PARAM_INT);

    // Base set: distinct tokens separated by comma/space.
    $baseset = array_values(array_unique(array_filter(preg_split('/[\s,]+/', $rsetraw), static fn($x) => $x !== '')));

    // Relation pairs: "a,b" or "a b" per line, or "(a,b)".
    $pairs = [];
    foreach (preg_split('/\r\n|\r|\n/', $rpairraw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/([^\s,()]+)\s*[,\s]\s*([^\s,()]+)/', $line, $pm)) {
            $pairs[] = [$pm[1], $pm[2]];
        }
    }

    $askprops = [];
    foreach (['reflexive', 'symmetric', 'antisymmetric', 'transitive'] as $p) {
        if (optional_param('relations_ask_' . $p, 0, PARAM_INT)) {
            $askprops[] = $p;
        }
    }
    if (empty($askprops)) {
        $askprops = ['reflexive', 'symmetric', 'antisymmetric', 'transitive'];
    }

    $tool_obj = new \local_graphitoubb\tools\relations\relations_tool();
    $cfg = [
        'prompt'                  => $rprompt,
        'base_set'                => $baseset,
        'relation'                => $pairs,
        'required_representation' => in_array($rreq, ['matrix', 'pairs', 'digraph', 'any'], true) ? $rreq : 'any',
        'ask_properties'          => $askprops,
        'scoring'                 => ['representation_weight' => $rrw, 'properties_weight' => $rpw],
    ];
    $vres = $tool_obj->validate(['config' => $cfg]);

    if ($rprompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if (empty($baseset)) {
        $error = 'Define the base set S (at least one element).';
    } else if (!$vres->ok) {
        $error = 'Validation failed: ' . implode('; ', $vres->errors);
    }

    if (!$error) {
        $payload = [
            'tool'           => 'relations',
            'schema_version' => 1,
            'type'           => 'analyze',
            'config'         => $tool_obj->serialize($cfg) + ['prompt' => $rprompt],
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'relations', 'analyze', $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array(($tool ?? 'truth_table'), ['afd', 'grafo', 'arbol', 'karnaugh', 'relations'], true)) {
    $type     = required_param('exercise_type', PARAM_ALPHA);
    $formula  = optional_param('formula',   '', PARAM_RAW_TRIMMED);
    $formula1 = optional_param('formula_1', '', PARAM_RAW_TRIMMED);
    $formula2 = optional_param('formula_2', '', PARAM_RAW_TRIMMED);
    $expequiv = optional_param('expected_equivalent', 1, PARAM_INT);
    $expclass = optional_param('expected_class', 'tautology', PARAM_ALPHA);
    $reqjust  = optional_param('require_table_justification', 0, PARAM_INT);

    $config = [];
    if ($type === 'complete') {
        $config['formula'] = $formula;
    } else if ($type === 'equivalence') {
        $config['formula_1'] = $formula1;
        $config['formula_2'] = $formula2;
        $config['expected_equivalent'] = (bool) $expequiv;
        $config['require_table_justification'] = (bool) $reqjust;
    } else if ($type === 'classify') {
        $config['formula'] = $formula;
        $config['expected_class'] = $expclass;
        $config['require_table_justification'] = (bool) $reqjust;
    }

    $payload = [
        'tool'           => 'truth_table',
        'schema_version' => 1,
        'type'           => $type,
        'config'         => $config,
        'ui'             => [
            'intermediate_subformulas' => 'auto',
            'manual_subformulas'       => [],
            'row_order'                => 'canonical',
        ],
    ];

    if ($type !== 'complete') {
        $payload['scoring'] = [
            'radio_weight'       => 50,
            'table_weight'       => 50,
            'wrong_radio_policy' => 'strict',
        ];
    }

    // Schema-validate.
    $loader = new \local_graphitoubb\tools\truth_table\schema\schema_loader();
    $result = $loader->validate($payload, $type, 'problem');
    if (!$result->ok) {
        $error = 'Schema validation failed: ' . implode('; ', $result->errors);
    } else {
        // Domain validate (parses formula).
        $validator = new \local_graphitoubb\tools\truth_table\domain\validator();
        $vres = $validator->validate_problem($payload);
        if (!$vres->ok) {
            $error = 'Domain validation failed: ' . implode('; ', $vres->errors);
        }
    }

    if (!$error) {
        $serializer = new \local_graphitoubb\tools\truth_table\domain\serializer();
        $jsonenc    = $serializer->encode($payload);
        $hash       = $serializer->hash($payload);
        $now        = time();

        if ($existing) {
            $existing->tool           = 'truth_table';
            $existing->type           = $type;
            $existing->payload        = $jsonenc;
            $existing->payload_hash   = $hash;
            $existing->schema_version = 1;
            $existing->timemodified   = $now;
            $DB->update_record('graphitoubb_problem', $existing);
        } else {
            $DB->insert_record('graphitoubb_problem', (object) [
                'instanceid'     => $instance->id,
                'tool'           => 'truth_table',
                'type'           => $type,
                'payload'        => $jsonenc,
                'payload_hash'   => $hash,
                'schema_version' => 1,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ]);
        }
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

$selected_type = $prevpayload['type']                            ?? 'complete';
$cur_formula   = $prevpayload['config']['formula']               ?? 'A ∧ B';
$cur_formula1  = $prevpayload['config']['formula_1']             ?? '';
$cur_formula2  = $prevpayload['config']['formula_2']             ?? '';
$cur_expequiv  = $prevpayload['config']['expected_equivalent']   ?? true;
$cur_expclass  = $prevpayload['config']['expected_class']        ?? 'tautology';
$cur_reqjust   = $prevpayload['config']['require_table_justification'] ?? false;

// C1: AFD authoring prefill.
$cur_tool       = $prevpayload['tool'] ?? 'truth_table';
$cur_afd_prompt = $prevpayload['config']['prompt'] ?? '';
$cur_afd_alpha  = isset($prevpayload['config']['alphabet'])
    ? implode(' ', $prevpayload['config']['alphabet'])
    : 'a b';
$cur_afd_words  = '';
if (($prevpayload['tool'] ?? '') === 'afd' && !empty($prevpayload['config']['test_words'])) {
    $lines = [];
    foreach ($prevpayload['config']['test_words'] as $tw) {
        $prefix = !empty($tw['example']) ? '*' : '';
        $lines[] = $prefix . (!empty($tw['accept']) ? 'accept' : 'reject') . ':' . ($tw['word'] ?? '');
    }
    $cur_afd_words = implode("\n", $lines);
}

// grafo authoring prefill.
$cur_grafo_type   = ($cur_tool === 'grafo') ? ($prevpayload['type'] ?? 'construct') : 'construct';
$cur_grafo_cfg    = ($cur_tool === 'grafo') ? ($prevpayload['config'] ?? []) : [];
$cur_grafo_prompt = (string) ($cur_grafo_cfg['prompt'] ?? '');
$cur_grafo_dir    = !empty($cur_grafo_cfg['directed']);
$cur_grafo_constr = $cur_grafo_cfg['constraints'] ?? [];
$cur_grafo_q      = (string) ($cur_grafo_cfg['question'] ?? 'has_euler_circuit');
$cur_grafo_wk     = (string) ($cur_grafo_cfg['walk_kind'] ?? 'euler_circuit');
$cur_grafo_sv     = (string) ($cur_grafo_cfg['start_vertex'] ?? '');
$cur_grafo_given  = $cur_grafo_cfg['given_graph'] ?? null;

// arbol authoring prefill.
$cur_arbol_type   = ($cur_tool === 'arbol') ? ($prevpayload['type'] ?? 'bst_build') : 'bst_build';
$cur_arbol_cfg    = ($cur_tool === 'arbol') ? ($prevpayload['config'] ?? []) : [];
$cur_arbol_prompt = (string) ($cur_arbol_cfg['prompt'] ?? '');
$cur_arbol_ins    = isset($cur_arbol_cfg['insertions']) ? implode(', ', $cur_arbol_cfg['insertions']) : '';
$cur_arbol_order  = (string) ($cur_arbol_cfg['order'] ?? 'in');
$cur_arbol_pair   = (string) ($cur_arbol_cfg['pair'] ?? 'pre_in');
$cur_arbol_a      = isset($cur_arbol_cfg['a']) ? implode(', ', $cur_arbol_cfg['a']) : '';
$cur_arbol_b      = isset($cur_arbol_cfg['b']) ? implode(', ', $cur_arbol_cfg['b']) : '';
$cur_arbol_given  = $cur_arbol_cfg['given_tree'] ?? null;

// karnaugh authoring prefill.
$cur_k_cfg    = ($cur_tool === 'karnaugh') ? ($prevpayload['config'] ?? []) : [];
$cur_k_prompt = (string) ($cur_k_cfg['prompt'] ?? '');
$cur_k_nvars  = (int) ($cur_k_cfg['n_vars'] ?? 3);
$cur_k_vars   = isset($cur_k_cfg['var_names']) ? implode(' ', $cur_k_cfg['var_names']) : 'A B C';
$cur_k_min    = isset($cur_k_cfg['minterms']) ? implode(', ', $cur_k_cfg['minterms']) : '';
$cur_k_reqmin = !array_key_exists('require_minimal', $cur_k_cfg) || !empty($cur_k_cfg['require_minimal']);
$cur_k_fw     = (int) ($cur_k_cfg['scoring']['fill_weight'] ?? 40);
$cur_k_gw     = (int) ($cur_k_cfg['scoring']['grouping_weight'] ?? 60);

// relations authoring prefill.
$cur_r_cfg    = ($cur_tool === 'relations') ? ($prevpayload['config'] ?? []) : [];
$cur_r_prompt = (string) ($cur_r_cfg['prompt'] ?? '');
$cur_r_set    = isset($cur_r_cfg['base_set']) ? implode(', ', $cur_r_cfg['base_set']) : '1, 2, 3';
$cur_r_pairs  = '';
if (!empty($cur_r_cfg['relation'])) {
    $lines = [];
    foreach ($cur_r_cfg['relation'] as $p) {
        $lines[] = ($p[0] ?? '') . ', ' . ($p[1] ?? '');
    }
    $cur_r_pairs = implode("\n", $lines);
}
$cur_r_req    = (string) ($cur_r_cfg['required_representation'] ?? 'any');
$cur_r_ask    = $cur_r_cfg['ask_properties'] ?? ['reflexive', 'symmetric', 'antisymmetric', 'transitive'];
$cur_r_rw     = (int) ($cur_r_cfg['scoring']['representation_weight'] ?? 40);
$cur_r_pw     = (int) ($cur_r_cfg['scoring']['properties_weight'] ?? 60);

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit problem — ' . format_string($instance->name));

if ($error) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
if ($savedmsg) {
    echo $OUTPUT->notification($savedmsg, \core\output\notification::NOTIFY_SUCCESS);
}
if ($warningmsg) {
    echo $OUTPUT->notification($warningmsg, \core\output\notification::NOTIFY_WARNING);
}
if ($presetloadedmsg) {
    echo $OUTPUT->notification($presetloadedmsg, \core\output\notification::NOTIFY_INFO);
}

$sesskey = sesskey();
$viewurl = (new \moodle_url('/mod/graphitoubb/view.php', ['id' => $cm->id]))->out(false);

$selopt = function(string $value, string $current, string $label): string {
    $sel = ($value === $current) ? ' selected' : '';
    return '<option value="' . s($value) . '"' . $sel . '>' . s($label) . '</option>';
};

$checked = function(bool $b): string {
    return $b ? ' checked' : '';
};

// C4: build a read-only preview of the truth table a formula produces, so the
// teacher sees exactly what the student will get. Returns '' on parse failure.
$build_preview = function(string $formula, string $heading): string {
    $formula = trim($formula);
    if ($formula === '') {
        return '';
    }
    try {
        $ast   = (new \local_graphitoubb\tools\truth_table\domain\parser())->parse($formula);
        $table = (new \local_graphitoubb\tools\truth_table\domain\truth_table_builder())->build($ast, ['intermediate' => 'auto']);
    } catch (\Throwable $e) {
        return '';
    }
    $nvars = count($table['variables']);
    $out  = '<h4 class="h6 mt-3">' . s($heading) . '</h4>';
    $out .= '<div class="table-responsive"><table class="table table-sm table-bordered" style="width:auto">';
    $out .= '<thead><tr>';
    foreach ($table['columns'] as $col) {
        $out .= '<th scope="col" class="text-center">' . s((string) $col) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($table['rows'] as $row) {
        $out .= '<tr>';
        foreach ($table['columns'] as $ci => $col) {
            if ($ci < $nvars) {
                $val = !empty($row['vars'][$table['variables'][$ci]]);
            } else {
                $val = !empty($row['values'][$ci - $nvars]);
            }
            $out .= '<td class="text-center">' . ($val ? 'V' : 'F') . '</td>';
        }
        $out .= '</tr>';
    }
    $out .= '</tbody></table></div>';
    return $out;
};

// Build the preview HTML for the current truth_table problem.
$previewhtml = '';
if ($cur_tool === 'truth_table') {
    if ($selected_type === 'equivalence') {
        $previewhtml .= $build_preview($cur_formula1, 'Formula 1');
        $previewhtml .= $build_preview($cur_formula2, 'Formula 2');
    } else {
        $previewhtml .= $build_preview($cur_formula, 'Formula');
    }
}

$cur_formula_safe  = s($cur_formula);
$cur_formula1_safe = s($cur_formula1);
$cur_formula2_safe = s($cur_formula2);
$cur_afd_prompt_safe = s($cur_afd_prompt);
$cur_afd_alpha_safe  = s($cur_afd_alpha);
$cur_afd_words_safe  = s($cur_afd_words);

// Preset catalogue browser: a collapsible library of curated, ready-to-use statements.
// Picking one reloads this page with ?preset=KEY, which prefills the form above the save
// button so the teacher can tweak before saving.
echo render_preset_catalog_browser($cm->id, $presetkey);

// grafo authoring canvas (rendered read/teacher-editable; prefilled from preset/existing).
$renderer = $PAGE->get_renderer('mod_graphitoubb');
$synth_grafo_problem = (object) [
    'tool'    => 'grafo',
    'payload' => json_encode(['tool' => 'grafo', 'type' => $cur_grafo_type, 'config' => $cur_grafo_cfg]),
];
$grafo_authoring_canvas = $renderer->render_graph_editor(
    (int) $instance->id, (int) $instance->id, $synth_grafo_problem, false, null, false, true
);
$cur_grafo_given_attr = $cur_grafo_given
    ? s(json_encode($cur_grafo_given, JSON_UNESCAPED_UNICODE)) : '';

// Constraint 3-state select current values.
$cstate = function (string $key) use ($cur_grafo_constr): string {
    if (!array_key_exists($key, $cur_grafo_constr)) {
        return 'ignore';
    }
    return $cur_grafo_constr[$key] ? 'yes' : 'no';
};
$cur_c_nv = isset($cur_grafo_constr['n_vertices']) ? (string) $cur_grafo_constr['n_vertices'] : '';
$cur_c_ne = isset($cur_grafo_constr['n_edges']) ? (string) $cur_grafo_constr['n_edges'] : '';
$cur_c_ds = isset($cur_grafo_constr['degree_sequence'])
    ? implode(', ', $cur_grafo_constr['degree_sequence']) : '';
$cur_grafo_prompt_safe = s($cur_grafo_prompt);
$cur_grafo_sv_safe     = s($cur_grafo_sv);
$grafo_dir_attr        = $checked($cur_grafo_dir);

// arbol authoring canvas (for traversal_answer given tree).
$synth_arbol_problem = (object) [
    'tool'    => 'arbol',
    'payload' => json_encode(['tool' => 'arbol', 'type' => $cur_arbol_type, 'config' => $cur_arbol_cfg]),
];
// Distinct host id (offset) so the two authoring canvases don't collide in the DOM.
$arbol_canvas_hostid = (int) $instance->id + 1000000;
$arbol_authoring_canvas = $renderer->render_graph_editor(
    $arbol_canvas_hostid, $arbol_canvas_hostid, $synth_arbol_problem, false, null, false, true
);
$cur_arbol_given_attr  = $cur_arbol_given
    ? s(json_encode($cur_arbol_given, JSON_UNESCAPED_UNICODE)) : '';
$cur_arbol_prompt_safe = s($cur_arbol_prompt);
$cur_arbol_ins_safe    = s($cur_arbol_ins);
$cur_arbol_a_safe      = s($cur_arbol_a);
$cur_arbol_b_safe      = s($cur_arbol_b);

echo <<<HTML
<form method="post" action="">
    <input type="hidden" name="sesskey" value="{$sesskey}">

    <div class="form-group">
        <label for="tool"><strong>Tool</strong></label>
        <select name="tool" id="tool" class="form-control">
HTML;
echo $selopt('truth_table', $cur_tool, 'Truth table (logic)');
echo $selopt('afd',         $cur_tool, 'AFD — finite automaton');
echo $selopt('grafo',       $cur_tool, 'Grafo — graph theory');
echo $selopt('arbol',       $cur_tool, 'Árbol — trees & BST');
echo $selopt('karnaugh',    $cur_tool, 'Karnaugh — boolean simplification');
echo $selopt('relations',   $cur_tool, 'Relations — binary relations');
echo <<<HTML
        </select>
    </div>

    <div class="mod-graphitoubb-tool-section" data-tool="truth_table">
    <div class="form-group">
        <label for="exercise_type"><strong>Exercise type</strong></label>
        <select name="exercise_type" id="exercise_type" class="form-control">
HTML;
echo $selopt('complete',    $selected_type, 'Complete table');
echo $selopt('equivalence', $selected_type, 'Equivalence (two formulas)');
echo $selopt('classify',    $selected_type, 'Classify (tautology / contradiction / contingency)');
echo <<<HTML
        </select>
    </div>

    <details class="mod-graphitoubb-syntax-help card card-body mb-3">
        <summary><strong>Formula syntax help</strong></summary>
        <table class="table table-sm mt-2 mb-0">
            <thead><tr><th>Operator</th><th>Symbol</th><th>ASCII</th><th>Example</th></tr></thead>
            <tbody>
                <tr><td>Negation</td><td>¬</td><td><code>~</code> <code>!</code></td><td><code>~A</code> → ¬A</td></tr>
                <tr><td>Conjunction</td><td>∧</td><td><code>&amp;</code> <code>/\\</code></td><td><code>A &amp; B</code> → A ∧ B</td></tr>
                <tr><td>Disjunction</td><td>∨</td><td><code>|</code> <code>\\/</code></td><td><code>A | B</code> → A ∨ B</td></tr>
                <tr><td>Exclusive or</td><td>⊕</td><td>—</td><td><code>A ⊕ B</code></td></tr>
                <tr><td>Implication</td><td>→</td><td><code>-&gt;</code></td><td><code>A -&gt; B</code> → A → B</td></tr>
                <tr><td>Biconditional</td><td>↔</td><td><code>&lt;-&gt;</code></td><td><code>A &lt;-&gt; B</code> → A ↔ B</td></tr>
                <tr><td>True / False</td><td>⊤ / ⊥</td><td>—</td><td><code>⊤</code>, <code>⊥</code></td></tr>
            </tbody>
        </table>
        <small class="form-text text-muted">Variables are single uppercase letters (A–Z). Use parentheses to group.</small>
    </details>

    <div class="form-group mod-graphitoubb-field-group" data-types="complete classify">
        <label for="formula"><strong>Formula</strong> (single, for complete &amp; classify)</label>
        <input type="text" name="formula" id="formula" class="form-control"
               value="{$cur_formula_safe}"
               placeholder="A ∧ B  (or use ASCII: A & B, A | B, ~A, A -> B, A &lt;-&gt; B)">
        <small class="form-text text-muted">
            Symbols: ¬ ∧ ∨ ⊕ → ↔ ⊤ ⊥. ASCII synonyms accepted.
        </small>
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label for="formula_1"><strong>Formula 1</strong> (equivalence only)</label>
        <input type="text" name="formula_1" id="formula_1" class="form-control"
               value="{$cur_formula1_safe}" placeholder="A → B">
    </div>
    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label for="formula_2"><strong>Formula 2</strong> (equivalence only)</label>
        <input type="text" name="formula_2" id="formula_2" class="form-control"
               value="{$cur_formula2_safe}" placeholder="¬A ∨ B">
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label><strong>Expected equivalent</strong> (equivalence only)</label>
        <select name="expected_equivalent" class="form-control">
HTML;
echo $selopt('1', $cur_expequiv ? '1' : '0', 'Yes — equivalent');
echo $selopt('0', $cur_expequiv ? '1' : '0', 'No — not equivalent');
echo <<<HTML
        </select>
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="classify">
        <label><strong>Expected class</strong> (classify only)</label>
        <select name="expected_class" class="form-control">
HTML;
echo $selopt('tautology',     $cur_expclass, 'Tautology');
echo $selopt('contradiction', $cur_expclass, 'Contradiction');
echo $selopt('contingency',   $cur_expclass, 'Contingency');
$reqjust_attr = $checked((bool) $cur_reqjust);
echo <<<HTML
        </select>
    </div>

    <div class="form-check mod-graphitoubb-field-group" data-types="equivalence classify">
        <input type="checkbox" name="require_table_justification" value="1"
               id="reqjust" class="form-check-input"{$reqjust_attr}>
        <label class="form-check-label" for="reqjust">
            Require table justification (equivalence / classify)
        </label>
    </div>
    </div><!-- /truth_table tool section -->

    <div class="mod-graphitoubb-tool-section" data-tool="afd">
        <div class="form-group">
            <label for="afd_prompt"><strong>Prompt (consigna)</strong></label>
            <textarea name="afd_prompt" id="afd_prompt" class="form-control" rows="3"
                      placeholder="Build a DFA over {a, b} that accepts exactly the words containing at least one 'a'.">{$cur_afd_prompt_safe}</textarea>
            <small class="form-text text-muted">Shown to the student above the editor.</small>
        </div>
        <div class="form-group">
            <label for="afd_alphabet"><strong>Alphabet</strong></label>
            <input type="text" name="afd_alphabet" id="afd_alphabet" class="form-control"
                   value="{$cur_afd_alpha_safe}" placeholder="a b">
            <small class="form-text text-muted">Single alphanumeric symbols, separated by spaces or commas.</small>
        </div>
        <div class="form-group">
            <label for="afd_test_words"><strong>Test words</strong> (one per line)</label>
            <textarea name="afd_test_words" id="afd_test_words" class="form-control" rows="6"
                      placeholder="accept:a&#10;accept:aa&#10;accept:ba&#10;reject:&#10;reject:b">{$cur_afd_words_safe}</textarea>
            <small class="form-text text-muted">
                Format <code>verdict:word</code> — e.g. <code>accept:aa</code>, <code>reject:b</code>,
                <code>accept:</code> (empty word ε). Verdicts: accept / reject (also + / -). These are
                hidden from students and used to grade the automaton on submission.
                Prefix a line with <code>*</code> to also show it to students as a worked example
                (e.g. <code>*accept:aa</code>) — pick a few that illustrate the language without
                giving the whole test set away.
            </small>
        </div>
    </div><!-- /afd tool section -->

    <div class="mod-graphitoubb-tool-section" data-tool="grafo">
        <div class="form-group">
            <label for="grafo_type"><strong>Graph exercise type</strong></label>
            <select name="grafo_type" id="grafo_type" class="form-control">
HTML;
echo $selopt('construct', $cur_grafo_type, 'Construct — build a graph meeting constraints');
echo $selopt('decision',  $cur_grafo_type, 'Decision — yes/no about a given graph (e.g. Königsberg)');
echo $selopt('traversal', $cur_grafo_type, 'Traversal — find a walk on a given graph');
echo <<<HTML
            </select>
        </div>
        <div class="form-group">
            <label for="grafo_prompt"><strong>Prompt (consigna)</strong></label>
            <textarea name="grafo_prompt" id="grafo_prompt" class="form-control" rows="3"
                      placeholder="Decide whether the Königsberg bridges graph has an Euler circuit.">{$cur_grafo_prompt_safe}</textarea>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="grafo_directed" value="1" id="grafo_directed"
                   class="form-check-input"{$grafo_dir_attr}>
            <label class="form-check-label" for="grafo_directed">Directed graph</label>
        </div>

        <div class="mod-graphitoubb-grafo-fields" data-gtypes="construct">
            <p class="text-muted small">Set the constraints the student's graph must satisfy. Each satisfied
            constraint earns partial credit (pass ≥ 60%).</p>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="grafo_c_nvertices">Number of vertices</label>
                    <input type="text" name="grafo_c_nvertices" id="grafo_c_nvertices"
                           class="form-control" value="{$cur_c_nv}" placeholder="(any)">
                </div>
                <div class="form-group col-md-4">
                    <label for="grafo_c_nedges">Number of edges</label>
                    <input type="text" name="grafo_c_nedges" id="grafo_c_nedges"
                           class="form-control" value="{$cur_c_ne}" placeholder="(any)">
                </div>
                <div class="form-group col-md-4">
                    <label for="grafo_c_degseq">Degree sequence</label>
                    <input type="text" name="grafo_c_degseq" id="grafo_c_degseq"
                           class="form-control" value="{$cur_c_ds}" placeholder="e.g. 2, 2, 2">
                </div>
            </div>
            <div class="form-row">
HTML;
$constropts = function (string $key, string $label) use ($selopt, $cstate): string {
    $cur = $cstate($key);
    return '<div class="form-group col-md-4"><label>' . s($label) . '</label>'
        . '<select name="grafo_c_' . $key . '" class="form-control">'
        . $selopt('ignore', $cur, '—')
        . $selopt('yes', $cur, 'Yes (required)')
        . $selopt('no', $cur, 'No (must not)')
        . '</select></div>';
};
echo $constropts('connected', 'Connected');
echo $constropts('bipartite', 'Bipartite');
echo $constropts('acyclic', 'Acyclic');
echo $constropts('is_tree', 'Is a tree');
echo $constropts('eulerian', 'Eulerian (Euler circuit)');
echo <<<HTML
            </div>
        </div>

        <div class="mod-graphitoubb-grafo-fields" data-gtypes="decision">
            <div class="form-group">
                <label for="grafo_question"><strong>Question</strong></label>
                <select name="grafo_question" id="grafo_question" class="form-control">
HTML;
echo $selopt('has_euler_circuit',    $cur_grafo_q, 'Has an Euler circuit?');
echo $selopt('has_euler_path',       $cur_grafo_q, 'Has an Euler path?');
echo $selopt('has_hamiltonian_path', $cur_grafo_q, 'Has a Hamiltonian path?');
echo $selopt('is_connected',         $cur_grafo_q, 'Is connected?');
echo $selopt('is_bipartite',         $cur_grafo_q, 'Is bipartite?');
echo <<<HTML
                </select>
            </div>
        </div>

        <div class="mod-graphitoubb-grafo-fields" data-gtypes="traversal">
            <div class="form-group">
                <label for="grafo_walkkind"><strong>Walk kind</strong></label>
                <select name="grafo_walkkind" id="grafo_walkkind" class="form-control">
HTML;
echo $selopt('euler_circuit',       $cur_grafo_wk, 'Euler circuit');
echo $selopt('euler_path',          $cur_grafo_wk, 'Euler path');
echo $selopt('hamiltonian_path',    $cur_grafo_wk, 'Hamiltonian path');
echo $selopt('hamiltonian_circuit', $cur_grafo_wk, 'Hamiltonian circuit');
echo <<<HTML
                </select>
            </div>
            <div class="form-group">
                <label for="grafo_startvertex">Start vertex id (optional)</label>
                <input type="text" name="grafo_startvertex" id="grafo_startvertex"
                       class="form-control" value="{$cur_grafo_sv_safe}" placeholder="(any)">
            </div>
        </div>

        <div class="mod-graphitoubb-grafo-fields" data-gtypes="decision traversal">
            <label><strong>Given graph</strong> (draw below, or load a preset)</label>
            <input type="hidden" name="given_graph" value="{$cur_grafo_given_attr}">
            {$grafo_authoring_canvas}
        </div>
    </div><!-- /grafo tool section -->

    <div class="mod-graphitoubb-tool-section" data-tool="arbol">
        <div class="form-group">
            <label for="arbol_type"><strong>Tree exercise type</strong></label>
            <select name="arbol_type" id="arbol_type" class="form-control">
HTML;
echo $selopt('bst_build',       $cur_arbol_type, 'BST construction — build a BST from an insertion order');
echo $selopt('traversal_answer', $cur_arbol_type, 'Traversal — give the pre/in/post/level order of a tree');
echo $selopt('reconstruct',     $cur_arbol_type, 'Reconstruct — rebuild a tree from two traversals');
echo <<<HTML
            </select>
        </div>
        <div class="form-group">
            <label for="arbol_prompt"><strong>Prompt (consigna)</strong></label>
            <textarea name="arbol_prompt" id="arbol_prompt" class="form-control" rows="3"
                      placeholder="Insert the values 8, 3, 10, 1, 6 into a binary search tree.">{$cur_arbol_prompt_safe}</textarea>
        </div>

        <div class="mod-graphitoubb-arbol-fields" data-atypes="bst_build">
            <div class="form-group">
                <label for="arbol_insertions"><strong>Insertion order</strong> (comma-separated integers)</label>
                <input type="text" name="arbol_insertions" id="arbol_insertions"
                       class="form-control" value="{$cur_arbol_ins_safe}" placeholder="8, 3, 10, 1, 6">
            </div>
        </div>

        <div class="mod-graphitoubb-arbol-fields" data-atypes="traversal_answer">
            <div class="form-group">
                <label for="arbol_order"><strong>Traversal order</strong></label>
                <select name="arbol_order" id="arbol_order" class="form-control">
HTML;
echo $selopt('pre',   $cur_arbol_order, 'Pre-order');
echo $selopt('in',    $cur_arbol_order, 'In-order');
echo $selopt('post',  $cur_arbol_order, 'Post-order');
echo $selopt('level', $cur_arbol_order, 'Level-order');
echo <<<HTML
                </select>
            </div>
            <label><strong>Given tree</strong> (draw below, or load a preset)</label>
            <input type="hidden" name="given_tree" value="{$cur_arbol_given_attr}">
            {$arbol_authoring_canvas}
        </div>

        <div class="mod-graphitoubb-arbol-fields" data-atypes="reconstruct">
            <div class="form-group">
                <label for="arbol_pair"><strong>Traversal pair</strong></label>
                <select name="arbol_pair" id="arbol_pair" class="form-control">
HTML;
echo $selopt('pre_in',  $cur_arbol_pair, 'Preorder + Inorder');
echo $selopt('post_in', $cur_arbol_pair, 'Postorder + Inorder');
echo <<<HTML
                </select>
            </div>
            <div class="form-group">
                <label for="arbol_a"><strong>First traversal</strong> (preorder or postorder; distinct integers)</label>
                <input type="text" name="arbol_a" id="arbol_a" class="form-control"
                       value="{$cur_arbol_a_safe}" placeholder="8, 3, 1, 6, 10">
            </div>
            <div class="form-group">
                <label for="arbol_b"><strong>Inorder traversal</strong> (distinct integers)</label>
                <input type="text" name="arbol_b" id="arbol_b" class="form-control"
                       value="{$cur_arbol_b_safe}" placeholder="1, 3, 6, 8, 10">
            </div>
        </div>
    </div><!-- /arbol tool section -->
HTML;

// ---- karnaugh tool section ----
$k_prompt_safe = s($cur_k_prompt);
$k_vars_safe   = s($cur_k_vars);
$k_min_safe    = s($cur_k_min);
$k_reqmin_attr = $checked($cur_k_reqmin);
$k_fw_safe     = (int) $cur_k_fw;
$k_gw_safe     = (int) $cur_k_gw;
echo '<div class="mod-graphitoubb-tool-section" data-tool="karnaugh">';
echo <<<HTML
    <div class="form-group">
        <label for="karnaugh_prompt"><strong>Prompt (consigna)</strong></label>
        <textarea name="karnaugh_prompt" id="karnaugh_prompt" class="form-control" rows="3"
                  placeholder="Simplify f(A,B,C) using a Karnaugh map.">{$k_prompt_safe}</textarea>
    </div>
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="karnaugh_nvars"><strong>Variables</strong></label>
            <select name="karnaugh_nvars" id="karnaugh_nvars" class="form-control">
HTML;
echo $selopt('2', (string) $cur_k_nvars, '2 variables');
echo $selopt('3', (string) $cur_k_nvars, '3 variables');
echo $selopt('4', (string) $cur_k_nvars, '4 variables');
echo <<<HTML
            </select>
        </div>
        <div class="form-group col-md-8">
            <label for="karnaugh_varnames">Variable names (MSB→LSB, single letters)</label>
            <input type="text" name="karnaugh_varnames" id="karnaugh_varnames" class="form-control"
                   value="{$k_vars_safe}" placeholder="A B C">
        </div>
    </div>
    <div class="form-group">
        <label for="karnaugh_minterms"><strong>Minterms</strong> (indices where f = 1, comma-separated)</label>
        <input type="text" name="karnaugh_minterms" id="karnaugh_minterms" class="form-control"
               value="{$k_min_safe}" placeholder="0, 2, 3, 4, 7">
        <small class="form-text text-muted">The canonical truth of f. Index i's bits are the variables MSB→LSB.</small>
    </div>
    <div class="form-group">
        <label for="karnaugh_formula">…or a <strong>formula shortcut</strong> (auto-fills the minterms; overrides the list)</label>
        <input type="text" name="karnaugh_formula" id="karnaugh_formula" class="form-control"
               placeholder="A&amp;B | ~C   (leave blank to use the minterm list)">
        <small class="form-text text-muted">Uses the same syntax as truth-table formulas. Variables must match the names above.</small>
    </div>
    <div class="form-check mb-2">
        <input type="checkbox" name="karnaugh_require_minimal" value="1" id="karnaugh_require_minimal"
               class="form-check-input"{$k_reqmin_attr}>
        <label class="form-check-label" for="karnaugh_require_minimal">
            Require a minimal cover (reward fewer/larger groups)
        </label>
    </div>
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="karnaugh_fill_weight">Fill weight (%)</label>
            <input type="number" name="karnaugh_fill_weight" id="karnaugh_fill_weight" class="form-control"
                   value="{$k_fw_safe}" min="0" max="100">
        </div>
        <div class="form-group col-md-4">
            <label for="karnaugh_grouping_weight">Grouping weight (%)</label>
            <input type="number" name="karnaugh_grouping_weight" id="karnaugh_grouping_weight" class="form-control"
                   value="{$k_gw_safe}" min="0" max="100">
        </div>
    </div>
    <small class="form-text text-muted mb-2">Fill + grouping weights must sum to 100.</small>
</div><!-- /karnaugh tool section -->
HTML;

// ---- relations tool section ----
$r_prompt_safe = s($cur_r_prompt);
$r_set_safe    = s($cur_r_set);
$r_pairs_safe  = s($cur_r_pairs);
$r_rw_safe     = (int) $cur_r_rw;
$r_pw_safe     = (int) $cur_r_pw;
$r_ask = static function (string $p) use ($cur_r_ask, $checked): string {
    return $checked(in_array($p, $cur_r_ask, true));
};
echo '<div class="mod-graphitoubb-tool-section" data-tool="relations">';
echo <<<HTML
    <div class="form-group">
        <label for="relations_prompt"><strong>Prompt (consigna)</strong></label>
        <textarea name="relations_prompt" id="relations_prompt" class="form-control" rows="3"
                  placeholder="Build R on S and declare its properties.">{$r_prompt_safe}</textarea>
    </div>
    <div class="form-group">
        <label for="relations_baseset"><strong>Base set S</strong> (elements, comma/space-separated; ≤ 6)</label>
        <input type="text" name="relations_baseset" id="relations_baseset" class="form-control"
               value="{$r_set_safe}" placeholder="1, 2, 3">
    </div>
    <div class="form-group">
        <label for="relations_pairs"><strong>Relation R</strong> (one ordered pair per line: <code>a, b</code>)</label>
        <textarea name="relations_pairs" id="relations_pairs" class="form-control" rows="5"
                  placeholder="1, 1&#10;2, 2&#10;3, 3&#10;1, 2">{$r_pairs_safe}</textarea>
    </div>
    <div class="form-group">
        <label for="relations_required_rep"><strong>Required representation</strong></label>
        <select name="relations_required_rep" id="relations_required_rep" class="form-control">
HTML;
echo $selopt('any',     $cur_r_req, 'Any (student chooses)');
echo $selopt('matrix',  $cur_r_req, 'Matrix only');
echo $selopt('pairs',   $cur_r_req, 'Ordered pairs only');
echo $selopt('digraph', $cur_r_req, 'Directed graph only');
echo <<<HTML
        </select>
    </div>
    <div class="form-group">
        <label><strong>Properties to declare</strong></label>
        <div class="form-check"><input type="checkbox" name="relations_ask_reflexive" value="1"
             id="relations_ask_reflexive" class="form-check-input"{$r_ask('reflexive')}>
             <label class="form-check-label" for="relations_ask_reflexive">Reflexive</label></div>
        <div class="form-check"><input type="checkbox" name="relations_ask_symmetric" value="1"
             id="relations_ask_symmetric" class="form-check-input"{$r_ask('symmetric')}>
             <label class="form-check-label" for="relations_ask_symmetric">Symmetric</label></div>
        <div class="form-check"><input type="checkbox" name="relations_ask_antisymmetric" value="1"
             id="relations_ask_antisymmetric" class="form-check-input"{$r_ask('antisymmetric')}>
             <label class="form-check-label" for="relations_ask_antisymmetric">Antisymmetric</label></div>
        <div class="form-check"><input type="checkbox" name="relations_ask_transitive" value="1"
             id="relations_ask_transitive" class="form-check-input"{$r_ask('transitive')}>
             <label class="form-check-label" for="relations_ask_transitive">Transitive</label></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="relations_rep_weight">Representation weight (%)</label>
            <input type="number" name="relations_rep_weight" id="relations_rep_weight" class="form-control"
                   value="{$r_rw_safe}" min="0" max="100">
        </div>
        <div class="form-group col-md-4">
            <label for="relations_prop_weight">Properties weight (%)</label>
            <input type="number" name="relations_prop_weight" id="relations_prop_weight" class="form-control"
                   value="{$r_pw_safe}" min="0" max="100">
        </div>
    </div>
    <small class="form-text text-muted mb-2">Representation + properties weights must sum to 100.</small>
</div><!-- /relations tool section -->
HTML;

echo <<<HTML
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save problem</button>
        <a class="btn btn-secondary" href="{$viewurl}">Back to activity</a>
    </div>
</form>
HTML;

// C4: live preview of the truth table the current formula produces.
if ($previewhtml !== '') {
    echo \html_writer::start_div('mod-graphitoubb-tt-preview mt-4');
    echo $OUTPUT->heading('Truth table preview', 4, 'h5');
    echo $previewhtml;
    echo \html_writer::end_div();
}

// C3: show/hide the type-specific fields client-side instead of reloading the
// whole form on change (the old onchange="this.form.submit()" lost typed input).
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var toolSel = document.getElementById('tool');
    var typeSel = document.getElementById('exercise_type');
    var grafoTypeSel = document.getElementById('grafo_type');
    var arbolTypeSel = document.getElementById('arbol_type');

    var toggleTool = function() {
        var t = toolSel ? toolSel.value : 'truth_table';
        document.querySelectorAll('.mod-graphitoubb-tool-section').forEach(function(s) {
            s.style.display = (s.getAttribute('data-tool') === t) ? '' : 'none';
        });
        // The preset catalogue only shows exercises for the selected tool.
        document.querySelectorAll('.mod-graphitoubb-preset-group').forEach(function(g) {
            g.style.display = (g.getAttribute('data-tool') === t) ? '' : 'none';
        });
    };
    var toggleType = function() {
        if (!typeSel) {
            return;
        }
        var t = typeSel.value;
        document.querySelectorAll('.mod-graphitoubb-field-group').forEach(function(g) {
            var types = (g.getAttribute('data-types') || '').split(' ');
            g.style.display = (types.indexOf(t) !== -1) ? '' : 'none';
        });
    };
    var toggleGrafoType = function() {
        if (!grafoTypeSel) {
            return;
        }
        var t = grafoTypeSel.value;
        document.querySelectorAll('.mod-graphitoubb-grafo-fields').forEach(function(g) {
            var types = (g.getAttribute('data-gtypes') || '').split(' ');
            g.style.display = (types.indexOf(t) !== -1) ? '' : 'none';
        });
    };
    if (toolSel) {
        toolSel.addEventListener('change', toggleTool);
    }
    if (typeSel) {
        typeSel.addEventListener('change', toggleType);
    }
    var toggleArbolType = function() {
        if (!arbolTypeSel) {
            return;
        }
        var t = arbolTypeSel.value;
        document.querySelectorAll('.mod-graphitoubb-arbol-fields').forEach(function(g) {
            var types = (g.getAttribute('data-atypes') || '').split(' ');
            g.style.display = (types.indexOf(t) !== -1) ? '' : 'none';
        });
    };
    if (grafoTypeSel) {
        grafoTypeSel.addEventListener('change', toggleGrafoType);
    }
    if (arbolTypeSel) {
        arbolTypeSel.addEventListener('change', toggleArbolType);
    }
    toggleTool();
    toggleType();
    toggleGrafoType();
    toggleArbolType();
});
JS);

echo $OUTPUT->footer();
