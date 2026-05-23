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
 * Serializer — JSON encode/decode and stable hashing for problem payloads.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use RuntimeException;

/**
 * Encodes and decodes problem arrays to/from JSON and computes a stable
 * content hash suitable for snapshot change detection.
 */
final class serializer {
    /**
     * JSON-encode a problem array.
     *
     * @param  array $problem
     * @return string JSON string.
     * @throws RuntimeException On encoding failure.
     */
    public function encode(array $problem): string {
        $json = json_encode($problem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * JSON-decode a problem JSON string to an associative array.
     *
     * @param  string $json
     * @return array
     * @throws RuntimeException On decoding failure or non-array root.
     */
    public function decode(string $json): array {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('json_decode failed: ' . json_last_error_msg());
        }
        if (!is_array($data)) {
            throw new RuntimeException('json_decode produced a non-array result.');
        }
        return $data;
    }

    /**
     * Compute a stable SHA-256 hash of a problem array.
     *
     * Keys are sorted recursively before encoding to ensure that arrays with
     * the same content but different key insertion order produce the same hash.
     *
     * @param  array $problem
     * @return string 64-character lowercase hexadecimal SHA-256 hash.
     * @throws RuntimeException On encoding failure.
     */
    public function hash(array $problem): string {
        $normalised = $this->sort_keys_recursive($problem);
        $json       = json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('json_encode failed during hash: ' . json_last_error_msg());
        }
        return hash('sha256', $json);
    }

    /**
     * Recursively sort array keys to produce a canonical key order.
     *
     * Numeric arrays (sequential integer keys) are left in their original order
     * because the sequence matters semantically (row order, column order).
     * Associative arrays have their keys sorted lexicographically.
     *
     * @param  mixed $data
     * @return mixed
     */
    private function sort_keys_recursive(mixed $data): mixed {
        if (!is_array($data)) {
            return $data;
        }

        // Determine if the array is sequential (0-indexed list).
        $is_sequential = array_keys($data) === range(0, count($data) - 1);

        $result = [];
        if ($is_sequential) {
            foreach ($data as $item) {
                $result[] = $this->sort_keys_recursive($item);
            }
        } else {
            ksort($data);
            foreach ($data as $k => $v) {
                $result[$k] = $this->sort_keys_recursive($v);
            }
        }

        return $result;
    }
}
