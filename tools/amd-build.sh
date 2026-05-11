#!/usr/bin/env bash
# AMD minify pipeline for graphitoubb plugin family.
#
# Usage: bash tools/amd-build.sh
#
# Runs npx terser for each amd/src/*.js → amd/build/*.min.js.
# Handles both mod/graphitoubb and local/graphitoubb AMD trees.
#
# Convention for third-party libraries already minified (file size > 100 KB):
#   skip re-minification, copy src → build as-is to preserve the UMD bundle.
#   Example: mod/graphitoubb/amd/src/cytoscape.js (~1 MB, already minified).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

AMD_TREES=(
    "mod/graphitoubb"
    "local/graphitoubb"
)

SKIP_MINIFY_ABOVE_KB=100

build_tree() {
    local tree="$1"
    local src_dir="$REPO_ROOT/$tree/amd/src"
    local build_dir="$REPO_ROOT/$tree/amd/build"

    if [[ ! -d "$src_dir" ]]; then
        echo "  [skip] $tree/amd/src not found"
        return
    fi

    mkdir -p "$build_dir"

    for src_file in "$src_dir"/*.js; do
        [[ -f "$src_file" ]] || continue
        local base
        base="$(basename "$src_file" .js)"
        local out_file="$build_dir/${base}.min.js"
        local size_kb
        size_kb=$(du -k "$src_file" | cut -f1)

        if (( size_kb > SKIP_MINIFY_ABOVE_KB )); then
            echo "  [copy]  $tree/amd/src/${base}.js  (${size_kb}KB — already minified, copying)"
            cp "$src_file" "$out_file"
            cp "$src_file" "$build_dir/${base}.js"
        else
            echo "  [min]   $tree/amd/src/${base}.js → build/${base}.min.js"
            npx terser "$src_file" \
                --compress \
                --mangle \
                --output "$out_file"
        fi
    done
}

echo "=== AMD build pipeline ==="
for tree in "${AMD_TREES[@]}"; do
    echo "--- $tree ---"
    build_tree "$tree"
done
echo "=== Done ==="
