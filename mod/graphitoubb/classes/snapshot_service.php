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

use mod_graphitoubb\exception\rate_limited_exception;

/**
 * Service for persisting tool-state snapshots during an attempt.
 *
 * Rate limit (D-B decision): server enforces 1 snapshot per second per attempt.
 * Per D-B: significance is client authority (server never re-checks whether a change
 * is "significant"). Structural payload validation (well-formed AFD JSON, bounds D-A)
 * IS performed server-side as defense-in-depth when validator/serializer deps are
 * injected — independent from the significance heuristic.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot_service {
    /** @var \local_graphitoubb\tools\afd\domain\validator|null */
    private ?\local_graphitoubb\tools\afd\domain\validator $validator;

    /** @var \local_graphitoubb\tools\afd\domain\serializer|null */
    private ?\local_graphitoubb\tools\afd\domain\serializer $serializer;

    /**
     * Constructor.
     *
     * @param \local_graphitoubb\tools\afd\domain\validator|null  $validator
     * @param \local_graphitoubb\tools\afd\domain\serializer|null $serializer
     */
    public function __construct(
        ?\local_graphitoubb\tools\afd\domain\validator $validator = null,
        ?\local_graphitoubb\tools\afd\domain\serializer $serializer = null,
    ) {
        $this->validator  = $validator;
        $this->serializer = $serializer;
    }

    /**
     * Persists a snapshot and returns its id.
     *
     * When validator and serializer deps are set, the payload is deserialized and
     * validated before storing; throws moodle_exception on invalid input.
     * When either dep is null the validation hook is skipped (preserves S5 compat).
     *
     * Throws rate_limited_exception if a snapshot for this attempt was already
     * saved in the current second (timecreated >= time()).
     *
     * @param int    $attemptid     Attempt id.
     * @param string $payload       Canonical JSON of tool state.
     * @param int    $schemaversion Tool schema version.
     * @return int New snapshot id.
     * @throws \moodle_exception       When payload fails AFD validation.
     * @throws rate_limited_exception  When rate limit is exceeded.
     */
    public function save(int $attemptid, string $payload, int $schemaversion): int {
        global $DB;

        if ($this->validator !== null && $this->serializer !== null) {
            try {
                $automaton = $this->serializer->deserialize($payload);
            } catch (\InvalidArgumentException $e) {
                throw new \moodle_exception('invalid_snapshot', 'mod_graphitoubb');
            }
            $errors = $this->validator->validate($automaton);
            if ($errors) {
                throw new \moodle_exception('invalid_snapshot', 'mod_graphitoubb');
            }
        }

        // Fetch the most recent snapshot timecreated for this attempt.
        $rows = $DB->get_records_sql(
            'SELECT timecreated FROM {graphitoubb_snapshot} WHERE attemptid = ? ORDER BY id DESC',
            [$attemptid],
            0,
            1
        );
        $last = $rows ? reset($rows) : null;

        if ($last !== null && (int) $last->timecreated >= time()) {
            throw new rate_limited_exception();
        }

        $id = $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $attemptid,
            'payload'        => $payload,
            'schema_version' => $schemaversion,
            'timecreated'    => time(),
        ]);

        return (int) $id;
    }

    /**
     * Returns the most recent snapshot for an attempt, or null if none exists.
     *
     * @param int $attemptid Attempt id.
     * @return \stdClass|null Snapshot row or null.
     */
    public function get_latest(int $attemptid): ?\stdClass {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT * FROM {graphitoubb_snapshot} WHERE attemptid = ? ORDER BY id DESC',
            [$attemptid],
            0,
            1
        );
        return $rows ? reset($rows) : null;
    }

    /**
     * Returns the number of snapshots saved for an attempt.
     *
     * @param int $attemptid Attempt id.
     * @return int Count of snapshots.
     */
    public function count_for_attempt(int $attemptid): int {
        global $DB;
        return (int) $DB->count_records('graphitoubb_snapshot', ['attemptid' => $attemptid]);
    }
}
