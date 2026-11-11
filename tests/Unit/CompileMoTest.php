<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/scripts/compile-mo.php';

/**
 * Tests for scripts/compile-mo.php, the msgfmt fallback used by `make mo` (#337).
 */
class CompileMoTest extends PHPUnitTestCase {

    private const FIXTURE = <<<'PO'
# Header is fuzzy, but must still be kept.
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: Test\n"
"POT-Creation-Date: 2026-01-01 00:00+0000\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"

msgid "Zeta"
msgstr "Zeta-es"

msgctxt "verb"
msgid "Post"
msgstr "Publicar"

#, fuzzy, php-format
msgid "Fuzzy %s"
msgstr "Borroso %s"

msgid "Untranslated"
msgstr ""

msgid "Line\n"
"two \"q\" \101\x42"
msgstr "Linea\n"
"dos \"q\""

msgid "%d day"
msgid_plural "%d days"
msgstr[0] "%d dia"
msgstr[1] "%d dias"

msgid "%d file"
msgid_plural "%d files"
msgstr[0] ""
msgstr[1] "%d archivos"

#~ msgid "Old"
#~ msgstr "Viejo"

msgid "Alpha"
msgstr "Alfa"
PO;

    private $tmpFiles = [];

    protected function tearDown(): void {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testFixtureCompilesToExpectedMessages(): void {
        $messages = $this->decodeMo(mayo_mo_build(mayo_po_parse(self::FIXTURE)));

        $this->assertSame(
            [
                '',
                "%d day\0%d days",
                'Alpha',
                "Line\ntwo \"q\" AB",
                'Zeta',
                "verb\x04Post",
            ],
            array_keys($messages),
            'Fuzzy, untranslated and obsolete entries are dropped; the rest are sorted bytewise.'
        );
        $this->assertSame("%d dia\0%d dias", $messages["%d day\0%d days"]);
        $this->assertSame('Publicar', $messages["verb\x04Post"]);
        $this->assertSame("Linea\ndos \"q\"", $messages["Line\ntwo \"q\" AB"]);
        $this->assertStringContainsString('Project-Id-Version: Test', $messages['']);
        $this->assertStringNotContainsString('POT-Creation-Date', $messages['']);
    }

    public function testMoHeaderIsLittleEndianWithoutHashTable(): void {
        $mo = mayo_mo_build(mayo_po_parse(self::FIXTURE));
        $header = unpack('Vmagic/Vrevision/Vcount/Vorig/Vtrans/Vhashsize/Vhashoffset', $mo);

        $this->assertSame(0x950412de, $header['magic']);
        $this->assertSame(0, $header['revision']);
        $this->assertSame(6, $header['count']);
        $this->assertSame(28, $header['orig']);
        $this->assertSame(28 + 8 * 6, $header['trans']);
        $this->assertSame(0, $header['hashsize']);
    }

    /**
     * @dataProvider localeProvider
     */
    public function testShippedLocalesCompile(string $poFile): void {
        $moFile = $this->tmp('.mo');
        $count = mayo_compile_mo($poFile, $moFile);

        $this->assertGreaterThan(1, $count);
        $this->assertCount($count, $this->decodeMo(file_get_contents($moFile)));
    }

    /**
     * @dataProvider localeProvider
     */
    public function testShippedLocalesMatchMsgfmt(string $poFile): void {
        $msgfmt = trim((string) shell_exec('command -v msgfmt 2>/dev/null'));
        if ($msgfmt === '') {
            $this->markTestSkipped('msgfmt is not installed.');
        }

        $ours = $this->tmp('.mo');
        $theirs = $this->tmp('.mo');
        mayo_compile_mo($poFile, $ours);
        exec(escapeshellarg($msgfmt) . ' --no-hash ' . escapeshellarg($poFile) . ' -o ' . escapeshellarg($theirs), $output, $status);

        $this->assertSame(0, $status, 'msgfmt failed');
        $this->assertSame(md5_file($theirs), md5_file($ours), basename($poFile) . ' differs from msgfmt --no-hash output');
    }

    public static function localeProvider(): array {
        $files = glob(dirname(__DIR__, 2) . '/languages/*.po');
        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    /**
     * @dataProvider malformedProvider
     */
    public function testMalformedPoIsRejected(string $source, string $expected): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expected);

        mayo_mo_build(mayo_po_parse($source));
    }

    public static function malformedProvider(): array {
        return [
            'unknown line' => ["msgid \"a\"\nmsgstr \"b\"\ngarbage\n", 'unrecognized line'],
            'unterminated string' => ["msgid \"a\nmsgstr \"b\"\n", 'malformed string literal'],
            'msgstr without msgid' => ["msgstr \"b\"\n", 'msgstr without msgid'],
            'missing msgstr' => ["msgid \"a\"\n\nmsgid \"b\"\nmsgstr \"c\"\n", 'missing msgstr'],
            'plural without forms' => ["msgid \"a\"\nmsgid_plural \"as\"\nmsgstr \"b\"\n", 'requires msgstr[N]'],
            'gap in plural forms' => ["msgid \"a\"\nmsgid_plural \"as\"\nmsgstr[0] \"b\"\nmsgstr[2] \"c\"\n", 'numbered consecutively'],
            'bad escape' => ["msgid \"a\\q\"\nmsgstr \"b\"\n", 'invalid escape'],
            'duplicate message' => ["msgid \"a\"\nmsgstr \"b\"\n\nmsgid \"a\"\nmsgstr \"c\"\n", 'duplicate message'],
        ];
    }

    public function testFailedCompileDoesNotWriteOutput(): void {
        $poFile = $this->tmp('.po');
        $moFile = $this->tmp('.mo');
        file_put_contents($poFile, "msgid \"a\"\nmsgstr \"b\"\ngarbage\n");

        try {
            mayo_compile_mo($poFile, $moFile);
            $this->fail('Expected a RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($poFile . ':3:', $e->getMessage());
        }
        $this->assertFileDoesNotExist($moFile);
    }

    /**
     * Decode a .mo file into [original => translation], in file order.
     */
    private function decodeMo(string $mo): array {
        $header = unpack('V7', $mo);
        $messages = [];
        for ($i = 0; $i < $header[3]; $i++) {
            $orig = unpack('Vlen/Voff', $mo, $header[4] + 8 * $i);
            $trans = unpack('Vlen/Voff', $mo, $header[5] + 8 * $i);
            $messages[substr($mo, $orig['off'], $orig['len'])] = substr($mo, $trans['off'], $trans['len']);
        }
        return $messages;
    }

    private function tmp(string $suffix): string {
        $base = tempnam(sys_get_temp_dir(), 'mayo-mo-');
        $this->tmpFiles[] = $base;
        $this->tmpFiles[] = $base . $suffix;
        return $base . $suffix;
    }
}
