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
 * Grader facade — dispatches to the correct strategy grader by problem type.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

use local_graphitoubb\tools\truth_table\domain\evaluator;
use local_graphitoubb\tools\truth_table\domain\parser;
use local_graphitoubb\tools\truth_table\domain\serializer;
use local_graphitoubb\tools\truth_table\domain\truth_table_builder;

/**
 * Top-level grader entry point for the truth_table tool.
 *
 * Usage:
 *   $result = grader::instance()->grade($problem, $submission);
 *
 * Or with explicit collaborators for testing:
 *   $result = (new grader($parser, $builder, $evaluator, $serializer))->grade(...);
 *
 * Dispatches on $problem['type']:
 *   'complete'    → complete_grader
 *   'equivalence' → equivalence_grader
 *   'classify'    → classify_grader
 *
 * Any thrown exception (parsing, building, invalid payload) is caught and
 * returned as a grading_result with error = true.
 */
final class grader {
    /**
     * Build the facade with domain collaborators.
     *
     * @param parser              $parser
     * @param truth_table_builder $builder
     * @param evaluator           $evaluator
     * @param serializer          $serializer
     */
    public function __construct(
        private readonly parser $parser,
        private readonly truth_table_builder $builder,
        private readonly evaluator $evaluator,
        private readonly serializer $serializer
    ) {
    }

    /**
     * Build a default grader instance with standard domain collaborators.
     *
     * Intended for production callers that do not need custom collaborators.
     * Not a true singleton — each call returns a new instance; callers may
     * cache the result themselves.
     *
     * @return self
     */
    public static function instance(): self {
        return new self(
            parser: new parser(),
            builder: new truth_table_builder(),
            evaluator: new evaluator(),
            serializer: new serializer()
        );
    }

    /**
     * Grade a student submission against the given problem.
     *
     * @param  array $problem        Decoded problem JSON array. Must contain 'type'.
     * @param  array $submission     Decoded submission JSON array.
     * @param  float $max_grade      Maximum numeric score (e.g. 10.0). Defaults to 1.0.
     * @param  float $pass_threshold Fraction in [0,1] required to pass. Defaults to 0.6.
     * @return grading_result
     */
    public function grade(
        array $problem,
        array $submission,
        float $max_grade = 1.0,
        float $pass_threshold = 0.6
    ): grading_result {
        // Compute the problem snapshot hash before any potential exception.
        try {
            $hash = $this->serializer->hash($problem);
        } catch (\Throwable $e) {
            $hash = '';
        }

        try {
            $type = $problem['type'] ?? '';

            return match ($type) {
                'complete'    => (new complete_grader($this->parser, $this->builder, $this->evaluator))
                                    ->grade($problem, $submission, $max_grade, $pass_threshold, $hash),
                'equivalence' => (new equivalence_grader($this->parser, $this->builder, $this->evaluator))
                                    ->grade($problem, $submission, $max_grade, $pass_threshold, $hash),
                'classify'    => (new classify_grader($this->parser, $this->builder, $this->evaluator))
                                    ->grade($problem, $submission, $max_grade, $pass_threshold, $hash),
                default       => grading_result::error(
                    'Tipo de problema desconocido: "' . $type . '".',
                    $hash
                ),
            };
        } catch (\Throwable $e) {
            return grading_result::error(
                'Error interno durante la corrección: ' . $e->getMessage(),
                $hash
            );
        }
    }
}
