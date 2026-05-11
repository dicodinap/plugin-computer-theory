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

namespace qtype_graphitoubb\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider for qtype_graphitoubb.
 *
 * This question type stores instructor-owned question definitions only.
 * No per-student personal data is stored in question type tables.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements null_provider {
    /**
     * Returns the reason why this plugin stores no personal data.
     *
     * @return string Lang string key.
     */
    public static function get_reason(): string {
        return 'privacy:no_user_data_reason';
    }
}
