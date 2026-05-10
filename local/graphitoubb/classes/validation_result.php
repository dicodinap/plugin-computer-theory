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
 * Validation result value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb;

/**
 * Immutable result of a tool validation call.
 */
final class validation_result {
    /** @var bool True when validation passed. */
    public readonly bool $ok;

    /** @var string[] Human-readable error messages; empty when ok. */
    public readonly array $errors;

    /**
     * Build a validation result.
     *
     * @param bool     $ok
     * @param string[] $errors
     */
    public function __construct(bool $ok, array $errors = []) {
        $this->ok     = $ok;
        $this->errors = $errors;
    }

    /**
     * Return a passing result with no errors.
     *
     * @return self
     */
    public static function pass(): self {
        return new self(true);
    }

    /**
     * Return a failing result with the given error messages.
     *
     * @param string[] $errors
     * @return self
     */
    public static function fail(array $errors): self {
        return new self(false, $errors);
    }
}
