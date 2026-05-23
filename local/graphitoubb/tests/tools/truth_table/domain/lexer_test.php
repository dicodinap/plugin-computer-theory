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
 * Unit tests for the propositional formula lexer.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * @coversNothing
 */
final class lexer_test extends \basic_testcase {
    /** @var lexer */
    private lexer $lexer;

    protected function setUp(): void {
        parent::setUp();
        $this->lexer = new lexer();
    }

    // -------------------------------------------------------------------------
    // Test 1 — ASCII synonyms are normalised to Unicode operators.
    // -------------------------------------------------------------------------

    /**
     * normalize() should replace all ASCII synonyms with their Unicode equivalents.
     */
    public function test_normalize_replaces_ascii_synonyms(): void {
        // Arrange.
        $raw = 'A & B | C ~ D -> E <-> F';

        // Act.
        $result = $this->lexer->normalize($raw);

        // Assert.
        $this->assertStringContainsString('∧', $result, 'Expected ∧ (and)');
        $this->assertStringContainsString('∨', $result, 'Expected ∨ (or)');
        $this->assertStringContainsString('¬', $result, 'Expected ¬ (not)');
        $this->assertStringContainsString('→', $result, 'Expected → (impl)');
        $this->assertStringContainsString('↔', $result, 'Expected ↔ (iff)');
        $this->assertStringNotContainsString(' ', $result, 'Whitespace should be stripped');
    }

    // -------------------------------------------------------------------------
    // Test 2 — Tokenises a simple variable.
    // -------------------------------------------------------------------------

    /**
     * tokenize() on a single variable should produce a var token and an eof token.
     */
    public function test_tokenize_single_variable(): void {
        // Arrange.
        $normalized = 'A';

        // Act.
        $tokens = $this->lexer->tokenize($normalized);

        // Assert: first token is var 'A' at position 1; second is eof.
        $this->assertCount(2, $tokens);
        $this->assertSame('var', $tokens[0]['type']);
        $this->assertSame('A', $tokens[0]['value']);
        $this->assertSame(1, $tokens[0]['pos']);
        $this->assertSame('eof', $tokens[1]['type']);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Unknown character throws lex_exception with 1-indexed position.
    // -------------------------------------------------------------------------

    /**
     * tokenize() should throw lex_exception carrying the 1-indexed position
     * when it encounters an unrecognised character.
     */
    public function test_tokenize_throws_on_unknown_char(): void {
        // Arrange — '?' is not a valid token.
        $normalized = 'A∧?';

        // Act & Assert.
        try {
            $this->lexer->tokenize($normalized);
            $this->fail('Expected lex_exception was not thrown.');
        } catch (lex_exception $e) {
            $this->assertSame(3, $e->get_position(), 'Position should be 1-indexed (3rd char)');
            $this->assertStringContainsString('?', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Test 4 — Multi-char synonym '<->' takes priority over '->'.
    // -------------------------------------------------------------------------

    /**
     * normalize() should replace '<->' as a unit before it could be split into
     * '->' + '>' by a shorter-first approach. Result must be '↔', not '→>'.
     */
    public function test_normalize_multicchar_synonym_priority(): void {
        // Arrange.
        $raw = 'A<->B';

        // Act.
        $result = $this->lexer->normalize($raw);

        // Assert: full biconditional replaced, no spurious '>'.
        $this->assertStringContainsString('↔', $result);
        $this->assertStringNotContainsString('>', $result, '<-> must be consumed as a unit');
        $this->assertStringNotContainsString('→', $result, 'No implication should appear');
    }
}
