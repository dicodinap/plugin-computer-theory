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
 * Truth table builder — generates the complete truth table for a formula AST.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\tools\truth_table\domain\ast\formula_ast;
use InvalidArgumentException;

/**
 * Builds a truth table from a formula AST.
 *
 * Row order: canonical binary — variables sorted alphabetically, A as MSB.
 * Row 0 corresponds to all variables false, row 1 increments the LSB, etc.
 * This produces the standard textbook ordering (A=F B=F, A=F B=T, A=T B=F, A=T B=T).
 *
 * Column layout:
 *   [variable columns (sorted A–Z)] [subformula columns (post-order, non-atomic)] ['final']
 *
 * The 'final' column always uses the canonical string of the root formula.
 */
final class truth_table_builder {
    /** @var canonicalizer */
    private canonicalizer $canonicalizer;

    /** @var evaluator */
    private evaluator $evaluator;

    /** @var parser */
    private parser $parser;

    /**
     * Build the builder with its collaborators.
     */
    public function __construct() {
        $this->canonicalizer = new canonicalizer();
        $this->evaluator     = new evaluator();
        $this->parser        = new parser();
    }

    /**
     * Build the full truth table for the given formula AST.
     *
     * Options:
     *   'intermediate' — 'auto' (default): derive non-atomic subformulas automatically (max 8).
     *                    'none': no intermediate columns.
     *                    'manual': use the provided manual_subformulas list.
     *   'manual_subformulas' — string[] of raw formula strings when intermediate = 'manual'.
     *
     * Return shape:
     * ```
     * [
     *   'variables' => ['A', 'B', ...],         // sorted alphabetically
     *   'columns'   => ['A', 'B', ..., 'A∧B', 'final'], // column header labels
     *   'rows'      => [
     *     ['vars' => ['A' => false, 'B' => false], 'values' => [false, false, false]],
     *     ...
     *   ]
     * ]
     * ```
     *
     * @param  formula_ast $ast  Root AST node.
     * @param  array       $opts Options: intermediate, manual_subformulas.
     * @return array
     * @throws InvalidArgumentException If a manual subformula is not a subformula of the root.
     */
    public function build(formula_ast $ast, array $opts = []): array {
        $intermediate = $opts['intermediate'] ?? 'auto';

        // Collect and sort variables.
        $variables = array_values(array_unique($ast->variables()));
        sort($variables);

        $n = count($variables);

        // Determine intermediate subformula ASTs.
        $sub_asts = $this->resolve_intermediate_asts($ast, $intermediate, $opts['manual_subformulas'] ?? []);

        // Build column headers.
        $columns = $variables; // variable headers.
        foreach ($sub_asts as $sub_ast) {
            $columns[] = $sub_ast->canonical();
        }
        $final_header = $ast->canonical();
        $columns[]    = 'final';

        // Generate rows using bit-mask. Row i: bit (n-1-j) of i = value of variables[j].
        // This places variable[0] (alphabetically first) as the MSB, giving standard ordering.
        $rows = [];
        $row_count = 1 << $n; // 2^n.
        for ($i = 0; $i < $row_count; $i++) {
            $assignment = [];
            for ($j = 0; $j < $n; $j++) {
                // MSB is variable[0]: bit position (n-1-j).
                $assignment[$variables[$j]] = (bool)(($i >> ($n - 1 - $j)) & 1);
            }

            $values = [];
            // Variable values first.
            foreach ($variables as $v) {
                $values[] = $assignment[$v];
            }
            // Intermediate subformula values.
            foreach ($sub_asts as $sub_ast) {
                $values[] = $this->evaluator->evaluate($sub_ast, $assignment);
            }
            // Final formula value.
            $values[] = $this->evaluator->evaluate($ast, $assignment);

            $rows[] = [
                'vars'   => $assignment,
                'values' => $values,
            ];
        }

        return [
            'variables' => $variables,
            'columns'   => $columns,
            'rows'      => $rows,
        ];
    }

    /**
     * Resolve the list of intermediate subformula ASTs based on the 'intermediate' option.
     *
     * @param  formula_ast $root               Root AST.
     * @param  string      $mode               'auto', 'none', or 'manual'.
     * @param  string[]    $manual_subformulas  Raw strings for manual mode.
     * @return formula_ast[]
     * @throws InvalidArgumentException         On manual subformula not found in root.
     */
    private function resolve_intermediate_asts(
        formula_ast $root,
        string $mode,
        array $manual_subformulas
    ): array {
        if ($mode === 'none') {
            return [];
        }

        if ($mode === 'auto') {
            // Get non-atomic subformula canonical strings from the root (max 8, excluding root itself).
            $sub_strings = $this->canonicalizer->subformulas($root, 8);
            // Re-parse each to get an evaluable AST.
            // Decision: re-parse from canonical string to get the sub-AST because PHP does not have
            // pointer equality we can use to extract sub-nodes directly from the tree without
            // additional complexity. The canonical string round-trips losslessly.
            $sub_asts = [];
            foreach ($sub_strings as $s) {
                $sub_asts[] = $this->parser->parse($s);
            }
            return $sub_asts;
        }

        if ($mode === 'manual') {
            // Build the set of canonical subformula strings in the root.
            $root_subs   = $this->canonicalizer->subformulas($root, 256); // large cap to get all.
            $root_subs[] = $root->canonical(); // include root itself.
            $sub_set     = array_flip($root_subs);

            $sub_asts = [];
            foreach ($manual_subformulas as $raw) {
                $sub_ast = $this->parser->parse($raw);
                $canon   = $sub_ast->canonical();
                if (!isset($sub_set[$canon])) {
                    throw new InvalidArgumentException(
                        'La subfórmula "' . $canon . '" no es una subfórmula de la fórmula raíz.'
                    );
                }
                $sub_asts[] = $sub_ast;
            }
            return $sub_asts;
        }

        // Unknown mode — treat as 'none'.
        return [];
    }
}
