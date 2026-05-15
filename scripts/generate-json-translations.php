<?php
/**
 * Generate JED-format JSON translation files for JavaScript bundles.
 *
 * Parses each .po file in languages/, extracts entries whose source references
 * include assets/js/, and writes a per-locale JSON file that WordPress loads
 * via wp_set_script_translations().
 *
 * Usage:
 *   php scripts/generate-json-translations.php
 */

$langDir = __DIR__ . '/../languages';

$locales = [
    'es_ES' => 'nplurals=2; plural=(n != 1);',
    'pt_BR' => 'nplurals=2; plural=(n != 1);',
    'fr_FR' => 'nplurals=2; plural=(n > 1);',
];

foreach ($locales as $locale => $pluralForms) {
    $poFile = "{$langDir}/mayo-events-manager-{$locale}.po";
    if (!file_exists($poFile)) {
        echo "Skipping {$locale}: .po file not found\n";
        continue;
    }

    $entries = parsePo($poFile);
    $jsEntries = filterJsEntries($entries);

    $jed = buildJed($locale, $pluralForms, $jsEntries);

    // Write one JSON per script handle that has JS strings
    $jsonFile = "{$langDir}/mayo-events-manager-{$locale}-mayo-public.json";
    file_put_contents($jsonFile, json_encode($jed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    echo "{$locale}: wrote " . count($jsEntries) . " JS entries to " . basename($jsonFile) . "\n";
}

/**
 * Parse a PO file into an array of entries.
 */
function parsePo(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $entries = [];
    $current = null;
    $lastKey = null;

    foreach ($lines as $line) {
        // Source reference comments
        if (str_starts_with($line, '#:')) {
            if ($current === null) {
                $current = ['refs' => [], 'msgid' => '', 'msgid_plural' => '', 'msgstr' => []];
            }
            $current['refs'][] = trim(substr($line, 2));
            continue;
        }

        // Other comments — skip
        if (str_starts_with($line, '#')) {
            continue;
        }

        // Blank line — save current entry
        if (trim($line) === '') {
            if ($current !== null && $current['msgid'] !== '') {
                $entries[] = $current;
            }
            $current = null;
            $lastKey = null;
            continue;
        }

        // msgid
        if (str_starts_with($line, 'msgid_plural ')) {
            $current['msgid_plural'] = extractString($line, 'msgid_plural ');
            $lastKey = 'msgid_plural';
            continue;
        }
        if (str_starts_with($line, 'msgid ')) {
            if ($current === null) {
                $current = ['refs' => [], 'msgid' => '', 'msgid_plural' => '', 'msgstr' => []];
            }
            $current['msgid'] = extractString($line, 'msgid ');
            $lastKey = 'msgid';
            continue;
        }

        // msgstr[N]
        if (preg_match('/^msgstr\[(\d+)\] /', $line, $m)) {
            $idx = (int)$m[1];
            $current['msgstr'][$idx] = extractString($line, "msgstr[{$idx}] ");
            $lastKey = "msgstr[{$idx}]";
            continue;
        }

        // msgstr (singular)
        if (str_starts_with($line, 'msgstr ')) {
            $current['msgstr'][0] = extractString($line, 'msgstr ');
            $lastKey = 'msgstr';
            continue;
        }

        // Continuation line (starts with ")
        if (str_starts_with($line, '"') && $current !== null && $lastKey !== null) {
            $continued = extractQuoted($line);
            if ($lastKey === 'msgid') {
                $current['msgid'] .= $continued;
            } elseif ($lastKey === 'msgid_plural') {
                $current['msgid_plural'] .= $continued;
            } elseif ($lastKey === 'msgstr') {
                $current['msgstr'][0] .= $continued;
            } elseif (preg_match('/^msgstr\[(\d+)\]$/', $lastKey, $m)) {
                $current['msgstr'][(int)$m[1]] .= $continued;
            }
        }
    }
    // Don't forget last entry
    if ($current !== null && $current['msgid'] !== '') {
        $entries[] = $current;
    }

    return $entries;
}

/**
 * Filter entries to only those with at least one JS source reference.
 */
function filterJsEntries(array $entries): array
{
    return array_filter($entries, function ($entry) {
        foreach ($entry['refs'] as $ref) {
            if (str_contains($ref, 'assets/js/')) {
                return true;
            }
        }
        return false;
    });
}

/**
 * Build a JED 1.x-format structure.
 */
function buildJed(string $locale, string $pluralForms, array $entries): array
{
    $messages = [
        '' => [
            'domain' => 'messages',
            'lang'   => $locale,
            'plural_forms' => $pluralForms,
        ],
    ];

    foreach ($entries as $entry) {
        $key = $entry['msgid'];
        if ($entry['msgid_plural'] !== '') {
            // Plural entry: value is [singular_translation, plural_translation, ...]
            $translations = [];
            ksort($entry['msgstr']);
            foreach ($entry['msgstr'] as $str) {
                $translations[] = $str;
            }
            $messages[$key] = $translations;
        } else {
            // Singular entry: value is [translation]
            $messages[$key] = [$entry['msgstr'][0] ?? ''];
        }
    }

    return [
        'translation-revision-date' => '2026-05-15 00:00+0000',
        'generator'  => 'mayo-generate-json-translations',
        'source'     => 'assets/js/dist/public.bundle.js',
        'domain'     => 'messages',
        'locale_data' => [
            'messages' => $messages,
        ],
    ];
}

function extractString(string $line, string $prefix): string
{
    $rest = substr($line, strlen($prefix));
    return extractQuoted($rest);
}

function extractQuoted(string $str): string
{
    $str = trim($str);
    if (str_starts_with($str, '"') && str_ends_with($str, '"')) {
        $str = substr($str, 1, -1);
    }
    // Unescape PO escape sequences
    $str = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $str);
    return $str;
}
