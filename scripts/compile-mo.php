<?php
/**
 * Compile a gettext .po file into a binary .mo file.
 *
 * A dependency-free stand-in for `msgfmt`, used by `make mo` when gettext is
 * not installed (for example when a CI runner can't reach the apt mirror).
 * The output is byte-identical to `msgfmt --no-hash`:
 *
 *   - fuzzy and untranslated entries are left out (the header is always kept),
 *   - obsolete (#~) entries are ignored,
 *   - the POT-Creation-Date header field is dropped, as msgfmt does,
 *   - plural translations are joined with NUL, msgctxt is prefixed with "\x04",
 *   - originals are sorted bytewise and written in little-endian GNU .mo
 *     format with no hash table (WordPress's MO loader doesn't use it).
 *
 * Usage:
 *   php scripts/compile-mo.php <input.po> <output.mo>
 *
 * Exits non-zero, without writing the output, if the .po file is malformed.
 */

/**
 * Parse PO source text into a list of entries.
 *
 * Each entry is an array with keys: msgctxt (string|null), msgid (string),
 * msgid_plural (string|null), msgstr (string[] indexed by plural form),
 * fuzzy (bool).
 *
 * @throws RuntimeException On malformed input.
 */
function mayo_po_parse(string $source, string $name = 'input'): array
{
    $source = str_replace(["\r\n", "\r"], "\n", $source);
    if (strncmp($source, "\xEF\xBB\xBF", 3) === 0) {
        $source = substr($source, 3);
    }
    $lines = explode("\n", $source);

    $entries = [];
    $entry = null;
    $field = null;     // Key in $entry that continuation strings append to.
    $index = null;     // Plural index when $field === 'msgstr'.
    $fuzzy = false;    // Pending flags for the next entry.
    $obsolete = false; // Whether the current entry is a #~ entry.

    $fail = static function (int $lineNo, string $message) use ($name): void {
        throw new RuntimeException("{$name}:{$lineNo}: {$message}");
    };

    $finish = static function () use (&$entry, &$entries, &$field, &$fail, &$lineNo, &$obsolete): void {
        if ($entry === null) {
            return;
        }
        if (!isset($entry['msgid'])) {
            $fail($lineNo, 'entry has no msgid');
        }
        if (!$entry['msgstr']) {
            $fail($lineNo, 'missing msgstr for msgid "' . $entry['msgid'] . '"');
        }
        if ($entry['msgid_plural'] === null) {
            if (array_keys($entry['msgstr']) !== [0] || $entry['plural_syntax']) {
                $fail($lineNo, 'msgstr[N] used without msgid_plural');
            }
        } else {
            if (!$entry['plural_syntax']) {
                $fail($lineNo, 'msgid_plural requires msgstr[N] forms');
            }
            ksort($entry['msgstr']);
            if (array_keys($entry['msgstr']) !== range(0, count($entry['msgstr']) - 1)) {
                $fail($lineNo, 'plural forms are not numbered consecutively from 0');
            }
        }
        unset($entry['plural_syntax']);
        if (!$obsolete) {
            $entries[] = $entry;
        }
        $entry = null;
        $field = null;
    };

    $newEntry = static function (bool $isFuzzy): array {
        return [
            'msgctxt' => null,
            'msgid' => null,
            'msgid_plural' => null,
            'msgstr' => [],
            'fuzzy' => $isFuzzy,
            'plural_syntax' => false,
        ];
    };

    foreach ($lines as $i => $raw) {
        $lineNo = $i + 1;
        $line = trim($raw);

        if ($line === '') {
            continue;
        }

        // Obsolete entries: parse for well-formedness but discard.
        $isObsoleteLine = false;
        if (strncmp($line, '#~', 2) === 0) {
            $isObsoleteLine = true;
            $line = ltrim(substr($line, 2));
            if (strncmp($line, '|', 1) === 0 || $line === '') {
                continue; // "#~|" previous-msgid comment.
            }
        } elseif ($line[0] === '#') {
            if (strncmp($line, '#,', 2) === 0) {
                $flags = array_map('trim', explode(',', substr($line, 2)));
                if (in_array('fuzzy', $flags, true)) {
                    $fuzzy = true;
                }
            }
            continue;
        }

        if ($line[0] === '"') {
            if ($field === null) {
                $fail($lineNo, 'string continuation without a keyword');
            }
            $value = mayo_po_unquote($line, $lineNo, $fail);
            if ($field === 'msgstr') {
                $entry['msgstr'][$index] .= $value;
            } else {
                $entry[$field] .= $value;
            }
            continue;
        }

        if (!preg_match('/^(msgctxt|msgid_plural|msgid|msgstr)(?:\[(\d+)\])?\s+(".*)$/', $line, $m)) {
            $fail($lineNo, 'unrecognized line: ' . $line);
        }
        [, $keyword, $plural, $quoted] = $m;
        $value = mayo_po_unquote($quoted, $lineNo, $fail);

        if ($plural !== '' && $keyword !== 'msgstr') {
            $fail($lineNo, "{$keyword} cannot take a plural index");
        }

        switch ($keyword) {
            case 'msgctxt':
            case 'msgid':
                // A msgctxt, or a msgid not preceded by its msgctxt, starts a new entry.
                if ($entry !== null && ($keyword === 'msgctxt' || $entry['msgid'] !== null)) {
                    $finish();
                }
                if ($entry === null) {
                    $entry = $newEntry($fuzzy);
                    $fuzzy = false;
                    $obsolete = $isObsoleteLine;
                }
                $entry[$keyword] = $value;
                $field = $keyword;
                break;

            case 'msgid_plural':
                if ($entry === null || $entry['msgid'] === null || $entry['msgstr']) {
                    $fail($lineNo, 'msgid_plural must follow msgid');
                }
                if ($entry['msgid_plural'] !== null) {
                    $fail($lineNo, 'duplicate msgid_plural');
                }
                $entry['msgid_plural'] = $value;
                $field = 'msgid_plural';
                break;

            case 'msgstr':
                if ($entry === null || $entry['msgid'] === null) {
                    $fail($lineNo, 'msgstr without msgid');
                }
                $idx = $plural === '' ? 0 : (int) $plural;
                if (array_key_exists($idx, $entry['msgstr'])) {
                    $fail($lineNo, 'duplicate msgstr' . ($plural === '' ? '' : "[{$idx}]"));
                }
                if ($plural !== '') {
                    $entry['plural_syntax'] = true;
                } elseif ($entry['msgstr']) {
                    $fail($lineNo, 'msgstr mixed with msgstr[N]');
                }
                $entry['msgstr'][$idx] = $value;
                $field = 'msgstr';
                $index = $idx;
                break;
        }
    }

    $lineNo = count($lines);
    $finish();

    return $entries;
}

/**
 * Decode one quoted PO string literal (C escape syntax).
 */
function mayo_po_unquote(string $quoted, int $lineNo, callable $fail): string
{
    if (!preg_match('/^"((?:[^"\\\\]|\\\\.)*)"$/s', $quoted, $m)) {
        $fail($lineNo, 'malformed string literal: ' . $quoted);
    }
    $body = $m[1];
    $simple = ['n' => "\n", 't' => "\t", 'r' => "\r", '"' => '"', '\\' => '\\',
        'a' => "\x07", 'b' => "\x08", 'f' => "\x0C", 'v' => "\x0B", '?' => '?', "'" => "'"];

    return preg_replace_callback(
        '/\\\\(?:([0-7]{1,3})|x([0-9A-Fa-f]+)|(.))/s',
        static function (array $e) use ($simple, $lineNo, $fail): string {
            if (isset($e[1]) && $e[1] !== '') {
                return chr(octdec($e[1]) & 0xFF);
            }
            if (isset($e[2]) && $e[2] !== '') {
                return chr(hexdec($e[2]) & 0xFF);
            }
            if (!isset($simple[$e[3]])) {
                $fail($lineNo, 'invalid escape sequence \\' . $e[3]);
            }
            return $simple[$e[3]];
        },
        $body
    );
}

/**
 * Build the .mo binary for a list of parsed PO entries.
 *
 * @throws RuntimeException On duplicate messages.
 */
function mayo_mo_build(array $entries, string $name = 'input'): string
{
    $messages = [];
    foreach ($entries as $entry) {
        $isHeader = $entry['msgid'] === '' && $entry['msgctxt'] === null;

        if ($entry['fuzzy'] && !$isHeader) {
            continue;
        }
        // Untranslated: like msgfmt, only an empty first form counts. A plural
        // with later forms left empty is still emitted.
        if ($entry['msgstr'][0] === '') {
            continue;
        }

        $original = $entry['msgid'];
        if ($entry['msgid_plural'] !== null) {
            $original .= "\0" . $entry['msgid_plural'];
        }
        if ($entry['msgctxt'] !== null) {
            $original = $entry['msgctxt'] . "\x04" . $original;
        }

        $translation = implode("\0", $entry['msgstr']);
        if ($isHeader) {
            $translation = preg_replace('/^POT-Creation-Date:[^\n]*\n/m', '', $translation);
        }

        if (array_key_exists($original, $messages)) {
            $label = str_replace(["\0", "\x04"], [' / ', ' | '], $original);
            throw new RuntimeException("{$name}: duplicate message definition \"{$label}\"");
        }
        $messages[$original] = $translation;
    }

    uksort($messages, 'strcmp');

    $count = count($messages);
    $originalsTable = 28;
    $translationsTable = $originalsTable + 8 * $count;
    $hashTable = $translationsTable + 8 * $count;
    $offset = $hashTable; // Hash table size is 0, so strings start here.

    $header = pack('V7', 0x950412de, 0, $count, $originalsTable, $translationsTable, 0, $hashTable);

    $originalsIndex = '';
    $translationsIndex = '';
    $originalsData = '';
    $translationsData = '';

    foreach (array_keys($messages) as $original) {
        $original = (string) $original;
        $originalsIndex .= pack('V2', strlen($original), $offset + strlen($originalsData));
        $originalsData .= $original . "\0";
    }
    $offset += strlen($originalsData);
    foreach ($messages as $translation) {
        $translationsIndex .= pack('V2', strlen($translation), $offset + strlen($translationsData));
        $translationsData .= $translation . "\0";
    }

    return $header . $originalsIndex . $translationsIndex . $originalsData . $translationsData;
}

/**
 * Compile $poFile into $moFile. The output is only written on success.
 *
 * @throws RuntimeException On read, parse, or write failure.
 */
function mayo_compile_mo(string $poFile, string $moFile): int
{
    $source = @file_get_contents($poFile);
    if ($source === false) {
        throw new RuntimeException("{$poFile}: cannot read file");
    }
    $entries = mayo_po_parse($source, $poFile);
    $mo = mayo_mo_build($entries, $poFile);

    if (@file_put_contents($moFile, $mo) === false) {
        throw new RuntimeException("{$moFile}: cannot write file");
    }

    return unpack('V', $mo, 8)[1];
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    if ($argc !== 3) {
        fwrite(STDERR, "Usage: php {$argv[0]} <input.po> <output.mo>\n");
        exit(2);
    }
    try {
        $count = mayo_compile_mo($argv[1], $argv[2]);
        fwrite(STDERR, "{$argv[1]}: {$count} messages written to {$argv[2]}\n");
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'compile-mo: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
