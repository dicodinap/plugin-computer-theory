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
 * Library functions for mod_graphitoubb (Moodle activity hooks).
 *
 * No declare(strict_types=1) — Moodle hook contract requires loose typing here.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var float Chilean grade scale bounds and passing threshold (exigencia). */
define('MOD_GRAPHITOUBB_GRADEMAX', 7.0);
define('MOD_GRAPHITOUBB_GRADEMIN', 1.0);
// Passing threshold: 60% of the content maps to the passing grade 4.0.
define('MOD_GRAPHITOUBB_EXIGENCIA', 0.60);

/**
 * Converts a [0,1] fraction to the Chilean 1.0–7.0 grade scale.
 *
 * Two-segment linear map with a configurable exigencia p (default 60%):
 *   f ≥ p:  4.0 + (f − p)/(1 − p) · 3.0   (60% → 4.0 … 100% → 7.0)
 *   f < p:  1.0 + (f / p)     · 3.0        (0% → 1.0 … 60% → 4.0)
 * Rounded to one decimal, matching Chilean grade-reporting convention.
 *
 * @param float $fraction Fraction in [0,1] (clamped).
 * @return float Grade in [1.0, 7.0], one decimal.
 */
function graphitoubb_fraction_to_grade($fraction) {
    $p = MOD_GRAPHITOUBB_EXIGENCIA;
    $f = max(0.0, min(1.0, (float) $fraction));

    if ($f >= $p) {
        $nota = 4.0 + (($f - $p) / (1.0 - $p)) * 3.0;
    } else {
        $nota = 1.0 + ($f / $p) * 3.0;
    }

    return round($nota, 1);
}

/**
 * Returns the list of features this module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if supported, null if unknown.
 */
function graphitoubb_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new mod_graphitoubb instance to the database.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_graphitoubb_mod_form|null $mform Form instance (unused in v1).
 * @return int New instance id.
 */
function graphitoubb_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    $data->id = $DB->insert_record('graphitoubb', $data);

    // Create the gradebook item for this new instance (itemnumber 0).
    graphitoubb_grade_item_update($data);

    return $data->id;
}

/**
 * Updates an existing mod_graphitoubb instance.
 *
 * @param stdClass $data Form data; must include $data->instance = id.
 * @param mod_graphitoubb_mod_form|null $mform Form instance (unused in v1).
 * @return bool True on success.
 */
function graphitoubb_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id           = $data->instance;

    // Detect whether the grading policy changed so we can re-push every user's grade.
    $oldpolicy = $DB->get_field('graphitoubb', 'attempts_policy', ['id' => $data->id]);

    $result = $DB->update_record('graphitoubb', $data);

    // Keep the gradebook item in sync (name / settings).
    graphitoubb_grade_item_update($data);

    // If the aggregation policy changed, the per-user grade may change ⇒ re-push all.
    $newpolicy = isset($data->attempts_policy) ? $data->attempts_policy : $oldpolicy;
    if ($oldpolicy !== null && $newpolicy !== $oldpolicy) {
        graphitoubb_update_grades($data, 0);
    }

    return $result;
}

/**
 * Deletes a mod_graphitoubb instance and all associated student data.
 *
 * @param int $id Instance id.
 * @return bool True on success, false if instance not found.
 */
function graphitoubb_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('graphitoubb', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    // Remove the gradebook item for this instance.
    graphitoubb_grade_item_delete($instance);

    $attemptids = $DB->get_fieldset_select('graphitoubb_attempt', 'id', 'instanceid = ?', [$id]);
    if ($attemptids) {
        [$insql, $inparams] = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('graphitoubb_snapshot', "attemptid $insql", $inparams);
        $DB->delete_records_select('graphitoubb_wordbank_log', "attemptid $insql", $inparams);
    }

    $DB->delete_records('graphitoubb_attempt', ['instanceid' => $id]);
    $DB->delete_records('graphitoubb', ['id' => $id]);

    return true;
}

/**
 * Returns display info for the activity in course listings.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|false Info object or false if instance not found.
 */
function graphitoubb_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'graphitoubb',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat'
    );

    if (!$instance) {
        return false;
    }

    $info       = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('graphitoubb', $instance, $coursemodule->id, false);
    }

    $info->customdata = \core_text::substr(trim(strip_tags($instance->intro)), 0, 200);

    return $info;
}

/**
 * Creates or updates the gradebook grade item for a mod_graphitoubb instance.
 *
 * Single grade item (itemnumber 0), value type on the Chilean 1.0–7.0 scale, no
 * scales or outcomes. The [0,1] fraction is mapped by graphitoubb_fraction_to_grade().
 *
 * @param stdClass $instance Instance record; must include id, course and name.
 * @param mixed    $grades   Grade object/array keyed by userid, 'reset', or null.
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, etc.
 */
function graphitoubb_grade_item_update($instance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname'  => $instance->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => MOD_GRAPHITOUBB_GRADEMAX,
        'grademin'  => MOD_GRAPHITOUBB_GRADEMIN,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/graphitoubb',
        $instance->course,
        'mod',
        'graphitoubb',
        $instance->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the gradebook grade item for a mod_graphitoubb instance.
 *
 * @param stdClass $instance Instance record; must include id and course.
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, etc.
 */
function graphitoubb_grade_item_delete($instance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/graphitoubb',
        $instance->course,
        'mod',
        'graphitoubb',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Computes per-user gradebook grades for a mod_graphitoubb instance.
 *
 * The gradebook grade is per-user per-instance: the instance's attempts_policy is
 * applied BETWEEN the user's attempts (join graphitoubb_attempt → graphitoubb_grade_cache
 * by attemptid, considering only attempts that have a cached grade). rawgrade is the
 * fraction mapped to the Chilean 1.0–7.0 scale by graphitoubb_fraction_to_grade().
 *
 * @param stdClass $instance Instance record (loaded from the graphitoubb table).
 * @param int      $userid   Optional single user id; 0 for all users.
 * @return array<int,stdClass> Map of userid → grade object with ->userid and ->rawgrade.
 */
function graphitoubb_get_user_grades($instance, $userid = 0) {
    global $DB;

    // Ensure we have the policy even if a partial record was passed in.
    if (!isset($instance->attempts_policy)) {
        $instance = $DB->get_record('graphitoubb', ['id' => $instance->id], '*', MUST_EXIST);
    }
    $policy = $instance->attempts_policy ?: 'best';

    $params = ['instanceid' => $instance->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND a.userid = :userid';
        $params['userid'] = $userid;
    }

    // One row per attempt that has a cached grade, ordered so end() = most recent attempt.
    $sql = "SELECT a.id AS attemptid, a.userid, a.timefinished, gc.fraction
              FROM {graphitoubb_attempt} a
              JOIN {graphitoubb_grade_cache} gc ON gc.attemptid = a.id
             WHERE a.instanceid = :instanceid $usersql
          ORDER BY a.userid ASC, a.timefinished ASC, a.id ASC";
    $rows = $DB->get_records_sql($sql, $params);

    $byuser = [];
    foreach ($rows as $row) {
        $byuser[(int) $row->userid][] = $row;
    }

    $grades = [];
    foreach ($byuser as $uid => $attempts) {
        $fractions = array_map(static fn($r): float => (float) $r->fraction, $attempts);

        switch ($policy) {
            case 'last':
                $chosen   = end($attempts);
                $fraction = (float) $chosen->fraction;
                break;

            case 'average':
                $fraction = array_sum($fractions) / count($fractions);
                break;

            case 'best':
            default:
                $fraction = max($fractions);
                break;
        }

        $grade           = new stdClass();
        $grade->userid   = $uid;
        $grade->rawgrade = graphitoubb_fraction_to_grade($fraction);
        $grades[$uid]    = $grade;
    }

    return $grades;
}

/**
 * Pushes mod_graphitoubb grades into the gradebook.
 *
 * @param stdClass $instance   Instance record.
 * @param int      $userid     Optional single user id; 0 for all users.
 * @param bool     $nullifnone When true and a single user has no grade, push a null grade.
 * @return void
 */
function graphitoubb_update_grades($instance, $userid = 0, $nullifnone = true) {
    global $DB;

    if (!isset($instance->attempts_policy)) {
        $instance = $DB->get_record('graphitoubb', ['id' => $instance->id], '*', MUST_EXIST);
    }

    if ($grades = graphitoubb_get_user_grades($instance, $userid)) {
        graphitoubb_grade_item_update($instance, $grades);
    } else if ($userid && $nullifnone) {
        $grade           = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        graphitoubb_grade_item_update($instance, $grade);
    } else {
        graphitoubb_grade_item_update($instance);
    }
}
