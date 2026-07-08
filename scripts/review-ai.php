<?php
/**
 * review-ai.php — the AI review pass of the tiger-vendor-bot.
 *
 * Runs ONLY after review.php's deterministic gate passes (so the LLM never sees junk). It
 * gathers the module's key files from the pinned ref, asks a model for a quality + SAFETY
 * verdict, and prints a structured result the workflow posts as PR feedback.
 *
 * Provider-agnostic (OpenAI-compatible chat/completions). Configure via env — defaults to
 * GitHub Models, which runs inside the Action with no extra service:
 *   MODEL_ENDPOINT  default https://models.github.ai/inference/chat/completions
 *   MODEL_NAME      default openai/gpt-4o-mini
 *   MODEL_TOKEN     required for a live call (a GitHub PAT w/ models:read, or an API key)
 *
 *   php scripts/review-ai.php data/<Org>_<Repo>.json            # live (needs MODEL_TOKEN)
 *   php scripts/review-ai.php data/<Org>_<Repo>.json --dry-run  # gather + print the prompt only
 *
 * Exit 0 = accept, 1 = reject/changes-requested, 2 = usage/gather error.
 */

const RAW = 'https://raw.githubusercontent.com';
const API = 'https://api.github.com';
const MAX_FILES = 18;
const MAX_BYTES = 48000;   // keep the prompt cheap

$path   = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "usage: php scripts/review-ai.php data/<Org>_<Repo>.json [--dry-run]\n");
    exit(2);
}
$listing = json_decode((string) file_get_contents($path), true);
if (!preg_match('~github\.com/([^/]+)/([^/]+?)/?$~', (string) ($listing['repository'] ?? ''), $m)) {
    fwrite(STDERR, "bad repository URL in listing\n"); exit(2);
}
[$org, $repo] = [$m[1], $m[2]];
$ref = (string) ($listing['ref'] ?? ($listing['version'] ?? 'main'));

// --- gather the executable + descriptive surface at the pinned ref ---
$tree = json_decode((string) http(API . "/repos/{$org}/{$repo}/git/trees/{$ref}?recursive=1"), true);
if (!is_array($tree) || empty($tree['tree'])) {
    fwrite(STDERR, "could not read repo tree for {$org}/{$repo}@{$ref}\n"); exit(2);
}
$want = static function ($p) {
    return $p === 'module.json' || $p === 'TIGER.md'
        || (substr($p, -4) === '.php' && !preg_match('#(^|/)(tests?|vendor)/#', $p));
};
$files = [];
$bytes = 0;
foreach ($tree['tree'] as $node) {
    if (($node['type'] ?? '') !== 'blob' || !$want($node['path'])) { continue; }
    if (count($files) >= MAX_FILES || $bytes >= MAX_BYTES) { break; }
    $content = http(RAW . "/{$org}/{$repo}/{$ref}/" . $node['path']);
    if ($content === null) { continue; }
    $content = substr($content, 0, 8000);
    $files[$node['path']] = $content;
    $bytes += strlen($content);
}

$prompt = build_prompt($listing, $files);
if ($dryRun) {
    echo $prompt . "\n";
    fwrite(STDERR, "\n[dry-run] gathered " . count($files) . " file(s), " . $bytes . " bytes. Set MODEL_TOKEN for a live call.\n");
    exit(0);
}

// --- call the model ---
$token = getenv('MODEL_TOKEN') ?: '';
if ($token === '') { fwrite(STDERR, "MODEL_TOKEN not set — cannot run the AI pass.\n"); exit(2); }
$verdict = call_model($prompt, $token);
if (!$verdict) { fwrite(STDERR, "model call failed or returned unparseable output.\n"); exit(2); }

echo format_markdown($verdict);
exit(($verdict['verdict'] ?? 'reject') === 'accept' ? 0 : 1);

// ---------------------------------------------------------------------------

function build_prompt(array $listing, array $files): string
{
    $sys = <<<SYS
You are tiger-vendor-bot, reviewing a submission to the open Tiger module registry. Tiger
installs a module by running its PHP in the host app, so your job is a QUALITY + SAFETY triage,
not a guarantee. Judge:
  1. Does the code match what the listing + module.json + TIGER.md claim (name, purpose)?
  2. SAFETY red flags: obfuscation, eval of dynamic/remote/base64 code, exec/shell of remote
     input, credential/secret exfiltration, hidden network calls, backdoors, license laundering.
  3. Basic quality: is it a coherent, real Tiger module (Bootstrap + controllers/services),
     not spam/placeholder?
Public code being reviewable is the price of admission; do NOT reject for being proprietary if
a license is declared. Be specific and fair. When unsure, prefer "flag" over "reject".

Respond with ONLY minified JSON:
{"verdict":"accept|flag|reject","confidence":0.0-1.0,"summary":"one sentence",
 "findings":[{"severity":"info|warn|block","note":"...","file":"path or null"}]}
SYS;

    $ctx = "LISTING:\n" . json_encode($listing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\nFILES:\n";
    foreach ($files as $p => $c) {
        $ctx .= "\n===== {$p} =====\n{$c}\n";
    }
    return "SYSTEM:\n{$sys}\n\nUSER:\n{$ctx}";
}

function call_model(string $prompt, string $token): ?array
{
    $endpoint = getenv('MODEL_ENDPOINT') ?: 'https://models.github.ai/inference/chat/completions';
    $model    = getenv('MODEL_NAME') ?: 'openai/gpt-4o-mini';
    [$sys, $user] = array_pad(explode("\n\nUSER:\n", $prompt, 2), 2, '');
    $sys = preg_replace('/^SYSTEM:\n/', '', $sys);

    $payload = json_encode([
        'model'       => $model,
        'temperature' => 0,
        'messages'    => [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => $user],
        ],
    ]);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code < 200 || $code >= 300 || !$res) { return null; }

    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    if (preg_match('/\{.*\}/s', (string) $text, $mm)) {
        $v = json_decode($mm[0], true);
        return is_array($v) ? $v : null;
    }
    return null;
}

function format_markdown(array $v): string
{
    $icon = ['accept' => '✅', 'flag' => '⚠️', 'reject' => '⛔'][$v['verdict'] ?? 'reject'] ?? '❓';
    $out  = "### {$icon} tiger-vendor-bot review — **" . strtoupper($v['verdict'] ?? '?') . "**"
        . (isset($v['confidence']) ? " _(confidence " . round($v['confidence'] * 100) . "%)_" : '') . "\n\n";
    $out .= ($v['summary'] ?? '') . "\n\n";
    foreach ($v['findings'] ?? [] as $f) {
        $mark = ['info' => 'ℹ️', 'warn' => '⚠️', 'block' => '⛔'][$f['severity'] ?? 'info'] ?? '•';
        $out .= "- {$mark} " . ($f['file'] ? "`{$f['file']}` — " : '') . ($f['note'] ?? '') . "\n";
    }
    $out .= "\n_Automated triage — a maintainer makes the final call._\n";
    return $out;
}

function http(string $url): ?string
{
    $h = ['User-Agent: tiger-vendor-bot'];
    if (($t = getenv('GITHUB_TOKEN'))) { $h[] = 'Authorization: Bearer ' . $t; }   // lifts API rate limits in CI
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => $h]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ($code >= 200 && $code < 300) ? $res : null;
}
