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
 * Unit tests for the AFD serializer.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\automaton;
use local_graphitoubb\tools\afd\domain\serializer;
use local_graphitoubb\tools\afd\domain\state;
use local_graphitoubb\tools\afd\domain\transition;

/**
 * Tests for serializer.
 *
 * @covers \local_graphitoubb\tools\afd\domain\serializer
 */
final class serializer_test extends \basic_testcase {
    /**
     * Build a two-state automaton for round-trip tests.
     *
     * @return automaton
     */
    private function make_automaton(): automaton {
        return new automaton(
            [new state('q0', 'Start'), new state('q1', 'Accept')],
            ['a', 'b'],
            [new transition('q0', 'a', 'q1'), new transition('q1', 'b', 'q1')],
            'q0',
            ['q1']
        );
    }

    /**
     * Build a serializer instance.
     *
     * @return serializer
     */
    private function make_serializer(): serializer {
        return new serializer();
    }

    // Serialize tests.

    public function test_serialize_returns_valid_json(): void {
        $json = $this->make_serializer()->serialize($this->make_automaton());
        $this->assertNotFalse(json_decode($json));
    }

    public function test_serialize_includes_schema_version(): void {
        $json = $this->make_serializer()->serialize($this->make_automaton());
        $data = json_decode($json, true);
        $this->assertArrayHasKey('schema_version', $data);
        $this->assertSame(1, $data['schema_version']);
    }

    public function test_serialize_includes_all_required_keys(): void {
        $json = $this->make_serializer()->serialize($this->make_automaton());
        $data = json_decode($json, true);
        foreach (['states', 'alphabet', 'transitions', 'start', 'finals'] as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: $key");
        }
    }

    public function test_serialize_states_contain_id_and_label(): void {
        $json = $this->make_serializer()->serialize($this->make_automaton());
        $data = json_decode($json, true);
        $this->assertSame('q0', $data['states'][0]['id']);
        $this->assertSame('Start', $data['states'][0]['label']);
    }

    public function test_serialize_transitions_contain_from_symbol_to(): void {
        $json = $this->make_serializer()->serialize($this->make_automaton());
        $data = json_decode($json, true);
        $t = $data['transitions'][0];
        $this->assertSame('q0', $t['from']);
        $this->assertSame('a', $t['symbol']);
        $this->assertSame('q1', $t['to']);
    }

    // Deserialize tests.

    public function test_round_trip_preserves_states(): void {
        $s   = $this->make_serializer();
        $a   = $this->make_automaton();
        $a2  = $s->deserialize($s->serialize($a));
        $ids = array_map(fn($st) => $st->get_id(), $a2->get_states());
        $this->assertSame(['q0', 'q1'], $ids);
    }

    public function test_round_trip_preserves_labels(): void {
        $s   = $this->make_serializer();
        $a   = $this->make_automaton();
        $a2  = $s->deserialize($s->serialize($a));
        $this->assertSame('Start', $a2->get_states()[0]->get_label());
        $this->assertSame('Accept', $a2->get_states()[1]->get_label());
    }

    public function test_round_trip_preserves_alphabet(): void {
        $s  = $this->make_serializer();
        $a  = $this->make_automaton();
        $a2 = $s->deserialize($s->serialize($a));
        $this->assertSame(['a', 'b'], $a2->get_alphabet());
    }

    public function test_round_trip_preserves_transitions(): void {
        $s  = $this->make_serializer();
        $a  = $this->make_automaton();
        $a2 = $s->deserialize($s->serialize($a));
        $ts = $a2->get_transitions();
        $this->assertCount(2, $ts);
        $this->assertSame('q0', $ts[0]->get_from());
        $this->assertSame('a', $ts[0]->get_symbol());
        $this->assertSame('q1', $ts[0]->get_to());
    }

    public function test_round_trip_preserves_start_and_finals(): void {
        $s  = $this->make_serializer();
        $a  = $this->make_automaton();
        $a2 = $s->deserialize($s->serialize($a));
        $this->assertSame('q0', $a2->get_start());
        $this->assertSame(['q1'], $a2->get_finals());
    }

    public function test_deserialize_invalid_json_throws(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->make_serializer()->deserialize('not-json');
    }

    public function test_deserialize_missing_states_key_throws(): void {
        $json = json_encode(['schema_version' => 1, 'alphabet' => [], 'transitions' => [], 'start' => 'q0', 'finals' => []]);
        $this->expectException(\InvalidArgumentException::class);
        $this->make_serializer()->deserialize($json);
    }

    public function test_deserialize_missing_start_key_throws(): void {
        $json = json_encode(['schema_version' => 1, 'states' => [], 'alphabet' => [], 'transitions' => [], 'finals' => []]);
        $this->expectException(\InvalidArgumentException::class);
        $this->make_serializer()->deserialize($json);
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(serializer::class);
        $this->assertTrue($r->isFinal());
    }
}
