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
 * Recursive-descent parser for propositional formulae.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\tools\truth_table\domain\ast\and_node;
use local_graphitoubb\tools\truth_table\domain\ast\const_node;
use local_graphitoubb\tools\truth_table\domain\ast\formula_ast;
use local_graphitoubb\tools\truth_table\domain\ast\iff_node;
use local_graphitoubb\tools\truth_table\domain\ast\impl_node;
use local_graphitoubb\tools\truth_table\domain\ast\not_node;
use local_graphitoubb\tools\truth_table\domain\ast\or_node;
use local_graphitoubb\tools\truth_table\domain\ast\var_node;
use local_graphitoubb\tools\truth_table\domain\ast\xor_node;

/**
 * Handwritten recursive-descent parser for propositional logic formulae.
 *
 * Grammar (lowest precedence first, matching spec §2 BNF):
 *
 *   formula       ::= biconditional
 *   biconditional ::= implication ( '↔' implication )*
 *   implication   ::= disjunction ( '→' disjunction )*
 *   disjunction   ::= conjunction ( ( '∨' | '⊕' ) conjunction )*
 *   conjunction   ::= negation ( '∧' negation )*
 *   negation      ::= '¬' negation | primary
 *   primary       ::= '(' formula ')' | variable | constant
 *
 * Decision: implication and biconditional use left-to-right iteration in the
 * loop but the spec says right-associative. To achieve right-associativity we
 * collect the operands and fold right. For biconditional the spec BNF uses a *
 * (repetition) which is technically left-assoc; right-assoc gives the more
 * mathematically conventional tree for nested implications.
 *
 * Decision: variables must be single uppercase letters A–Z. Lowercase letters
 * are rejected with 'Variable inválida "x" en posición X'.
 *
 * All error messages are in Spanish with 1-indexed position numbers.
 */
final class parser {
    /** @var array<int, array{type: string, value: string, pos: int}> Token stream. */
    private array $tokens = [];

    /** @var int Current position in the token stream. */
    private int $cursor = 0;

    /**
     * Parse a raw formula string and return the AST root.
     *
     * Applies lexer normalisation and tokenisation before parsing.
     *
     * @param  string $raw Raw formula string (may contain ASCII synonyms and whitespace).
     * @return formula_ast
     * @throws lex_exception   On unrecognised character.
     * @throws parse_exception On syntactic error (Spanish messages, 1-indexed positions).
     */
    public function parse(string $raw): formula_ast {
        $lexer = new lexer();

        $normalized   = $lexer->normalize($raw);
        if ($normalized === '') {
            throw new parse_exception('Fórmula vacía', 1);
        }

        $this->tokens = $lexer->tokenize($normalized);
        $this->cursor = 0;

        $ast = $this->parse_biconditional();

        // After parsing the full formula, expect EOF.
        $tok = $this->current();
        if ($tok['type'] !== 'eof') {
            throw new parse_exception(
                'Se esperaba fin de fórmula pero se encontró "' . $tok['value'] . '" en posición ' . $tok['pos'],
                $tok['pos']
            );
        }

        return $ast;
    }

    // -------------------------------------------------------------------------
    // Grammar rules
    // -------------------------------------------------------------------------

    /**
     * biconditional ::= implication ( '↔' implication )*
     *
     * Decision: right-associative — collected operands are folded from right.
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_biconditional(): formula_ast {
        $operands = [$this->parse_implication()];

        while ($this->current()['type'] === 'iff') {
            $this->advance();
            $operands[] = $this->parse_implication();
        }

        // Fold right: A ↔ B ↔ C → A ↔ (B ↔ C).
        $result = array_pop($operands);
        while (!empty($operands)) {
            $left   = array_pop($operands);
            $result = new iff_node($left, $result);
        }

        return $result;
    }

    /**
     * implication ::= disjunction ( '→' disjunction )*
     *
     * Decision: right-associative — collected operands are folded from right.
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_implication(): formula_ast {
        $operands = [$this->parse_disjunction()];

        while ($this->current()['type'] === 'impl') {
            $op  = $this->current();
            $this->advance();
            $tok = $this->current();
            if ($tok['type'] === 'eof' || $tok['type'] === 'rparen') {
                throw new parse_exception(
                    'Operador "→" sin operando derecho en posición ' . $op['pos'],
                    $op['pos']
                );
            }
            $operands[] = $this->parse_disjunction();
        }

        // Fold right: A → B → C → A → (B → C).
        $result = array_pop($operands);
        while (!empty($operands)) {
            $left   = array_pop($operands);
            $result = new impl_node($left, $result);
        }

        return $result;
    }

    /**
     * disjunction ::= conjunction ( ( '∨' | '⊕' ) conjunction )*
     *
     * Left-associative — fold left naturally via loop.
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_disjunction(): formula_ast {
        $left = $this->parse_conjunction();

        while (in_array($this->current()['type'], ['or', 'xor'], true)) {
            $type = $this->current()['type'];
            $this->advance();
            $right = $this->parse_conjunction();
            if ($type === 'xor') {
                $left = new xor_node($left, $right);
            } else {
                $left = new or_node($left, $right);
            }
        }

        return $left;
    }

    /**
     * conjunction ::= negation ( '∧' negation )*
     *
     * Left-associative — fold left naturally via loop.
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_conjunction(): formula_ast {
        $left = $this->parse_negation();

        while ($this->current()['type'] === 'and') {
            $this->advance();
            $right = $this->parse_negation();
            $left  = new and_node($left, $right);
        }

        return $left;
    }

    /**
     * negation ::= '¬' negation | primary
     *
     * Right-recursive by the recursive call.
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_negation(): formula_ast {
        if ($this->current()['type'] === 'not') {
            $this->advance();
            return new not_node($this->parse_negation());
        }

        return $this->parse_primary();
    }

    /**
     * primary ::= '(' formula ')' | variable | constant
     *
     * @return formula_ast
     * @throws parse_exception
     */
    private function parse_primary(): formula_ast {
        $tok = $this->current();

        if ($tok['type'] === 'lparen') {
            $this->advance(); // consume '('.
            $inner = $this->parse_biconditional();
            $close = $this->current();
            if ($close['type'] !== 'rparen') {
                // Use the lparen position for "unclosed" messages.
                throw new parse_exception(
                    'Paréntesis sin cerrar en posición ' . $tok['pos'],
                    $tok['pos']
                );
            }
            $this->advance(); // consume ')'.
            return $inner;
        }

        if ($tok['type'] === 'rparen') {
            throw new parse_exception(
                'Se esperaba ")" pero se encontró "' . $tok['value'] . '" en posición ' . $tok['pos'],
                $tok['pos']
            );
        }

        if ($tok['type'] === 'var') {
            $this->advance();
            return new var_node($tok['value']);
        }

        if ($tok['type'] === 'const_true') {
            $this->advance();
            return new const_node(true);
        }

        if ($tok['type'] === 'const_false') {
            $this->advance();
            return new const_node(false);
        }

        // Any other token at the primary level is unexpected.
        if ($tok['type'] === 'eof') {
            // Distinguish missing-operand from truly empty (caught earlier).
            throw new parse_exception(
                'Operando esperado pero se encontró fin de fórmula en posición ' . $tok['pos'],
                $tok['pos']
            );
        }

        throw new parse_exception(
            'Símbolo inesperado "' . $tok['value'] . '" en posición ' . $tok['pos'],
            $tok['pos']
        );
    }

    // -------------------------------------------------------------------------
    // Token stream helpers
    // -------------------------------------------------------------------------

    /**
     * Return the token at the current cursor position without consuming it.
     *
     * @return array{type: string, value: string, pos: int}
     */
    private function current(): array {
        return $this->tokens[$this->cursor];
    }

    /**
     * Advance the cursor by one.
     *
     * @return void
     */
    private function advance(): void {
        $this->cursor++;
    }
}
