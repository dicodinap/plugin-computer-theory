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
 * UI logic for the wordbank panel — accumulates tested words for an attempt.
 *
 * @module     mod_graphitoubb/wordbank
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_graphitoubb/afd_simulator'], function(AfdSimulator) {

    var words = [];
    var automaton = null;

    /**
     * Set the current automaton to test words against.
     *
     * @param {object} afd Automaton descriptor.
     */
    var setAutomaton = function(afd) {
        automaton = afd;
    };

    /**
     * Test a word against the current automaton and add it to the bank.
     *
     * @param {string} word
     * @return {{word: string, accepted: boolean, trace: string[]}|null} Null if no automaton set.
     */
    var testWord = function(word) {
        if (!automaton) {
            return null;
        }
        var result = AfdSimulator.run(automaton, word);
        var entry = {word: word, accepted: result.accepted, trace: result.trace};
        words.push(entry);
        return entry;
    };

    /**
     * Return a copy of all tested words.
     *
     * @return {Array}
     */
    var getWords = function() {
        return words.slice();
    };

    return {
        setAutomaton: setAutomaton,
        testWord: testWord,
        getWords: getWords,
    };
});
