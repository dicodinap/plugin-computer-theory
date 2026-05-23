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

declare(strict_types=1);

namespace mod_graphitoubb;

use local_graphitoubb\tools\truth_table\domain\serializer;

/**
 * Data-access object for graphitoubb_problem records.
 *
 * Pattern: Repository (single table, no cross-join queries).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class problem_repository {
    /**
     * Persist a problem payload.
     *
     * If a record already exists for the instance, it is updated (upsert by instanceid).
     * payload_hash is computed from the payload array using serializer::hash().
     *
     * @param int    $instanceid     graphitoubb.id
     * @param string $tool           Tool slug (e.g. 'truth_table').
     * @param string $type           Sub-type (e.g. 'complete').
     * @param array  $payload        Decoded problem array.
     * @param int    $schemaversion  Schema version integer.
     * @return int  Record id.
     */
    public function save(int $instanceid, string $tool, string $type, array $payload, int $schemaversion): int {
        global $DB;

        $ser  = new serializer();
        $json = $ser->encode($payload);
        $hash = $ser->hash($payload);
        $now  = time();

        $existing = $DB->get_record('graphitoubb_problem', ['instanceid' => $instanceid]);
        if ($existing) {
            $existing->tool           = $tool;
            $existing->type           = $type;
            $existing->payload        = $json;
            $existing->payload_hash   = $hash;
            $existing->schema_version = $schemaversion;
            $existing->timemodified   = $now;
            $DB->update_record('graphitoubb_problem', $existing);
            return (int) $existing->id;
        }

        $record = (object) [
            'instanceid'     => $instanceid,
            'tool'           => $tool,
            'type'           => $type,
            'payload'        => $json,
            'payload_hash'   => $hash,
            'schema_version' => $schemaversion,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ];
        return (int) $DB->insert_record('graphitoubb_problem', $record);
    }

    /**
     * Find the problem record for an instance.
     *
     * @param int $instanceid
     * @return \stdClass|null
     */
    public function find_by_instance(int $instanceid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('graphitoubb_problem', ['instanceid' => $instanceid]);
        return $record ?: null;
    }

    /**
     * Delete the problem record for an instance.
     *
     * @param int $instanceid
     */
    public function delete_by_instance(int $instanceid): void {
        global $DB;
        $DB->delete_records('graphitoubb_problem', ['instanceid' => $instanceid]);
    }
}
