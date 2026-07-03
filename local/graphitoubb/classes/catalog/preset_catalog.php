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
 * Preset catalogue — loads curated exercises shipped with the plugin.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\catalog;

/**
 * Reads the JSON manifests under local/graphitoubb/catalog/ and exposes them as
 * {@see preset} value objects.
 *
 * A manifest is a JSON array of entries. Each entry carries localised metadata
 * objects ({"en": ..., "es": ...}) plus a canonical `payload`. For AFD the
 * student-facing prompt is itself localised inside payload.config.prompt and is
 * resolved here to a plain string so the saved problem stays canonical.
 *
 * The catalogue is read-only data — no DB, deterministic, unit-testable.
 */
final class preset_catalog {
    /** @var string Directory holding the per-tool JSON manifests. */
    private string $dir;

    /** @var preset[]|null Lazy cache keyed by load(). */
    private ?array $cache = null;

    /**
     * @param string|null $dir Override the manifest directory (tests). Defaults to
     *                         the shipped catalog/ folder of this plugin.
     */
    public function __construct(?string $dir = null) {
        $this->dir = $dir ?? (__DIR__ . '/../../catalog');
    }

    /**
     * Return every preset, optionally filtered by tool.
     *
     * @param  string|null $tool 'afd' | 'truth_table' | null for all.
     * @return preset[] Ordered as declared in the manifests.
     */
    public function all(?string $tool = null): array {
        $presets = $this->load();
        if ($tool === null) {
            return $presets;
        }
        return array_values(array_filter($presets, static fn(preset $p): bool => $p->tool === $tool));
    }

    /**
     * Look up a single preset by key.
     *
     * @param  string $key
     * @return preset|null
     */
    public function get(string $key): ?preset {
        foreach ($this->load() as $p) {
            if ($p->key === $key) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Load and cache all manifests, building resolved {@see preset} objects.
     *
     * @return preset[]
     */
    private function load(): array {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $lang     = $this->current_language();
        $presets  = [];
        $seenkeys = [];

        foreach (['afd', 'truth_table'] as $tool) {
            $file = $this->dir . '/' . $tool . '.json';
            if (!is_readable($file)) {
                continue;
            }
            $entries = json_decode((string) file_get_contents($file), true);
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                $preset = $this->build_preset($entry, $tool, $lang);
                if ($preset === null || isset($seenkeys[$preset->key])) {
                    continue;
                }
                $seenkeys[$preset->key] = true;
                $presets[]              = $preset;
            }
        }

        $this->cache = $presets;
        return $presets;
    }

    /**
     * Build a single preset from a raw manifest entry, resolving localised fields.
     *
     * @param  mixed  $entry Raw decoded entry.
     * @param  string $tool  Tool slug inferred from the manifest filename.
     * @param  string $lang  Resolved language code ('es' | 'en').
     * @return preset|null Null when the entry is malformed.
     */
    private function build_preset($entry, string $tool, string $lang): ?preset {
        if (!is_array($entry) || empty($entry['key']) || empty($entry['payload'])) {
            return null;
        }

        $payload = $entry['payload'];
        if (!is_array($payload)) {
            return null;
        }

        // Resolve a localised AFD prompt that lives inside the payload.
        if (isset($payload['config']['prompt']) && is_array($payload['config']['prompt'])) {
            $payload['config']['prompt'] = $this->pick($payload['config']['prompt'], $lang);
        }

        return new preset(
            (string) $entry['key'],
            (string) ($entry['tool'] ?? $tool),
            (string) ($entry['type'] ?? ($payload['type'] ?? '')),
            (string) ($entry['difficulty'] ?? 'medium'),
            array_values(array_map('strval', (array) ($entry['tags'] ?? []))),
            $this->pick($entry['title'] ?? '', $lang),
            $this->pick($entry['summary'] ?? '', $lang),
            $payload
        );
    }

    /**
     * Resolve a localised value ({"en":..,"es":..}) to a string in the given language.
     *
     * Falls back to Spanish, then English, then any available value, then "".
     *
     * @param  mixed  $value Localised object or plain string.
     * @param  string $lang
     * @return string
     */
    private function pick($value, string $lang): string {
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            return '';
        }
        foreach ([$lang, 'es', 'en'] as $code) {
            if (isset($value[$code]) && is_string($value[$code])) {
                return $value[$code];
            }
        }
        foreach ($value as $v) {
            if (is_string($v)) {
                return $v;
            }
        }
        return '';
    }

    /**
     * Current UI language collapsed to 'es' or 'en' (everything non-Spanish → en).
     *
     * @return string
     */
    private function current_language(): string {
        if (function_exists('current_language')) {
            $lang = (string) current_language();
            return (strpos($lang, 'es') === 0) ? 'es' : 'en';
        }
        return 'es';
    }
}
