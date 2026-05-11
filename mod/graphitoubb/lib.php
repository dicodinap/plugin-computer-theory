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

    return $DB->insert_record('graphitoubb', $data);
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

    return $DB->update_record('graphitoubb', $data);
}

/**
 * Deletes a mod_graphitoubb instance and all associated student data.
 *
 * @param int $id Instance id.
 * @return bool True on success, false if instance not found.
 */
function graphitoubb_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('graphitoubb', ['id' => $id])) {
        return false;
    }

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
