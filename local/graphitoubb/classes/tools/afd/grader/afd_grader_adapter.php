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
 * Adapter wrapping the production-verified afd_grader in the shared contract (I1).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\grader;

use local_graphitoubb\grader_interface;

/**
 * Maps the stored AFD problem to afd_grader's ($config, $snapshotjson) input and
 * normalises its output to the shared result array. The afd_grader internals are
 * NOT modified; every key it returns keeps its exact value (I1). The adapter only
 * ADDS the generic items_* aliases of words_*.
 */
final class afd_grader_adapter implements grader_interface {
    /**
     * Grade an AFD problem via afd_grader.
     *
     * @param  array       $problem        Decoded problem payload ({tool,type,config,...}).
     * @param  string|null $submissionjson Latest automaton snapshot JSON, or null.
     * @return array Shared result array (afd keys preserved + items_* aliases).
     */
    public function grade(array $problem, ?string $submissionjson): array {
        $config = $problem['config'] ?? [];
        $result = (new afd_grader())->grade($config, $submissionjson);

        // Additive generic count pair (I1: existing keys unchanged, new keys allowed).
        $result['items_total']   = (int) ($result['words_total'] ?? 0);
        $result['items_correct'] = (int) ($result['words_correct'] ?? 0);

        return $result;
    }
}
