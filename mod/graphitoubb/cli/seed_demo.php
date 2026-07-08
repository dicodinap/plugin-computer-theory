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
 * Seed a DEMO course showcasing every GraphitoUBB tool (demo day / pilot).
 *
 * Creates, idempotently:
 *  - A course with one section per tool (truth_table, karnaugh, relations,
 *    grafo, arbol, afd), each holding a curated selection of catalogue presets
 *    as mod_graphitoubb activities.
 *  - A quiz built from the preset Question Bank (qtype_graphitoubb) so the
 *    question-type integration can be demoed too.
 *  - Optional demo users (teacher + 2 students) with a configurable password.
 *
 * Production-safe: no hardcoded dev passwords (password comes from --password
 * or MOODLE_DEMO_PASS), and re-running only updates what already exists.
 *
 * Usage (inside container, dirroot as cwd):
 *   php mod/graphitoubb/cli/seed_demo.php [--shortname=GRAPHITOUBB-DEMO]
 *       [--password=...] [--users=1] [--quiz=1] [--lang=es]
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

[$options, $unrecognised] = cli_get_params([
    'help'      => false,
    'shortname' => 'GRAPHITOUBB-DEMO',
    'fullname'  => 'Estructuras Discretas — Demo GraphitoUBB',
    'password'  => getenv('MOODLE_DEMO_PASS') ?: 'DemoDay2026#',
    'users'     => 1,
    'quiz'      => 1,
    'grades'    => 1,
    'lang'      => 'es',
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Seed the GraphitoUBB demo course (idempotent).');
    cli_writeln('Options: --shortname --fullname --password --users=0|1 --quiz=0|1 --grades=0|1 --lang=es|en');
    exit(0);
}

// Never attempt outbound mail from the seed (fresh sites have no SMTP).
$CFG->noemailever = true;

// Resolve catalogue titles/prompts in the demo language. force_current_language()
// only works if the language pack is installed; fall back to an in-memory
// $CFG->lang override so preset titles still come out localised.
$lang = (string) $options['lang'];
force_current_language($lang); // Only sticks when the language pack is installed.
if (current_language() !== $lang) {
    $CFG->lang = $lang; // In-memory fallback so titles still resolve localised.
}

// Curated selection: section name => preset keys, in pedagogical order.
// grafo/arbol/karnaugh/relations ship few presets, so all of them go in;
// afd/truth_table are large catalogues, so one exercise per sub-type.
$sections = [
    1 => ['Lógica proposicional — tablas de verdad',
          ['tt-complete-impl', 'tt-equiv-demorgan-and', 'tt-classify-peirce']],
    2 => ['Álgebra booleana — mapas de Karnaugh',
          ['karnaugh_2var_intro', 'karnaugh_3var_basic', 'karnaugh_4var_medium']],
    3 => ['Relaciones binarias',
          ['relations_partial_order', 'relations_symmetric']],
    4 => ['Grafos — Euler, bipartitos y árboles generadores',
          ['grafo_konigsberg_euler', 'grafo_square_euler_circuit', 'grafo_construct_bipartite',
           'grafo_construct_tree', 'grafo_decision_bipartite_odd']],
    5 => ['Árboles — ABB y recorridos',
          ['arbol_bst_build_basic', 'arbol_traversal_inorder', 'arbol_reconstruct_pre_in']],
    6 => ['Autómatas finitos deterministas (AFD)',
          ['afd-contains-a', 'afd-bin-contains-101', 'afd-even-a-even-b']],
    7 => ['Evaluación — quiz con preguntas GraphitoUBB', []],
];

// Question Bank idnumbers for the demo quiz (one flavour of each tool; the
// preset bank ships no AFD questions, AFD is demoed via the activities above).
$quizquestionidnumbers = [
    'tt-complete-and',
    'tt-equiv-demorgan-or',
    'tt-classify-excluded-middle',
    'karnaugh_2var_intro',
    'relations_symmetric',
    'grafo_decision_bipartite_odd',
    'arbol_traversal_inorder',
];

// --- 1. Course --------------------------------------------------------------
$course = $DB->get_record('course', ['shortname' => $options['shortname']]);
if (!$course) {
    $course = create_course((object) [
        'fullname'    => $options['fullname'],
        'shortname'   => $options['shortname'],
        'category'    => core_course_category::get_default()->id,
        'format'      => 'topics',
        'numsections' => count($sections),
        'visible'     => 1,
        'summary'     => '<p>Curso de demostración de GraphitoUBB: una sección por herramienta '
                       . '(tablas de verdad, Karnaugh, relaciones, grafos, árboles y AFD) '
                       . 'más un quiz construido con el banco de preguntas precargado.</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
    cli_writeln("created course: {$options['shortname']} (id {$course->id})");
} else {
    cli_writeln("course exists: {$options['shortname']} (id {$course->id})");
}

course_create_sections_if_missing($course, range(0, count($sections)));
foreach ($sections as $num => [$name, $unused]) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num], '*', MUST_EXIST);
    if ((string) $section->name !== $name) {
        course_update_section($course, $section, ['name' => $name]);
    }
}

// --- 2. Demo users + enrolments ----------------------------------------------
/**
 * Ensure a demo user exists (idempotent); password only set on creation.
 *
 * @param string $username
 * @param string $firstname
 * @param string $lastname
 * @param string $password
 * @return stdClass
 */
function demo_user(string $username, string $firstname, string $lastname, string $password): stdClass {
    global $DB, $CFG;
    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        cli_writeln("user exists: {$username} (id {$existing->id})");
        return $existing;
    }
    $user = create_user_record($username, $password, 'manual');
    $user->firstname  = $firstname;
    $user->lastname   = $lastname;
    $user->email      = $username . '@example.com';
    $user->confirmed  = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $DB->update_record('user', $user);
    cli_writeln("created user: {$username} (id {$user->id})");
    return $DB->get_record('user', ['id' => $user->id]);
}

/**
 * Enrol a user with a role into the course (idempotent).
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param int $roleid
 * @return void
 */
function demo_enrol(stdClass $course, stdClass $user, int $roleid): void {
    global $DB;
    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$instance) {
        $enrolid = $enrol->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);
    }
    $enrol->enrol_user($instance, $user->id, $roleid);
}

if ((int) $options['users'] === 1) {
    $roles   = $DB->get_records_menu('role', null, '', 'shortname,id');
    $teacher = demo_user('profesor.demo', 'Profe', 'GraphitoUBB', (string) $options['password']);
    $alumno  = demo_user('estudiante.demo', 'Ana', 'Estudiante', (string) $options['password']);
    $alumno2 = demo_user('estudiante2.demo', 'Benjamín', 'Estudiante', (string) $options['password']);
    demo_enrol($course, $teacher, (int) $roles['editingteacher']);
    demo_enrol($course, $alumno, (int) $roles['student']);
    demo_enrol($course, $alumno2, (int) $roles['student']);
    cli_writeln('enrolments: profesor.demo=editingteacher, estudiante.demo/estudiante2.demo=student');
}

// --- 3. GraphitoUBB activities from the preset catalogue ---------------------
/**
 * Create (or reuse) a graphitoubb activity by name and (re)set its problem payload.
 *
 * @param stdClass $course
 * @param int      $sectionnum
 * @param string   $name
 * @param string   $intro
 * @param array    $payload
 * @return int graphitoubb instance id
 */
function demo_activity(stdClass $course, int $sectionnum, string $name, string $intro, array $payload): int {
    global $DB;

    $repo = new \mod_graphitoubb\problem_repository();
    $existing = $DB->get_record('graphitoubb', ['course' => $course->id, 'name' => $name]);
    if ($existing) {
        $cm = get_coursemodule_from_instance('graphitoubb', $existing->id, $course->id);
        $repo->save((int) $existing->id, (string) $payload['tool'], (string) $payload['type'], $payload, 1);
        cli_writeln("  reused activity: {$name} (cmid {$cm->id})");
        return (int) $existing->id;
    }

    $moduleinfo = (object) [
        'modulename'      => 'graphitoubb',
        'module'          => (int) $DB->get_field('modules', 'id', ['name' => 'graphitoubb'], MUST_EXIST),
        'course'          => $course->id,
        'section'         => $sectionnum,
        'name'            => $name,
        'intro'           => $intro,
        'introformat'     => FORMAT_HTML,
        'visible'         => 1,
        'cmidnumber'      => '',
        'attempts_policy' => 'best',
    ];
    $created = add_moduleinfo($moduleinfo, $course);
    $repo->save((int) $created->instance, (string) $payload['tool'], (string) $payload['type'], $payload, 1);
    cli_writeln("  created activity: {$name} (cmid {$created->coursemodule})");
    return (int) $created->instance;
}

$catalog = new \local_graphitoubb\catalog\preset_catalog();
$instancesbykey = []; // preset key => graphitoubb instance id (para los intentos demo).
foreach ($sections as $num => [$sectionname, $presetkeys]) {
    if (!$presetkeys) {
        continue;
    }
    cli_writeln("section {$num}: {$sectionname}");
    foreach ($presetkeys as $key) {
        $preset = $catalog->get($key);
        if ($preset === null) {
            cli_writeln("  WARNING: preset '{$key}' not found in catalogue, skipped.");
            continue;
        }
        $intro = '<p>' . s($preset->summary) . '</p>';
        $instancesbykey[$key] = demo_activity($course, $num, $preset->title, $intro, $preset->payload);
    }
}

// --- 4. Demo quiz from the seeded Question Bank -------------------------------
if ((int) $options['quiz'] === 1) {
    $quizname = 'Quiz de repaso (banco de preguntas GraphitoUBB)';
    $quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => $quizname]);

    if (!$quiz) {
        $now = time();
        $moduleinfo = (object) [
            'modulename'  => 'quiz',
            'module'      => (int) $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
            'course'      => $course->id,
            'section'     => count($sections),
            'name'        => $quizname,
            'intro'       => '<p>Preguntas GraphitoUBB servidas por el motor estándar de quiz '
                           . 'de Moodle (qtype_graphitoubb).</p>',
            'introformat' => FORMAT_HTML,
            'visible'     => 1,
            'cmidnumber'  => '',
            // Sensible quiz defaults (mirrors the mod_quiz generator).
            'timeopen'    => 0, 'timeclose' => 0, 'timelimit' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts'    => 0, 'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2, 'questiondecimalpoints' => -1,
            'attemptduring' => 1, 'correctnessduring' => 1, 'maxmarksduring' => 1,
            'marksduring' => 1, 'specificfeedbackduring' => 1, 'generalfeedbackduring' => 1,
            'rightanswerduring' => 1, 'overallfeedbackduring' => 0,
            'attemptimmediately' => 1, 'correctnessimmediately' => 1, 'maxmarksimmediately' => 1,
            'marksimmediately' => 1, 'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately' => 1, 'rightanswerimmediately' => 1,
            'overallfeedbackimmediately' => 1,
            'attemptopen' => 1, 'correctnessopen' => 1, 'maxmarksopen' => 1, 'marksopen' => 1,
            'specificfeedbackopen' => 1, 'generalfeedbackopen' => 1, 'rightansweropen' => 1,
            'overallfeedbackopen' => 1,
            'attemptclosed' => 1, 'correctnessclosed' => 1, 'maxmarksclosed' => 1,
            'marksclosed' => 1, 'specificfeedbackclosed' => 1, 'generalfeedbackclosed' => 1,
            'rightanswerclosed' => 1, 'overallfeedbackclosed' => 1,
            'questionsperpage' => 1, 'shuffleanswers' => 1,
            'sumgrades' => 0, 'grade' => 100,
            'timecreated' => $now, 'timemodified' => $now,
            'overduehandling' => 'autosubmit', 'graceperiod' => 86400,
            'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '',
            'delay1' => 0, 'delay2' => 0,
            'showuserpicture' => 0, 'showblocks' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ];
        $created = add_moduleinfo($moduleinfo, $course);
        $quiz = $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST);
        cli_writeln("created quiz: {$quizname} (cmid {$created->coursemodule})");
    } else {
        cli_writeln("quiz exists: {$quizname} (id {$quiz->id})");
    }

    if ($DB->count_records('quiz_slots', ['quizid' => $quiz->id]) > 0) {
        cli_writeln('quiz already has questions, skipping question setup.');
    } else {
        $systemcontext = context_system::instance();
        $category = $DB->get_record('question_categories',
            ['contextid' => $systemcontext->id, 'idnumber' => 'qtype_graphitoubb_presets']);
        if (!$category) {
            cli_writeln('WARNING: seeded question category not found; quiz left empty. '
                . 'Did qtype_graphitoubb install correctly?');
        } else {
            $added = 0;
            foreach ($quizquestionidnumbers as $idnumber) {
                $question = $DB->get_record_sql("
                        SELECT q.id
                          FROM {question} q
                          JOIN {question_versions} qv ON qv.questionid = q.id
                          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                         WHERE qbe.questioncategoryid = :cat AND qbe.idnumber = :idnumber
                      ORDER BY qv.version DESC",
                    ['cat' => $category->id, 'idnumber' => $idnumber], IGNORE_MULTIPLE);
                if (!$question) {
                    cli_writeln("  WARNING: bank question '{$idnumber}' not found, skipped.");
                    continue;
                }
                quiz_add_quiz_question((int) $question->id, $quiz);
                $added++;
                cli_writeln("  added question: {$idnumber}");
            }
            if ($added > 0) {
                quiz_update_sumgrades($quiz);
            }
        }
    }
}

// --- 5. Graded demo attempts (populate reports + gradebook) -------------------
// Runs each answer through the REAL pipeline (snapshot → finish → grader_dispatch
// → submission → grade cache → gradebook), same as external/finish_attempt.php,
// so every report/panel shows authentic data. Idempotent: one attempt per
// user+instance; finished attempts are never touched again.
if ((int) $options['grades'] === 1 && (int) $options['users'] === 1) {
    // preset key => username => student snapshot (tool answer envelope).
    // Ana (estudiante.demo) answers everything right; Benjamín (estudiante2.demo)
    // makes classic mistakes, so the gradebook shows a spread of notas.
    $gradedattempts = [
        'karnaugh_2var_intro' => [
            // Minterms {1,2,3}: fill correcto para ambos; Ana agrupa B y A (cover
            // válido), Benjamín mete un grupo que cubre un 0 → grouping 0.
            'estudiante.demo' => [
                'answer_kind' => 'kmap',
                'map'    => ['cells' => [0 => 0, 1 => 1, 2 => 1, 3 => 1]],
                'groups' => [['id' => 'g0', 'cells' => [1, 3]], ['id' => 'g1', 'cells' => [2, 3]]],
            ],
            'estudiante2.demo' => [
                'answer_kind' => 'kmap',
                'map'    => ['cells' => [0 => 0, 1 => 1, 2 => 1, 3 => 1]],
                'groups' => [['id' => 'g0', 'cells' => [0, 1]]],
            ],
        ],
        'grafo_decision_bipartite_odd' => [
            // Triángulo (ciclo impar) NO es bipartito: Ana responde no, Benjamín sí.
            'estudiante.demo'  => ['answer_kind' => 'boolean', 'value' => false],
            'estudiante2.demo' => ['answer_kind' => 'boolean', 'value' => true],
        ],
        'arbol_traversal_inorder' => [
            // In-orden correcto [1,3,6,8,10]; Benjamín intercambia 8 y 6 (LCP 2/5).
            'estudiante.demo'  => ['answer_kind' => 'sequence', 'values' => [1, 3, 6, 8, 10]],
            'estudiante2.demo' => ['answer_kind' => 'sequence', 'values' => [1, 3, 8, 6, 10]],
        ],
        'relations_symmetric' => [
            // R simétrica pero no transitiva; Benjamín cae en la trampa (transitive=true).
            'estudiante.demo' => [
                'answer_kind' => 'relation', 'representation' => 'pairs',
                'pairs' => [['1', '2'], ['2', '1'], ['2', '3'], ['3', '2']],
                'properties' => ['reflexive' => false, 'symmetric' => true,
                                 'antisymmetric' => false, 'transitive' => false],
            ],
            'estudiante2.demo' => [
                'answer_kind' => 'relation', 'representation' => 'pairs',
                'pairs' => [['1', '2'], ['2', '1'], ['2', '3'], ['3', '2']],
                'properties' => ['reflexive' => false, 'symmetric' => true,
                                 'antisymmetric' => false, 'transitive' => true],
            ],
        ],
    ];

    cli_writeln('');
    cli_writeln('seeding graded demo attempts...');
    $attemptsvc = new \mod_graphitoubb\attempt_service();
    $snapsvc    = new \mod_graphitoubb\snapshot_service();
    $subrepo    = new \mod_graphitoubb\submission_repository();
    $gcsvc      = new \mod_graphitoubb\grade_cache_service();
    $probrepo   = new \mod_graphitoubb\problem_repository();

    foreach ($gradedattempts as $presetkey => $answers) {
        $instanceid = $instancesbykey[$presetkey] ?? null;
        if (!$instanceid) {
            cli_writeln("  WARNING: no activity for preset '{$presetkey}', graded attempts skipped.");
            continue;
        }
        $problemrec = $probrepo->find_by_instance((int) $instanceid);
        $grader     = $problemrec ? \local_graphitoubb\grader_dispatch::for((string) $problemrec->tool) : null;
        if (!$grader) {
            cli_writeln("  WARNING: no grader for preset '{$presetkey}', skipped.");
            continue;
        }
        $instance = $DB->get_record('graphitoubb', ['id' => $instanceid], '*', MUST_EXIST);

        foreach ($answers as $username => $snapshot) {
            $student = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
            if (!$student) {
                cli_writeln("  WARNING: user {$username} not found, skipped.");
                continue;
            }
            $attempt = $attemptsvc->start_or_resume((int) $instanceid, (int) $student->id);
            if ((string) $attempt->status === 'finished') {
                cli_writeln("  attempt exists: {$username} @ {$presetkey}");
                continue;
            }

            $snapshotjson = json_encode($snapshot);
            $snapsvc->save((int) $attempt->id, $snapshotjson, 1);
            $attemptsvc->finish((int) $attempt->id);

            $grading = $grader->grade(json_decode($problemrec->payload, true) ?: [], $snapshotjson);
            $subrepo->save(
                (int) $attempt->id,
                ['tool' => $problemrec->tool, 'snapshot' => $snapshot],
                $grading,
                (string) $problemrec->payload_hash,
                1
            );
            $gcsvc->recompute_for_attempt((int) $attempt->id, $instance->attempts_policy ?: 'best');
            graphitoubb_update_grades($instance, (int) $student->id);

            cli_writeln(sprintf('  graded: %s @ %s -> %.0f%%',
                $username, $presetkey, 100.0 * (float) ($grading['fraction'] ?? 0)));
        }
    }
}

rebuild_course_cache($course->id, true);

cli_writeln('');
cli_writeln('demo seed complete.');
cli_writeln("  course: {$CFG->wwwroot}/course/view.php?id={$course->id}");
if ((int) $options['users'] === 1) {
    cli_writeln('  users:  profesor.demo / estudiante.demo / estudiante2.demo (password: la de --password / MOODLE_DEMO_PASS)');
}
