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
 * Unit tests for the propositional formula recursive-descent parser.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\tools\truth_table\domain\ast\and_node;
use local_graphitoubb\tools\truth_table\domain\ast\const_node;
use local_graphitoubb\tools\truth_table\domain\ast\iff_node;
use local_graphitoubb\tools\truth_table\domain\ast\impl_node;
use local_graphitoubb\tools\truth_table\domain\ast\not_node;
use local_graphitoubb\tools\truth_table\domain\ast\or_node;
use local_graphitoubb\tools\truth_table\domain\ast\var_node;
use local_graphitoubb\tools\truth_table\domain\ast\xor_node;

/**
 * 14 tests covering the full parser contract.
 *
 * @coversNothing
 */
final class parser_test extends \basic_testcase {
    /** @var parser */
    private parser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->parser = new parser();
    }

    // -------------------------------------------------------------------------
    // Test 1 — Parses a single variable.
    // -------------------------------------------------------------------------
    public function test_parses_single_var(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('A');

        // Assert.
        $this->assertInstanceOf(var_node::class, $ast);
        $this->assertSame('A', $ast->name);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Parses A ∧ B.
    // -------------------------------------------------------------------------
    public function test_parses_conjunction(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('A ∧ B');

        // Assert.
        $this->assertInstanceOf(and_node::class, $ast);
        $this->assertInstanceOf(var_node::class, $ast->left);
        $this->assertInstanceOf(var_node::class, $ast->right);
        $this->assertSame('A', $ast->left->name);
        $this->assertSame('B', $ast->right->name);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Precedence: A ∨ B ∧ C parses as A ∨ (B ∧ C).
    // -------------------------------------------------------------------------
    public function test_precedence_and_over_or(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('A ∨ B ∧ C');

        // Assert: root is or; right child is and.
        $this->assertInstanceOf(or_node::class, $ast);
        $this->assertInstanceOf(var_node::class, $ast->left);
        $this->assertSame('A', $ast->left->name);
        $this->assertInstanceOf(and_node::class, $ast->right);
    }

    // -------------------------------------------------------------------------
    // Test 4 — Right-associativity of impl: A → B → C parses as A → (B → C).
    // -------------------------------------------------------------------------
    public function test_right_assoc_implication(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('A → B → C');

        // Assert: root is impl; right child is also impl.
        $this->assertInstanceOf(impl_node::class, $ast);
        $this->assertInstanceOf(var_node::class, $ast->left);
        $this->assertSame('A', $ast->left->name);
        $this->assertInstanceOf(impl_node::class, $ast->right);
    }

    // -------------------------------------------------------------------------
    // Test 5 — Parentheses override precedence.
    // -------------------------------------------------------------------------
    public function test_parentheses_override_precedence(): void {
        // Arrange & Act — (A ∨ B) ∧ C should make or the left child of and.
        $ast = $this->parser->parse('(A ∨ B) ∧ C');

        // Assert: root is and; left child is or.
        $this->assertInstanceOf(and_node::class, $ast);
        $this->assertInstanceOf(or_node::class, $ast->left);
        $this->assertInstanceOf(var_node::class, $ast->right);
        $this->assertSame('C', $ast->right->name);
    }

    // -------------------------------------------------------------------------
    // Test 6 — Double negation.
    // -------------------------------------------------------------------------
    public function test_double_negation(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('¬¬A');

        // Assert: root is not; child is not; grandchild is var.
        $this->assertInstanceOf(not_node::class, $ast);
        $this->assertInstanceOf(not_node::class, $ast->operand);
        $this->assertInstanceOf(var_node::class, $ast->operand->operand);
    }

    // -------------------------------------------------------------------------
    // Test 7 — All 6 operators parsed at root level.
    // -------------------------------------------------------------------------
    public function test_all_binary_operators(): void {
        $cases = [
            ['A ∧ B', and_node::class],
            ['A ∨ B', or_node::class],
            ['A ⊕ B', xor_node::class],
            ['A → B', impl_node::class],
            ['A ↔ B', iff_node::class],
        ];

        foreach ($cases as [$raw, $class]) {
            $ast = $this->parser->parse($raw);
            $this->assertInstanceOf($class, $ast, 'Failed for: ' . $raw);
        }
    }

    // -------------------------------------------------------------------------
    // Test 8 — Negation operator.
    // -------------------------------------------------------------------------
    public function test_negation_operator(): void {
        // Arrange & Act.
        $ast = $this->parser->parse('¬A');

        // Assert.
        $this->assertInstanceOf(not_node::class, $ast);
        $this->assertInstanceOf(var_node::class, $ast->operand);
    }

    // -------------------------------------------------------------------------
    // Test 9 — ASCII synonyms produce the same AST as Unicode.
    // -------------------------------------------------------------------------
    public function test_ascii_synonyms_produce_same_ast(): void {
        $pairs = [
            ['A & B', 'A ∧ B'],
            ['A | B', 'A ∨ B'],
            ['~A', '¬A'],
            ['A -> B', 'A → B'],
            ['A <-> B', 'A ↔ B'],
            ['A /\\ B', 'A ∧ B'],
            ['A \\/ B', 'A ∨ B'],
        ];

        foreach ($pairs as [$ascii, $unicode]) {
            $ascii_ast   = $this->parser->parse($ascii);
            $unicode_ast = $this->parser->parse($unicode);
            $this->assertSame(
                $unicode_ast->canonical(),
                $ascii_ast->canonical(),
                'Canonical mismatch for: ' . $ascii . ' vs ' . $unicode
            );
        }
    }

    // -------------------------------------------------------------------------
    // Test 10 — Unclosed parenthesis throws parse_exception with position.
    // -------------------------------------------------------------------------
    public function test_throws_on_unclosed_paren(): void {
        try {
            $this->parser->parse('(A ∧ B');
            $this->fail('Expected parse_exception for unclosed paren.');
        } catch (parse_exception $e) {
            $this->assertStringContainsString('Paréntesis sin cerrar', $e->getMessage());
            $this->assertGreaterThan(0, $e->get_position());
        }
    }

    // -------------------------------------------------------------------------
    // Test 11 — Unknown symbol throws lex_exception with position.
    // -------------------------------------------------------------------------
    public function test_throws_on_unknown_symbol(): void {
        try {
            $this->parser->parse('A ? B');
            $this->fail('Expected lex_exception for unknown symbol.');
        } catch (lex_exception $e) {
            $this->assertStringContainsString('?', $e->getMessage());
            $this->assertGreaterThan(0, $e->get_position());
        }
    }

    // -------------------------------------------------------------------------
    // Test 12 — Missing right operand for → throws parse_exception.
    // -------------------------------------------------------------------------
    public function test_throws_on_missing_right_operand(): void {
        try {
            $this->parser->parse('A →');
            $this->fail('Expected parse_exception for missing right operand.');
        } catch (parse_exception $e) {
            $this->assertStringContainsString('→', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Test 13 — Empty formula throws parse_exception.
    // -------------------------------------------------------------------------
    public function test_throws_on_empty_formula(): void {
        try {
            $this->parser->parse('');
            $this->fail('Expected parse_exception for empty formula.');
        } catch (parse_exception $e) {
            $this->assertStringContainsString('vacía', $e->getMessage());
            $this->assertSame(1, $e->get_position());
        }
    }

    // -------------------------------------------------------------------------
    // Test 14 — Lowercase variable name throws lex_exception.
    // -------------------------------------------------------------------------
    public function test_throws_on_lowercase_variable(): void {
        try {
            $this->parser->parse('a');
            $this->fail('Expected lex_exception for lowercase variable.');
        } catch (lex_exception $e) {
            $this->assertStringContainsString('a', $e->getMessage());
            $this->assertSame(1, $e->get_position());
        }
    }
}
