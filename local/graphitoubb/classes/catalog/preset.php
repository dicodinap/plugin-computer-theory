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
 * Preset — one curated catalogue exercise (immutable value object).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\catalog;

/**
 * A single curated exercise from the preset catalogue.
 *
 * The catalogue is the shared source of truth for the activity-Problem template
 * picker (mod_graphitoubb) and the Question Bank seeder (qtype_graphitoubb).
 * Localised metadata (title/summary/prompt) is resolved to the requested language
 * at construction time; {@see $payload} is the canonical, persistence-ready
 * problem payload that grading and the schema loader understand.
 */
final class preset {
    /** @var string Stable identifier, unique within the catalogue. */
    public string $key;

    /** @var string Tool slug: 'afd' or 'truth_table'. */
    public string $tool;

    /** @var string Problem sub-type: 'language' | 'complete' | 'equivalence' | 'classify'. */
    public string $type;

    /** @var string Difficulty hint: 'easy' | 'medium' | 'hard'. */
    public string $difficulty;

    /** @var string[] Free-form classification tags. */
    public array $tags;

    /** @var string Localised short title for the picker. */
    public string $title;

    /** @var string Localised one-line summary for the picker. */
    public string $summary;

    /** @var array Canonical problem payload, ready to persist and grade. */
    public array $payload;

    /**
     * @param string   $key
     * @param string   $tool
     * @param string   $type
     * @param string   $difficulty
     * @param string[] $tags
     * @param string   $title
     * @param string   $summary
     * @param array    $payload
     */
    public function __construct(
        string $key,
        string $tool,
        string $type,
        string $difficulty,
        array $tags,
        string $title,
        string $summary,
        array $payload
    ) {
        $this->key        = $key;
        $this->tool       = $tool;
        $this->type       = $type;
        $this->difficulty = $difficulty;
        $this->tags       = $tags;
        $this->title      = $title;
        $this->summary    = $summary;
        $this->payload    = $payload;
    }
}
