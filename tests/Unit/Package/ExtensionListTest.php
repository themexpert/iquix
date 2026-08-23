<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Package\ExtensionList;
use PHPUnit\Framework\TestCase;

final class ExtensionListTest extends TestCase
{
    private const MANIFEST = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<extension type="package" method="upgrade">
    <name>Quix</name>
    <version>6.2.7</version>
    <files>
        <file type="component" id="com_quix">com_quix.zip</file>
        <file type="module" id="mod_quix_info" client="admin">mod_quix_info.zip</file>
        <file type="library" id="quix">lib_quixnxt.zip</file>
        <file type="plugin" folder="editors-xtd" id="quix">plg_editors_xtd_quix.zip</file>
        <file type="package" id="jmedia">pkg_jmedia.zip</file>
    </files>
</extension>
XML;

    public function testItReturnsEveryArchiveInManifestOrder(): void
    {
        $this->assertSame(
            [
                'com_quix.zip',
                'mod_quix_info.zip',
                'lib_quixnxt.zip',
                'plg_editors_xtd_quix.zip',
                'pkg_jmedia.zip',
            ],
            ExtensionList::fromManifest(self::MANIFEST)
        );
    }

    public function testItIgnoresUnreplacedReleasePlaceholders(): void
    {
        // scripts/release.sh substitutes tokens like ##QUIXNXT_SYSTEM_PLUGIN##.
        // A build that shipped one unreplaced must not become a filename.
        $xml = str_replace(
            '<file type="package" id="jmedia">pkg_jmedia.zip</file>',
            '<file type="plugin" id="quix">##QUIXNXT_SYSTEM_PLUGIN##</file>',
            self::MANIFEST
        );

        $this->assertNotContains('##QUIXNXT_SYSTEM_PLUGIN##', ExtensionList::fromManifest($xml));
    }

    public function testItRejectsFilenamesThatEscapeTheExtractionDirectory(): void
    {
        $xml = str_replace('com_quix.zip', '../../configuration.php', self::MANIFEST);

        $this->assertNotContains('../../configuration.php', ExtensionList::fromManifest($xml));
    }

    public function testItAcceptsOnlyZipArchives(): void
    {
        $xml = str_replace('com_quix.zip', 'com_quix.tar.gz', self::MANIFEST);

        $this->assertNotContains('com_quix.tar.gz', ExtensionList::fromManifest($xml));
    }

    public function testAManifestWithNoFilesYieldsAnEmptyList(): void
    {
        $xml = '<?xml version="1.0"?><extension type="package"><version>1.0</version></extension>';

        $this->assertSame([], ExtensionList::fromManifest($xml));
    }

    public function testInvalidXmlThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        ExtensionList::fromManifest('not xml at all');
    }
}
