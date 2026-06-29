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
 * AFD grader — scores a student automaton against a problem's test words.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\grader;

use local_graphitoubb\tools\afd\domain\serializer;
use local_graphitoubb\tools\afd\domain\simulator;

/**
 * Grades a student-built automaton by running each authored test word through it
 * (server-side, via the domain simulator) and comparing the verdict to the
 * teacher's expected accept/reject. The fraction is the share of test words the
 * automaton classifies correctly.
 *
 * Pure (no DB) and deterministic, so it is unit-testable in isolation.
 */
final class afd_grader {
    /** Default passing threshold as a fraction of the test set. */
    public const PASS_THRESHOLD = 0.6;

    /**
     * Grade a student's automaton snapshot against the problem test words.
     *
     * @param array       $config       Problem config: ['alphabet' => string[],
     *                                   'test_words' => [['word' => string, 'accept' => bool], ...]].
     * @param string|null $snapshotjson Latest student snapshot (canonical automaton JSON), or null.
     * @return array Result with keys: graded(bool), invalid(bool), message(?string),
     *               score(float), fraction(float), passed(bool), words_total(int),
     *               words_correct(int), results(array<array{word,expected,got,correct}>).
     */
    public function grade(array $config, ?string $snapshotjson): array {
        $testwords = $config['test_words'] ?? [];
        $total     = count($testwords);

        // An automaton with no start state (or an unparsable snapshot) cannot be
        // simulated — it is ungradeable rather than "rejects everything".
        $automaton  = null;
        $invalidmsg = null;
        if ($snapshotjson === null || $snapshotjson === '') {
            $invalidmsg = 'no_automaton';
        } else {
            try {
                $raw = json_decode($snapshotjson, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($raw) || empty($raw['start'])) {
                    $invalidmsg = 'no_start';
                } else {
                    $automaton = (new serializer())->deserialize($snapshotjson);
                }
            } catch (\Throwable $e) {
                $invalidmsg = 'invalid';
            }
        }

        if ($automaton === null) {
            return [
                'graded'        => true,
                'invalid'       => true,
                'message'       => $invalidmsg,
                'score'         => 0.0,
                'fraction'      => 0.0,
                'passed'        => false,
                'words_total'   => $total,
                'words_correct' => 0,
                'results'       => [],
            ];
        }

        $sim     = new simulator();
        $results = [];
        $correct = 0;
        foreach ($testwords as $tw) {
            $word     = (string) ($tw['word'] ?? '');
            $expected = (bool) ($tw['accept'] ?? false);
            $got      = $sim->run($automaton, $word)->is_accepted();
            $ok       = ($got === $expected);
            if ($ok) {
                $correct++;
            }
            $results[] = [
                'word'     => $word,
                'expected' => $expected,
                'got'      => $got,
                'correct'  => $ok,
            ];
        }

        $fraction = $total > 0 ? $correct / $total : 0.0;
        return [
            'graded'        => true,
            'invalid'       => false,
            'message'       => null,
            'score'         => $fraction,
            'fraction'      => $fraction,
            'passed'        => $fraction >= self::PASS_THRESHOLD,
            'words_total'   => $total,
            'words_correct' => $correct,
            'results'       => $results,
        ];
    }
}
