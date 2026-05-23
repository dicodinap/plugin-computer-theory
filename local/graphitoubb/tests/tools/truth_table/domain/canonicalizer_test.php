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
 * Unit tests for the canonicalizer.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * 3 tests: paren wrapping, atom exclusion, deduplication.
 *
 * @coversNothing
 */
final class canonicalizer_test extends \basic_testcase {
    /** @var canonicalizer */
    private canonicalizer $canonicalizer;

    /** @var parser */
    private parser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->canonicalizer = new canonicalizer();
        $this->parser        = new parser();
    }

    // -------------------------------------------------------------------------
    // Test 1 — canonical() wraps binary nodes in parentheses.
    // -------------------------------------------------------------------------
    public function test_canonical_adds_parens(): void {
        // Arrange.
        $ast = $this->parser->parse('A ∧ B');

        // Act.
        $canonical = $this->canonicalizer->canonical($ast);

        // Assert: must be fully parenthesised.
        $this->assertSame('(A ∧ B)', $canonical);
    }

    // -------------------------------------------------------------------------
    // Test 2 — subformulas() excludes atomic nodes.
    // -------------------------------------------------------------------------
    public function test_subformulas_excludes_atoms(): void {
        // Arrange — A ∧ B: subformulas should not include 'A' or 'B' (atoms).
        $ast = $this->parser->parse('A ∧ B');

        // Act.
        $subs = $this->canonicalizer->subformulas($ast);

        // Assert: no atom strings in result.
        $this->assertNotContains('A', $subs, 'Atom A must not appear in subformulas.');
        $this->assertNotContains('B', $subs, 'Atom B must not appear in subformulas.');
    }

    // -------------------------------------------------------------------------
    // Test 3 — subformulas() deduplicates repeated subformulae.
    // -------------------------------------------------------------------------
    public function test_subformulas_deduplicates(): void {
        // Arrange — (A ∧ B) ∨ (A ∧ B): (A ∧ B) appears twice, should appear once.
        $ast = $this->parser->parse('(A ∧ B) ∨ (A ∧ B)');

        // Act.
        $subs = $this->canonicalizer->subformulas($ast);

        // Assert: no duplicates.
        $this->assertSame(array_unique($subs), $subs, 'subformulas() must not contain duplicates.');
        // The single inner canonical is (A ∧ B).
        $this->assertContains('(A ∧ B)', $subs);
        $this->assertCount(1, $subs, 'Only one unique non-atomic non-root subformula expected.');
    }
}
