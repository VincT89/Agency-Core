<?php

namespace Tests\Unit;

use App\Support\Uploads\LegacyExcelWorkbook;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyExcelFixture;

class LegacyExcelWorkbookTest extends TestCase
{
    public function test_recognizes_workbooks_in_regular_mini_and_extended_allocation_streams(): void
    {
        foreach ([[4096, 3, 0x0600], [64, 3, 0x0600], [64, 3, 0x0500], [8192, 4, 0x0600], [8 * 1024 * 1024, 3, 0x0600]] as [$size, $version, $biff]) {
            $this->assertTrue(LegacyExcelWorkbook::matches(LegacyExcelFixture::make($size, $version, $biff)));
        }
    }

    public function test_rejects_other_office_documents_and_an_embedded_or_fake_workbook(): void
    {
        $data = LegacyExcelFixture::make();
        // Fixture layout: FAT sector 0, directory sector 1, workbook sectors 2-9.
        $workbookEntry = 1024 + 384;
        $wordName = mb_convert_encoding("WordDocument\0", 'UTF-16LE', 'UTF-8');
        $word = substr_replace($data, str_pad($wordName, 64, "\0").pack('v', strlen($wordName)), $workbookEntry, 66);
        $this->assertFalse(LegacyExcelWorkbook::matches($word));
        $this->assertFalse(LegacyExcelWorkbook::matches(substr_replace($data, "<?php echo 'test'; ?>", 1536, 20)));
        $this->assertFalse(LegacyExcelWorkbook::matches(substr_replace($data, pack('v', 0x0010), 1542, 2)));
        // Keep the Workbook entry in the file, but make it unreachable from the root.
        $this->assertFalse(LegacyExcelWorkbook::matches(substr_replace($data, pack('V', 0xFFFFFFFF), 1024 + 76, 4)));
        $storage = substr_replace($data, chr(1), $workbookEntry + 66, 1);
        $this->assertFalse(LegacyExcelWorkbook::matches($storage));
    }

    public function test_rejects_broken_or_cyclic_containers_without_following_unbounded_chains(): void
    {
        $data = LegacyExcelFixture::make();
        $mutations = [
            [48, 0xFFFFFF00], // Directory outside the file.
            [512 + 4, 1], // Directory FAT loop.
            [512 + 8, 2], // Workbook FAT loop.
            [1024 + 384 + 68, 3], // Directory sibling loop.
            [1024 + 384 + 120, 8192], // Declared stream size exceeds the file.
            [72, 5000], // Oversized allocation metadata.
        ];
        foreach ($mutations as [$offset, $value]) {
            $this->assertFalse(LegacyExcelWorkbook::matches(substr_replace($data, pack('V', $value), $offset, 4)));
        }
        foreach (['', str_repeat('x', 1536), substr($data, 0, -1), substr($data, 0, 1536), str_pad($data, 15 * 1024 * 1024 + 1, "\0")] as $invalid) {
            $this->assertFalse(LegacyExcelWorkbook::matches($invalid));
        }
    }
}
