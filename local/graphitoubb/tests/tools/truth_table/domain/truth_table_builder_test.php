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
 * Unit tests for the truth table builder.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * 5 tests covering row generation, ordering, subformula columns, and error paths.
 *
 * @coversNothing
 */
final class truth_table_builder_test extends \basic_testcase {
    /** @var truth_table_builder */
    private truth_table_builder $builder;

    /** @var parser */
    private parser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->builder = new truth_table_builder();
        $this->parser  = new parser();
    }

    // -------------------------------------------------------------------------
    // Test 1 — 1-variable formula yields exactly 2 rows.
    // -------------------------------------------------------------------------
    public function test_one_var_yields_two_rows(): void {
        // Arrange.
        $ast = $this->parser->parse('A');

        // Act.
        $table = $this->builder->build($ast, ['intermediate' => 'none']);

        // Assert.
        $this->assertCount(2, $table['rows'], 'Single variable must produce 2 rows.');
        $this->assertSame(['A'], $table['variables']);
    }

    // -------------------------------------------------------------------------
    // Test 2 — 3-variable formula yields 8 rows in canonical (binary) order.
    // -------------------------------------------------------------------------
    public function test_three_vars_yield_eight_rows_in_canonical_order(): void {
        // Arrange — A ∧ B ∧ C has 3 variables.
        $ast = $this->parser->parse('A ∧ B ∧ C');

        // Act.
        $table = $this->builder->build($ast, ['intermediate' => 'none']);

        // Assert row count.
        $this->assertCount(8, $table['rows'], '3 variables must produce 8 rows.');

        // Assert canonical order: row 0 → A=F,B=F,C=F; row 7 → A=T,B=T,C=T.
        $row0 = $table['rows'][0]['vars'];
        $row7 = $table['rows'][7]['vars'];

        $this->assertFalse($row0['A']);
        $this->assertFalse($row0['B']);
        $this->assertFalse($row0['C']);
        $this->assertTrue($row7['A']);
        $this->assertTrue($row7['B']);
        $this->assertTrue($row7['C']);

        // Row 1 should be A=F,B=F,C=T.
        $row1 = $table['rows'][1]['vars'];
        $this->assertFalse($row1['A']);
        $this->assertFalse($row1['B']);
        $this->assertTrue($row1['C']);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Subformula columns appear in post-order (inner before outer).
    // -------------------------------------------------------------------------
    public function test_subformula_columns_appear_in_postorder(): void {
        // Arrange — A ∧ B ∨ C gives subformulas: (A ∧ B), then (A ∧ B) ∨ C = root excluded.
        // The auto mode should expose the inner conjunction before the outer disjunction.
        $ast = $this->parser->parse('A ∧ B ∨ C');

        // Act.
        $table = $this->builder->build($ast, ['intermediate' => 'auto']);

        // Assert: variable columns first, then subformulas, then 'final'.
        $cols = $table['columns'];
        $this->assertContains('A', $cols);
        $this->assertContains('B', $cols);
        $this->assertContains('C', $cols);

        // '(A ∧ B)' should appear before the root canonical in column ordering.
        $inner_idx = array_search('(A ∧ B)', $cols, true);
        $final_idx = array_search('final', $cols, true);
        $this->assertNotFalse($inner_idx, 'Inner subformula (A ∧ B) should be a column.');
        $this->assertLessThan($final_idx, $inner_idx, 'Subformula column must precede final.');
    }

    // -------------------------------------------------------------------------
    // Test 4 — Manual subformula not in formula throws InvalidArgumentException.
    // -------------------------------------------------------------------------
    public function test_manual_subformula_not_in_formula_throws(): void {
        // Arrange.
        $ast = $this->parser->parse('A ∧ B');

        // Act & Assert.
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->build($ast, [
            'intermediate'       => 'manual',
            'manual_subformulas' => ['A ∨ B'], // not a subformula of A ∧ B.
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 5 — 'none' intermediate mode produces only variable and final columns.
    // -------------------------------------------------------------------------
    public function test_none_intermediate_yields_only_var_and_final_columns(): void {
        // Arrange.
        $ast = $this->parser->parse('A ∧ B');

        // Act.
        $table = $this->builder->build($ast, ['intermediate' => 'none']);

        // Assert: only A, B, final (3 columns total).
        $this->assertSame(['A', 'B', 'final'], $table['columns']);
    }
}
