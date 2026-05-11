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

declare(strict_types=1);

namespace mod_graphitoubb\output;

use advanced_testcase;

/**
 * Tests for mod_graphitoubb renderer.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_graphitoubb\output\renderer
 */
final class renderer_test extends advanced_testcase {
    /** @var renderer */
    private renderer $renderer;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_context(\context_system::instance());
        $this->renderer = $PAGE->get_renderer('mod_graphitoubb');
    }

    /**
     * Tests render_editor returns a non-empty HTML string.
     *
     * @covers \mod_graphitoubb\output\renderer::render_editor
     */
    public function test_render_editor_returns_nonempty_string(): void {
        $html = $this->renderer->render_editor(42, 7, 1);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Tests render_editor embeds the attempt id in output.
     *
     * @covers \mod_graphitoubb\output\renderer::render_editor
     */
    public function test_render_editor_contains_attemptid(): void {
        $html = $this->renderer->render_editor(99, 3, 1);
        $this->assertStringContainsString('99', $html);
    }

    /**
     * Tests render_attempt_summary returns a non-empty string containing the attempt id.
     *
     * @covers \mod_graphitoubb\output\renderer::render_attempt_summary
     */
    public function test_render_attempt_summary_returns_nonempty_string(): void {
        $attempt = (object) [
            'id'           => 55,
            'instanceid'   => 1,
            'userid'       => 2,
            'status'       => 'inprogress',
            'timestarted'  => time(),
            'timefinished' => null,
        ];
        $html = $this->renderer->render_attempt_summary($attempt);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('55', $html);
    }

    /**
     * Tests render_attempt_summary reflects the attempt status in output.
     *
     * @covers \mod_graphitoubb\output\renderer::render_attempt_summary
     */
    public function test_render_attempt_summary_contains_status(): void {
        $attempt = (object) [
            'id'           => 10,
            'instanceid'   => 1,
            'userid'       => 2,
            'status'       => 'finished',
            'timestarted'  => time(),
            'timefinished' => time(),
        ];
        $html = $this->renderer->render_attempt_summary($attempt);
        $this->assertStringContainsString('finished', $html);
    }

    /**
     * Tests render_attempt_list with empty array returns a non-empty HTML string.
     *
     * @covers \mod_graphitoubb\output\renderer::render_attempt_list
     */
    public function test_render_attempt_list_empty_returns_nonempty_string(): void {
        $context = \context_system::instance();
        $html    = $this->renderer->render_attempt_list([], $context);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Tests render_attempt_list with one attempt includes the student fullname.
     *
     * @covers \mod_graphitoubb\output\renderer::render_attempt_list
     */
    public function test_render_attempt_list_shows_fullname(): void {
        $attempt = (object) [
            'id'                 => 1,
            'userid'             => 99,
            'status'             => 'inprogress',
            'timestarted'        => time(),
            'timefinished'       => null,
            'firstname'          => 'Jane',
            'lastname'           => 'Doe',
            'firstnamephonetic'  => '',
            'lastnamephonetic'   => '',
            'middlename'         => '',
            'alternatename'      => '',
            'snapshot_count'     => 0,
            'last_word_tested'   => null,
        ];
        $context = \context_system::instance();
        $html    = $this->renderer->render_attempt_list([$attempt], $context);
        $this->assertStringContainsString('Jane', $html);
        $this->assertStringContainsString('Doe', $html);
    }

    /**
     * Tests render_attempt_list with populated attempts shows snapshot count.
     *
     * @covers \mod_graphitoubb\output\renderer::render_attempt_list
     */
    public function test_render_attempt_list_shows_snapshot_count(): void {
        $attempt = (object) [
            'id'                => 2,
            'userid'            => 7,
            'status'            => 'finished',
            'timestarted'       => time(),
            'timefinished'      => time(),
            'firstname'         => 'John',
            'lastname'          => 'Smith',
            'firstnamephonetic' => '',
            'lastnamephonetic'  => '',
            'middlename'        => '',
            'alternatename'     => '',
            'snapshot_count'    => 3,
            'last_word_tested'  => 'abc',
        ];
        $context = \context_system::instance();
        $html    = $this->renderer->render_attempt_list([$attempt], $context);
        $this->assertStringContainsString('3', $html);
        $this->assertStringContainsString('abc', $html);
    }

    /**
     * Neither capability: render_view_links returns empty string.
     *
     * @covers \mod_graphitoubb\output\renderer::render_view_links
     */
    public function test_render_view_links_neither_returns_empty(): void {
        $html = $this->renderer->render_view_links(1, false, false);
        $this->assertSame('', $html);
    }

    /**
     * Attempt-only: no report link rendered.
     *
     * @covers \mod_graphitoubb\output\renderer::render_view_links
     */
    public function test_render_view_links_attempt_only_returns_empty(): void {
        $html = $this->renderer->render_view_links(1, false, true);
        $this->assertSame('', $html);
    }

    /**
     * Viewreport-only: report link is rendered.
     *
     * @covers \mod_graphitoubb\output\renderer::render_view_links
     */
    public function test_render_view_links_viewreport_only_shows_link(): void {
        $html = $this->renderer->render_view_links(42, true, false);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('report.php', $html);
    }

    /**
     * Both capabilities: report link is rendered alongside editor.
     *
     * @covers \mod_graphitoubb\output\renderer::render_view_links
     */
    public function test_render_view_links_both_shows_report_link(): void {
        $html = $this->renderer->render_view_links(42, true, true);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('report.php', $html);
    }

    /**
     * Tests render_editor output includes the canvas container the AMD module targets.
     *
     * Moodle processes {{#js}} blocks via $PAGE->requires (not the returned HTML string),
     * so we assert on the canvas element that afd_editor.init() looks up at runtime.
     *
     * @covers \mod_graphitoubb\output\renderer::render_editor
     */
    public function test_render_editor_includes_canvas_container(): void {
        $html = $this->renderer->render_editor(1, 5, 1);
        $this->assertStringContainsString('graphitoubb-canvas-5', $html);
    }
}
