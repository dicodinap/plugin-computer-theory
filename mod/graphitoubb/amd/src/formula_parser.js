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
 * Client-side formula parser — mirrors the PHP parser for live preview.
 *
 * Server is source of truth; this is used only for immediate feedback while typing.
 * Implements the same grammar as the PHP parser (BNF §2 of the spec):
 *
 *   formula       ::= biconditional
 *   biconditional ::= implication ( '↔' implication )*
 *   implication   ::= disjunction ( '→' disjunction )*
 *   disjunction   ::= conjunction ( ('∨' | '⊕') conjunction )*
 *   conjunction   ::= negation ( '∧' negation )*
 *   negation      ::= '¬' negation | primary
 *   primary       ::= '(' formula ')' | variable | constant
 *
 * @module     mod_graphitoubb/formula_parser
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Build a parser Error carrying a localisable string key + params so the
     * consumer (truth_table_editor) can render it via core/str. The English
     * `.message` is a usable fallback for non-UI callers (e.g. tests).
     *
     * @param {string} message English fallback message.
     * @param {string} key     Lang string key in mod_graphitoubb.
     * @param {object|number} param  {$a} substitution(s).
     * @return {Error}
     */
    var parseError = function(message, key, param) {
        var err = new Error(message);
        err.strKey = key;
        err.strParam = param;
        return err;
    };

    // -------------------------------------------------------------------------
    // ASCII → Unicode normalization map.
    // -------------------------------------------------------------------------
    var ASCII_MAP = [
        [/<->/g, '↔'],
        [/->/g,  '→'],
        [/\/\\/g, '∧'],
        [/\\\//g, '∨'],
        [/&/g,   '∧'],
        [/\|/g,  '∨'],
        [/~/g,   '¬'],
        [/!/g,   '¬'],
    ];

    /**
     * Normalize ASCII synonyms to Unicode operators.
     *
     * @param {string} s
     * @return {string}
     */
    var normalize = function(s) {
        ASCII_MAP.forEach(function(pair) {
            s = s.replace(pair[0], pair[1]);
        });
        return s;
    };

    // -------------------------------------------------------------------------
    // Lexer
    // -------------------------------------------------------------------------
    var TOKEN = {
        VAR:   'VAR',
        CONST: 'CONST',
        NOT:   'NOT',
        AND:   'AND',
        OR:    'OR',
        XOR:   'XOR',
        IMPL:  'IMPL',
        IFF:   'IFF',
        LPAREN: 'LPAREN',
        RPAREN: 'RPAREN',
        EOF:    'EOF',
    };

    /**
     * Tokenize a normalized formula string.
     *
     * @param {string} input  Already normalized with Unicode operators.
     * @return {Array<{type: string, val: string, pos: number}>}
     */
    var tokenize = function(input) {
        var tokens = [];
        var i = 0;
        while (i < input.length) {
            var ch = input[i];
            if (ch === ' ' || ch === '\t' || ch === '\n') {
                i++;
                continue;
            }
            var pos = i + 1; // 1-indexed for error messages.
            if (ch === '(') { tokens.push({type: TOKEN.LPAREN, val: '(', pos: pos}); i++; continue; }
            if (ch === ')') { tokens.push({type: TOKEN.RPAREN, val: ')', pos: pos}); i++; continue; }
            if (ch === '¬') { tokens.push({type: TOKEN.NOT,    val: '¬', pos: pos}); i++; continue; }
            if (ch === '∧') { tokens.push({type: TOKEN.AND,    val: '∧', pos: pos}); i++; continue; }
            if (ch === '∨') { tokens.push({type: TOKEN.OR,     val: '∨', pos: pos}); i++; continue; }
            if (ch === '⊕') { tokens.push({type: TOKEN.XOR,    val: '⊕', pos: pos}); i++; continue; }
            if (ch === '→') { tokens.push({type: TOKEN.IMPL,   val: '→', pos: pos}); i++; continue; }
            if (ch === '↔') { tokens.push({type: TOKEN.IFF,    val: '↔', pos: pos}); i++; continue; }
            if (ch === '⊤' || ch === '⊥') {
                tokens.push({type: TOKEN.CONST, val: ch, pos: pos}); i++; continue;
            }
            if (/[A-Z]/.test(ch)) {
                tokens.push({type: TOKEN.VAR, val: ch, pos: pos}); i++; continue;
            }
            throw parseError(
                'Unexpected character "' + ch + '" at position ' + pos + '.',
                'parse_unexpected_char', {ch: ch, pos: pos});
        }
        tokens.push({type: TOKEN.EOF, val: '', pos: input.length + 1});
        return tokens;
    };

    // -------------------------------------------------------------------------
    // Recursive descent parser → AST
    // -------------------------------------------------------------------------
    /**
     * Parse a normalized formula string into an AST.
     *
     * AST nodes: {kind, ...} where kind ∈ var|const|not|and|or|xor|impl|iff
     *
     * @param {string} input
     * @return {object}  Root AST node.
     * @throws {Error}   With position and Spanish message on syntax error.
     */
    var parse = function(input) {
        var tokens = tokenize(normalize(input));
        var idx = 0;

        var peek = function() { return tokens[idx]; };
        var consume = function() { return tokens[idx++]; };

        var expect = function(type) {
            var tok = peek();
            if (tok.type !== type) {
                throw parseError(
                    'Expected ' + type + ' at position ' + tok.pos + ', found "' + tok.val + '".',
                    'parse_expected_token', {type: type, pos: tok.pos, val: tok.val});
            }
            return consume();
        };

        var parseFormula    = function() { return parseBiconditional(); };
        var parseBiconditional = function() {
            var left = parseImplication();
            while (peek().type === TOKEN.IFF) {
                consume();
                var right = parseImplication();
                left = {kind: 'iff', left: left, right: right};
            }
            return left;
        };
        var parseImplication = function() {
            var left = parseDisjunction();
            while (peek().type === TOKEN.IMPL) {
                consume();
                var right = parseDisjunction();
                left = {kind: 'impl', left: left, right: right};
            }
            return left;
        };
        var parseDisjunction = function() {
            var left = parseConjunction();
            while (peek().type === TOKEN.OR || peek().type === TOKEN.XOR) {
                var op = consume().type === TOKEN.OR ? 'or' : 'xor';
                var right = parseConjunction();
                left = {kind: op, left: left, right: right};
            }
            return left;
        };
        var parseConjunction = function() {
            var left = parseNegation();
            while (peek().type === TOKEN.AND) {
                consume();
                var right = parseNegation();
                left = {kind: 'and', left: left, right: right};
            }
            return left;
        };
        var parseNegation = function() {
            if (peek().type === TOKEN.NOT) {
                consume();
                return {kind: 'not', operand: parseNegation()};
            }
            return parsePrimary();
        };
        var parsePrimary = function() {
            var tok = peek();
            if (tok.type === TOKEN.LPAREN) {
                consume();
                var inner = parseFormula();
                expect(TOKEN.RPAREN);
                return inner;
            }
            if (tok.type === TOKEN.VAR) {
                consume();
                return {kind: 'var', name: tok.val};
            }
            if (tok.type === TOKEN.CONST) {
                consume();
                return {kind: 'const', value: tok.val === '⊤'};
            }
            throw parseError(
                'Expected a variable, constant or "(" at position ' + tok.pos + ', found "' + tok.val + '".',
                'parse_expected_operand', {pos: tok.pos, val: tok.val});
        };

        var ast = parseFormula();
        if (peek().type !== TOKEN.EOF) {
            throw parseError(
                'Incomplete formula: unexpected character at position ' + peek().pos + '.',
                'parse_incomplete', peek().pos);
        }
        return ast;
    };

    // -------------------------------------------------------------------------
    // Canonical serialization (adds explicit parentheses)
    // -------------------------------------------------------------------------
    var PREC = {var: 0, 'const': 0, not: 1, and: 2, or: 3, xor: 3, impl: 4, iff: 5};
    var OP_SYM = {and: '∧', or: '∨', xor: '⊕', impl: '→', iff: '↔'};

    /**
     * Serialize an AST to a canonical formula string with explicit parentheses.
     *
     * @param {object} node
     * @return {string}
     */
    var canonical = function(node) {
        switch (node.kind) {
            case 'var':   return node.name;
            case 'const': return node.value ? '⊤' : '⊥';
            case 'not':   return '¬' + canonical(node.operand);
            case 'and': case 'or': case 'xor': case 'impl': case 'iff':
                return '(' + canonical(node.left) + ' ' + OP_SYM[node.kind] + ' ' + canonical(node.right) + ')';
            default:      return '?';
        }
    };

    return {
        normalize: normalize,
        parse:     parse,
        canonical: canonical,
    };
});
