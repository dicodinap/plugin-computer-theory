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
 * Sad-path tests for parser, lexer and validator: malformed inputs.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * Sad-path coverage for invalid formula strings and out-of-bounds inputs.
 *
 * Each test focuses on one error surface from the grammar / validator.
 *
 * @coversNothing
 */
final class parser_sad_test extends \basic_testcase {
    /** @var parser */
    private parser $parser;

    /** @var validator */
    private validator $validator;

    protected function setUp(): void {
        parent::setUp();
        $this->parser    = new parser();
        $this->validator = new validator();
    }

    // -------------------------------------------------------------------------
    // Lexer error surfaces
    // -------------------------------------------------------------------------

    public function test_empty_string_throws_parse_exception(): void {
        $this->expectException(parse_exception::class);
        $this->expectExceptionMessage('Fórmula vacía');
        $this->parser->parse('');
    }

    public function test_whitespace_only_is_empty(): void {
        $this->expectException(parse_exception::class);
        $this->expectExceptionMessage('Fórmula vacía');
        $this->parser->parse("  \t  \n  ");
    }

    public function test_lowercase_variable_is_lex_error(): void {
        $this->expectException(lex_exception::class);
        $this->expectExceptionMessage('Símbolo desconocido "a"');
        $this->parser->parse('a');
    }

    public function test_digit_is_lex_error(): void {
        $this->expectException(lex_exception::class);
        $this->expectExceptionMessage('Símbolo desconocido "1"');
        $this->parser->parse('1');
    }

    public function test_unicode_garbage_is_lex_error(): void {
        $this->expectException(lex_exception::class);
        $this->expectExceptionMessage('Símbolo desconocido "α"');
        $this->parser->parse('αβ');
    }

    public function test_emoji_is_lex_error(): void {
        $this->expectException(lex_exception::class);
        // Emoji must be reported at position 1 — verifies multibyte-safe position counting.
        $this->expectExceptionMessage('en posición 1');
        $this->parser->parse('😀');
    }

    public function test_lex_error_position_after_valid_chars(): void {
        // Make sure positions are 1-indexed code points, not bytes.
        try {
            $this->parser->parse('A∧b');
            $this->fail('expected lex_exception');
        } catch (lex_exception $e) {
            $this->assertSame(3, $e->get_position());
            $this->assertStringContainsString('"b"', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Parser error surfaces — unbalanced parens, dangling operators
    // -------------------------------------------------------------------------

    public function test_unclosed_paren_throws_parse_exception(): void {
        $this->expectException(parse_exception::class);
        $this->expectExceptionMessage('Paréntesis sin cerrar en posición 1');
        $this->parser->parse('(A');
    }

    public function test_extra_close_paren(): void {
        $this->expectException(parse_exception::class);
        $this->parser->parse('A)');
    }

    public function test_empty_parens(): void {
        $this->expectException(parse_exception::class);
        $this->parser->parse('()');
    }

    public function test_dangling_binary_operator(): void {
        $this->expectException(parse_exception::class);
        // The lexer normalizes '&' → '∧'. After consuming the AND, primary() expects an operand.
        $this->parser->parse('A &');
    }

    public function test_dangling_implication(): void {
        $this->expectException(parse_exception::class);
        $this->expectExceptionMessage('Operador "→" sin operando derecho');
        $this->parser->parse('A ->');
    }

    public function test_leading_binary_operator(): void {
        $this->expectException(parse_exception::class);
        $this->parser->parse('& A');
    }

    public function test_double_binary_operator(): void {
        $this->expectException(parse_exception::class);
        $this->parser->parse('A ∧ ∧ B');
    }

    public function test_two_adjacent_vars_is_parse_error(): void {
        $this->expectException(parse_exception::class);
        // 'A B' normalises to 'AB'; after parsing var A, the next token 'B' is not EOF.
        $this->parser->parse('A B');
    }

    public function test_unary_not_without_operand(): void {
        $this->expectException(parse_exception::class);
        $this->parser->parse('¬');
    }

    // -------------------------------------------------------------------------
    // Validator bounds
    // -------------------------------------------------------------------------

    public function test_validator_rejects_more_than_max_variables(): void {
        // MAX_VARIABLES = 5. The alphabet only contains 26 letters, so the spec's notion of
        // ">26 vars" is unreachable at the parser level; the meaningful bound is
        // MAX_VARIABLES. Build a 6-variable formula and check the validator rejects it.
        $r = $this->validator->validate_formula('A ∧ B ∧ C ∧ D ∧ E ∧ F');
        $this->assertFalse($r->ok);
        $errors = $r->errors;
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('6 variables distintas', $errors[0]);
        $this->assertStringContainsString('máximo permitido es 5', $errors[0]);
    }

    public function test_validator_accepts_exactly_max_variables(): void {
        $r = $this->validator->validate_formula('A ∧ B ∧ C ∧ D ∧ E');
        $this->assertTrue($r->ok, implode('; ', $r->errors));
    }

    public function test_validator_rejects_overlong_formula(): void {
        // Build a 129-char normalised formula via long disjunction chain over A,B.
        // "A∨B" = 3 chars. Need >128 chars after normalisation; "(A∨B)" repeated stays parseable.
        $piece = '(A∨B)∧';
        $raw = str_repeat($piece, 22) . '(A∨B)'; // 22*6 + 5 = 137 chars.
        $r = $this->validator->validate_formula($raw);
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('excede el máximo de 128', $r->errors[0]);
    }

    public function test_validator_rejects_lex_error_with_spanish_prefix(): void {
        $r = $this->validator->validate_formula('a');
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Error léxico', $r->errors[0]);
    }

    public function test_validator_rejects_parse_error_with_spanish_prefix(): void {
        $r = $this->validator->validate_formula('(A');
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Error sintáctico', $r->errors[0]);
    }

    public function test_validator_rejects_empty_formula(): void {
        $r = $this->validator->validate_formula('');
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Error sintáctico', $r->errors[0]);
        $this->assertStringContainsString('Fórmula vacía', $r->errors[0]);
    }
}
