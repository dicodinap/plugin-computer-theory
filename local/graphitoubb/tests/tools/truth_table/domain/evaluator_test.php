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
 * Unit tests for the formula evaluator.
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
 * 8 tests — one per AST node kind — each verifying truth-table-correct evaluation.
 *
 * @coversNothing
 */
final class evaluator_test extends \basic_testcase {
    /** @var evaluator */
    private evaluator $evaluator;

    protected function setUp(): void {
        parent::setUp();
        $this->evaluator = new evaluator();
    }

    // -------------------------------------------------------------------------
    // Test 1 — Variable lookup.
    // -------------------------------------------------------------------------
    public function test_var_lookup(): void {
        // Arrange.
        $ast        = new var_node('A');
        $assignment = ['A' => true];

        // Act.
        $result = $this->evaluator->evaluate($ast, $assignment);

        // Assert.
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Constant true.
    // -------------------------------------------------------------------------
    public function test_const_true(): void {
        // Arrange.
        $ast = new const_node(true);

        // Act.
        $result = $this->evaluator->evaluate($ast, []);

        // Assert.
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Constant false.
    // -------------------------------------------------------------------------
    public function test_const_false(): void {
        // Arrange.
        $ast = new const_node(false);

        // Act.
        $result = $this->evaluator->evaluate($ast, []);

        // Assert.
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Test 4 — Negation.
    // -------------------------------------------------------------------------
    public function test_not(): void {
        // Arrange.
        $ast        = new not_node(new var_node('A'));
        $assignment = ['A' => false];

        // Act.
        $result = $this->evaluator->evaluate($ast, $assignment);

        // Assert: ¬false = true.
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Test 5 — Conjunction (AND).
    // -------------------------------------------------------------------------
    public function test_and(): void {
        // Arrange — T ∧ F = F.
        $ast        = new and_node(new var_node('A'), new var_node('B'));
        $assignment = ['A' => true, 'B' => false];

        // Act.
        $result = $this->evaluator->evaluate($ast, $assignment);

        // Assert.
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Test 6 — Disjunction (OR).
    // -------------------------------------------------------------------------
    public function test_or(): void {
        // Arrange — F ∨ T = T.
        $ast        = new or_node(new var_node('A'), new var_node('B'));
        $assignment = ['A' => false, 'B' => true];

        // Act.
        $result = $this->evaluator->evaluate($ast, $assignment);

        // Assert.
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Test 7 — Exclusive or (XOR).
    // -------------------------------------------------------------------------
    public function test_xor(): void {
        // Arrange — T ⊕ T = F.
        $ast        = new xor_node(new var_node('A'), new var_node('B'));
        $assignment = ['A' => true, 'B' => true];

        // Act.
        $result = $this->evaluator->evaluate($ast, $assignment);

        // Assert.
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Test 8 — Implication and biconditional together.
    // -------------------------------------------------------------------------
    public function test_impl_and_iff(): void {
        // Arrange — T → F = F; T ↔ F = F.
        $impl_ast   = new impl_node(new var_node('A'), new var_node('B'));
        $iff_ast    = new iff_node(new var_node('A'), new var_node('B'));
        $assignment = ['A' => true, 'B' => false];

        // Act.
        $impl_result = $this->evaluator->evaluate($impl_ast, $assignment);
        $iff_result  = $this->evaluator->evaluate($iff_ast, $assignment);

        // Assert: T→F = F; T↔F = F.
        $this->assertFalse($impl_result, 'T → F should be false');
        $this->assertFalse($iff_result, 'T ↔ F should be false');
    }
}
