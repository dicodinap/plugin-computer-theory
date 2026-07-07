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
 * Unit tests for the AFD grader.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\grader;

/**
 * @covers \local_graphitoubb\tools\afd\grader\afd_grader
 */
final class afd_grader_test extends \basic_testcase {

    /**
     * Snapshot JSON for a DFA over {a,b} that accepts words containing at least one 'a'.
     *
     * q0 (start) --a--> q1 (final);  q0 --b--> q0;  q1 --a--> q1;  q1 --b--> q1.
     *
     * @return string
     */
    private function dfa_contains_a(): string {
        return json_encode([
            'schema_version' => 1,
            'states'         => [['id' => 'q0', 'label' => 'q0'], ['id' => 'q1', 'label' => 'q1']],
            'alphabet'       => ['a', 'b'],
            'transitions'    => [
                ['from' => 'q0', 'symbol' => 'a', 'to' => 'q1'],
                ['from' => 'q0', 'symbol' => 'b', 'to' => 'q0'],
                ['from' => 'q1', 'symbol' => 'a', 'to' => 'q1'],
                ['from' => 'q1', 'symbol' => 'b', 'to' => 'q1'],
            ],
            'start'  => 'q0',
            'finals' => ['q1'],
        ]);
    }

    /**
     * Config whose test words exactly describe "contains at least one 'a'".
     *
     * @return array
     */
    private function config_contains_a(): array {
        return [
            'alphabet'   => ['a', 'b'],
            'test_words' => [
                ['word' => 'a',  'accept' => true],
                ['word' => 'aa', 'accept' => true],
                ['word' => 'ba', 'accept' => true],
                ['word' => 'b',  'accept' => false],
                ['word' => '',   'accept' => false],
                ['word' => 'bb', 'accept' => false],
            ],
        ];
    }

    public function test_correct_automaton_scores_full(): void {
        $result = (new afd_grader())->grade($this->config_contains_a(), $this->dfa_contains_a());

        $this->assertTrue($result['graded']);
        $this->assertFalse($result['invalid']);
        $this->assertSame(6, $result['words_total']);
        $this->assertSame(6, $result['words_correct']);
        // Exact integer division yields int(1) in PHP; compare numerically.
        $this->assertEqualsWithDelta(1.0, $result['fraction'], 0.0001);
        $this->assertTrue($result['passed']);
        $this->assertCount(6, $result['results']);
    }

    public function test_partial_automaton_scores_fraction(): void {
        // Config asks for "contains at least one 'b'": the contains-a DFA gets some wrong.
        $config = [
            'alphabet'   => ['a', 'b'],
            'test_words' => [
                ['word' => 'b',  'accept' => true],   // contains-a DFA rejects → wrong.
                ['word' => 'a',  'accept' => false],  // contains-a DFA accepts → wrong.
                ['word' => 'ab', 'accept' => true],   // contains-a DFA accepts → correct.
                ['word' => '',   'accept' => false],  // contains-a DFA rejects → correct.
            ],
        ];
        $result = (new afd_grader())->grade($config, $this->dfa_contains_a());

        $this->assertFalse($result['invalid']);
        $this->assertSame(4, $result['words_total']);
        $this->assertSame(2, $result['words_correct']);
        $this->assertEqualsWithDelta(0.5, $result['fraction'], 0.0001);
        $this->assertFalse($result['passed']);
    }

    public function test_empty_word_is_handled(): void {
        // DFA accepting only the empty string: q0 is start AND final, no transitions out.
        $snapshot = json_encode([
            'schema_version' => 1,
            'states'         => [['id' => 'q0', 'label' => 'q0']],
            'alphabet'       => ['a'],
            'transitions'    => [],
            'start'          => 'q0',
            'finals'         => ['q0'],
        ]);
        $config = [
            'alphabet'   => ['a'],
            'test_words' => [
                ['word' => '',  'accept' => true],
                ['word' => 'a', 'accept' => false],
            ],
        ];
        $result = (new afd_grader())->grade($config, $snapshot);

        $this->assertSame(2, $result['words_correct']);
        // Exact integer division yields int(1) in PHP; compare numerically.
        $this->assertEqualsWithDelta(1.0, $result['fraction'], 0.0001);
    }

    public function test_no_snapshot_is_invalid(): void {
        $result = (new afd_grader())->grade($this->config_contains_a(), null);

        $this->assertTrue($result['invalid']);
        $this->assertSame('no_automaton', $result['message']);
        $this->assertSame(0.0, $result['fraction']);
        $this->assertFalse($result['passed']);
    }

    public function test_automaton_without_start_is_invalid(): void {
        $snapshot = json_encode([
            'schema_version' => 1,
            'states'         => [['id' => 'q0', 'label' => 'q0']],
            'alphabet'       => ['a'],
            'transitions'    => [],
            'start'          => null,
            'finals'         => [],
        ]);
        $result = (new afd_grader())->grade($this->config_contains_a(), $snapshot);

        $this->assertTrue($result['invalid']);
        $this->assertSame('no_start', $result['message']);
        $this->assertSame(0.0, $result['fraction']);
    }
}
