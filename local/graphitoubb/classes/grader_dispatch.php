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
 * Generic tool→grader dispatch (slug-keyed), mirroring tool_registry.
 *
 * Replaces the `=== 'afd'` hardcode at finish_attempt.php so grading scales to
 * grafo/arbol without touching truth_table's answer-model path (D5).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb;

use local_graphitoubb\tools\afd\grader\afd_grader_adapter;
use local_graphitoubb\tools\arbol\grader\arbol_grader;
use local_graphitoubb\tools\grafo\grader\grafo_grader;
use local_graphitoubb\tools\karnaugh\grader\karnaugh_grader;
use local_graphitoubb\tools\relations\grader\relations_grader;

/**
 * Maps a tool slug to its grader_interface implementation.
 */
final class grader_dispatch {
    /**
     * Return the grader for a tool slug, or null when the slug has no grader
     * registered (caller should treat as ungradeable rather than fatal).
     *
     * @param  string $toolslug
     * @return grader_interface|null
     */
    public static function for(string $toolslug): ?grader_interface {
        switch ($toolslug) {
            case 'afd':
                return new afd_grader_adapter();
            case 'grafo':
                return new grafo_grader();
            case 'arbol':
                return new arbol_grader();
            case 'karnaugh':
                return new karnaugh_grader();
            case 'relations':
                return new relations_grader();
            default:
                return null;
        }
    }

    /**
     * A shared "ungradeable / unknown tool" result (never fatals the WS).
     *
     * @param  string $message
     * @return array
     */
    public static function unsupported_result(string $message = 'unsupported_tool'): array {
        return [
            'graded'        => false,
            'invalid'       => true,
            'message'       => $message,
            'score'         => 0.0,
            'fraction'      => 0.0,
            'passed'        => false,
            'items_total'   => 0,
            'items_correct' => 0,
            'results'       => [],
        ];
    }
}
