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
 * Evaluator — evaluates a formula AST under a variable assignment.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\tools\truth_table\domain\ast\formula_ast;

/**
 * Thin facade that delegates evaluation to the AST nodes.
 *
 * Exists as a named service to make dependency injection explicit in tests
 * and to provide a single entry point for future caching or instrumentation.
 */
final class evaluator {
    /**
     * Evaluate a formula AST under the given variable assignment.
     *
     * The caller must supply a value for every variable that appears in the
     * formula. Absent keys result in a PHP notice, not a thrown exception,
     * because the builder is the sole caller and it controls the assignment.
     *
     * @param  formula_ast         $ast        AST root to evaluate.
     * @param  array<string, bool> $assignment Map of variable name → bool.
     * @return bool
     */
    public function evaluate(formula_ast $ast, array $assignment): bool {
        return $ast->evaluate($assignment);
    }
}
