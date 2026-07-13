<?php
/**
 * compile-index.php — build data/index.json from the per-module data/<Org>_<Repo>.json files.
 *
 * The registry is one JSON file per module (so PRs never conflict). This concatenates the
 * ACCEPTED listings into a single search index that Tiger fetches + caches. Run by CI on any
 * change under data/ (see .github/workflows/compile.yml).
 *
 * The index is kept PURE vendor data — sorted alphabetically by module name as a stable default.
 * SPONSORSHIP is NOT baked in here: Tiger fetches data/sponsored.json alongside the index and
 * merges the `priority` at search time (client-side, so placement updates need no recompile).
 * `type` defaults to "plugin" when a listing omits it (search label only; see schema).
 *
 *   php scripts/compile-index.php
 */
$dataDir = dirname(__DIR__) . '/data';
$modules = [];

foreach (glob($dataDir . '/*.json') as $file) {
    // index.json is our output; sponsored.json is a curated overlay Tiger merges at runtime.
    if (in_array(basename($file), ['index.json', 'sponsored.json'], true)) {
        continue;
    }
    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        fwrite(STDERR, "  skip (invalid JSON): " . basename($file) . "\n");
        continue;
    }
    // Only reviewed-and-accepted listings appear in the index (pending/rejected stay out).
    if (($json['review']['status'] ?? '') !== 'accepted') {
        fwrite(STDERR, "  skip (not accepted): " . basename($file) . "\n");
        continue;
    }
    $json['type'] = $json['type'] ?? 'plugin';   // search label only; non-behavioral
    $modules[] = $json;
}

// Stable default order: alphabetical by module name. Tiger re-sorts per the chosen view
// (Featured = sponsored priority, Title, Latest) after merging sponsored.json.
usort($modules, static fn($a, $b) => strcasecmp($a['module'] ?? '', $b['module'] ?? ''));

$index = [
    'schema'       => 'tiger.registry-index/v1',
    'generated_at' => gmdate('c'),
    'count'        => count($modules),
    'modules'      => $modules,
];

file_put_contents($dataDir . '/index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "  wrote data/index.json (" . count($modules) . " module" . (count($modules) === 1 ? '' : 's') . ")\n";
