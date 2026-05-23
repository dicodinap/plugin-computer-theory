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
 * Lexer — tokenises propositional formula strings.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * Converts a raw formula string into a flat token stream.
 *
 * Two-phase processing:
 *  1. normalize() — replaces ASCII synonyms with canonical Unicode operators and strips whitespace.
 *  2. tokenize() — scans the normalised string character-by-character and produces typed tokens.
 *
 * Decision: SYNONYMS uses PHP strtr() with array form, which processes longest keys first
 * automatically. This guarantees '<->' is replaced before '->' without custom ordering.
 */
final class lexer {
    /**
     * ASCII synonym map from input shorthand → canonical Unicode operator.
     *
     * Multi-character keys are resolved before single-character ones by strtr().
     *
     * @var array<string, string>
     */
    public const SYNONYMS = [
        '<->' => '↔',
        '->'  => '→',
        '/\\' => '∧',
        '&'   => '∧',
        '\\/' => '∨',
        '|'   => '∨',
        '~'   => '¬',
        '!'   => '¬',
    ];

    /**
     * Apply ASCII synonym substitutions and strip all whitespace from the input.
     *
     * strtr() with an array argument always processes the longest matching key first,
     * so '<->' is guaranteed to be substituted before '->'.
     *
     * @param  string $raw Raw formula from the user.
     * @return string Normalised formula ready for tokenisation.
     */
    public function normalize(string $raw): string {
        $replaced = strtr($raw, self::SYNONYMS);
        return preg_replace('/\s+/u', '', $replaced);
    }

    /**
     * Scan a normalised formula string and produce a typed token array.
     *
     * Token shape: ['type' => string, 'value' => string, 'pos' => int].
     * The final element is always an 'eof' token.
     * Positions are 1-indexed character positions in the normalised string.
     *
     * Token types: var, const_true, const_false, not, and, or, xor,
     *              impl, iff, lparen, rparen, eof.
     *
     * @param  string $normalized Already-normalised formula (no whitespace, Unicode operators).
     * @return array<int, array{type: string, value: string, pos: int}>
     * @throws lex_exception When an unrecognised character is encountered.
     */
    public function tokenize(string $normalized): array {
        // Split into individual Unicode code points so multibyte chars are each one position.
        $chars  = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];
        $pos    = 0; // 0-indexed offset into $chars; reported as pos+1 to callers.

        while ($pos < count($chars)) {
            $ch      = $chars[$pos];
            $charpos = $pos + 1; // 1-indexed.

            switch ($ch) {
                case 'A':
                case 'B':
                case 'C':
                case 'D':
                case 'E':
                case 'F':
                case 'G':
                case 'H':
                case 'I':
                case 'J':
                case 'K':
                case 'L':
                case 'M':
                case 'N':
                case 'O':
                case 'P':
                case 'Q':
                case 'R':
                case 'S':
                case 'T':
                case 'U':
                case 'V':
                case 'W':
                case 'X':
                case 'Y':
                case 'Z':
                    $tokens[] = ['type' => 'var', 'value' => $ch, 'pos' => $charpos];
                    break;

                case '⊤':
                    $tokens[] = ['type' => 'const_true', 'value' => '⊤', 'pos' => $charpos];
                    break;

                case '⊥':
                    $tokens[] = ['type' => 'const_false', 'value' => '⊥', 'pos' => $charpos];
                    break;

                case '¬':
                    $tokens[] = ['type' => 'not', 'value' => '¬', 'pos' => $charpos];
                    break;

                case '∧':
                    $tokens[] = ['type' => 'and', 'value' => '∧', 'pos' => $charpos];
                    break;

                case '∨':
                    $tokens[] = ['type' => 'or', 'value' => '∨', 'pos' => $charpos];
                    break;

                case '⊕':
                    $tokens[] = ['type' => 'xor', 'value' => '⊕', 'pos' => $charpos];
                    break;

                case '→':
                    $tokens[] = ['type' => 'impl', 'value' => '→', 'pos' => $charpos];
                    break;

                case '↔':
                    $tokens[] = ['type' => 'iff', 'value' => '↔', 'pos' => $charpos];
                    break;

                case '(':
                    $tokens[] = ['type' => 'lparen', 'value' => '(', 'pos' => $charpos];
                    break;

                case ')':
                    $tokens[] = ['type' => 'rparen', 'value' => ')', 'pos' => $charpos];
                    break;

                default:
                    throw new lex_exception(
                        'Símbolo desconocido "' . $ch . '" en posición ' . $charpos,
                        $charpos
                    );
            }

            $pos++;
        }

        $tokens[] = ['type' => 'eof', 'value' => '', 'pos' => count($chars) + 1];

        return $tokens;
    }
}
