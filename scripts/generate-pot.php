<?php
/**
 * Lightweight POT generator for Mayo Events Manager.
 *
 * Walks the project's PHP and JS source files and extracts strings passed to
 * common translation functions (`__`, `_e`, `_x`, `_n`, `esc_html__`,
 * `esc_attr__`, `esc_html_e`, `esc_attr_e`, `_n_noop`, `_nx_noop`, plus the
 * matching `@wordpress/i18n` JS counterparts).
 *
 * For real releases the recommended workflow is `wp i18n make-pot`, which
 * understands more edge cases. This script exists as a no-dependency fallback
 * so the POT can be regenerated in any environment.
 *
 * Usage:
 *   php scripts/generate-pot.php > languages/mayo-events-manager.pot
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$textDomain  = 'mayo-events-manager';

$includeDirs = [
    $projectRoot . '/includes',
    $projectRoot . '/assets/js/src',
    $projectRoot . '/templates',
];
$includeFiles = [
    $projectRoot . '/mayo-events-manager.php',
];

$exts = ['php', 'js', 'jsx'];

/**
 * Walk a directory and yield matching files.
 *
 * @param string   $dir
 * @param string[] $exts
 * @return Generator<string>
 */
function walk_dir(string $dir, array $exts): Generator
{
    if (! is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $exts, true)) {
            yield $file->getPathname();
        }
    }
}

$files = [];
foreach ($includeDirs as $dir) {
    foreach (walk_dir($dir, $exts) as $f) {
        $files[] = $f;
    }
}
foreach ($includeFiles as $f) {
    if (is_file($f)) {
        $files[] = $f;
    }
}

// Pattern matches translation calls with single- or double-quoted strings,
// optionally followed by additional args, ending with the text-domain literal.
$singularFns = '__|_e|_x|_ex|esc_html__|esc_attr__|esc_html_e|esc_attr_e';
$pluralFns   = '_n|_nx|_n_noop|_nx_noop';

$strings = [];

foreach ($files as $path) {
    $relative = ltrim(str_replace($projectRoot, '', $path), '/');
    $contents = file_get_contents($path);
    if ($contents === false) {
        continue;
    }

    // Singular forms
    if (
        preg_match_all(
            '/\b(?:' . $singularFns . ')\s*\(\s*' .
            '(?:(?P<context>(?:\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*"))\s*,\s*)?' .
            '(?P<msg>(?:\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*"))' .
            '\s*,\s*' .
            '(?:\'' . preg_quote($textDomain, '/') . '\'|"' . preg_quote($textDomain, '/') . '")/u',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE
        )
    ) {
        foreach ($matches['msg'] as $i => $m) {
            $msgid = unquote_php($m[0]);
            // We can't tell unambiguously whether the optional first arg was a
            // context, since the regex makes it optional. Treat the third
            // captured slot as the msgid; ignore context here for simplicity.
            $key = $msgid;
            $line = line_at($contents, $m[1]);
            register_string($strings, $key, $msgid, null, null, $relative, $line);
        }
    }

    // Plural forms: _n(singular, plural, count, domain)
    if (
        preg_match_all(
            '/\b(?:' . $pluralFns . ')\s*\(\s*' .
            '(?P<single>(?:\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*"))' .
            '\s*,\s*' .
            '(?P<plural>(?:\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*"))' .
            '\s*,\s*[^,]+\s*,\s*' .
            '(?:\'' . preg_quote($textDomain, '/') . '\'|"' . preg_quote($textDomain, '/') . '")/u',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE
        )
    ) {
        foreach ($matches['single'] as $i => $m) {
            $singular = unquote_php($m[0]);
            $plural   = unquote_php($matches['plural'][$i][0]);
            $key      = $singular . "\0" . $plural;
            $line     = line_at($contents, $m[1]);
            register_string($strings, $key, $singular, $plural, null, $relative, $line);
        }
    }
}

/**
 * @param array<string, array{msgid:string,plural:?string,context:?string,refs:string[]}> $strings
 */
function register_string(
    array &$strings,
    string $key,
    string $msgid,
    ?string $plural,
    ?string $context,
    string $file,
    int $line
): void {
    if (! isset($strings[$key])) {
        $strings[$key] = [
            'msgid'   => $msgid,
            'plural'  => $plural,
            'context' => $context,
            'refs'    => [],
        ];
    }
    $strings[$key]['refs'][] = $file . ':' . $line;
}

function unquote_php(string $literal): string
{
    if ($literal === '') {
        return '';
    }
    $quote = $literal[0];
    $body  = substr($literal, 1, -1);
    if ($quote === "'") {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
    }
    return stripcslashes($body);
}

function line_at(string $contents, int $offset): int
{
    return substr_count(substr($contents, 0, $offset), "\n") + 1;
}

function escape_pot(string $s): string
{
    $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
    $s = str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $s);
    return $s;
}

ksort($strings);

$timestamp = gmdate('Y-m-d H:i+0000');

$header = <<<HEADER
# Copyright (C) bmlt-enabled
# This file is distributed under the GPLv2 or later license.
msgid ""
msgstr ""
"Project-Id-Version: Mayo Events Manager\\n"
"Report-Msgid-Bugs-To: https://github.com/bmlt-enabled/mayo/issues\\n"
"POT-Creation-Date: $timestamp\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Language-Team: \\n"
"X-Domain: mayo-events-manager\\n"


HEADER;

echo $header;

foreach ($strings as $entry) {
    foreach ($entry['refs'] as $ref) {
        echo '#: ' . $ref . "\n";
    }
    if ($entry['plural'] !== null) {
        echo 'msgid "'  . escape_pot($entry['msgid'])  . "\"\n";
        echo 'msgid_plural "' . escape_pot($entry['plural']) . "\"\n";
        echo "msgstr[0] \"\"\n";
        echo "msgstr[1] \"\"\n\n";
    } else {
        echo 'msgid "' . escape_pot($entry['msgid']) . "\"\n";
        echo "msgstr \"\"\n\n";
    }
}
