<?php

namespace Tests\Support;

/** Minimal synthetic CFB/BIFF structures; no customer workbook data. */
final class LegacyExcelFixture
{
    public static function make(int $size = 4096, int $version = 3, int $biff = 0x0600): string
    {
        $free = 0xFFFFFFFF;
        $end = 0xFFFFFFFE;
        $sectorSize = $version === 4 ? 4096 : 512;
        $entries = intdiv($sectorSize, 4);
        $mini = $size < 4096;
        $dataCount = (int) ceil($size / $sectorSize);
        $fatCount = 1;
        $difatCount = 0;
        do {
            $previous = [$fatCount, $difatCount];
            $fatCount = (int) ceil(($dataCount + 1 + (int) $mini + $fatCount + $difatCount) / $entries);
            $difatCount = (int) ceil(max(0, $fatCount - 109) / ($entries - 1));
        } while ($previous !== [$fatCount, $difatCount]);
        $directoryId = $fatCount;
        $difatId = $directoryId + 1;
        $miniFatId = $difatId + $difatCount;
        $firstData = $miniFatId + (int) $mini;
        $header = str_repeat("\0", $sectorSize);
        $header = substr_replace($header, hex2bin('d0cf11e0a1b11ae1'), 0, 8);
        foreach ([24 => 0x003E, 26 => $version, 28 => 0xFFFE, 30 => $version === 4 ? 12 : 9, 32 => 6] as $offset => $value) {
            $header = substr_replace($header, pack('v', $value), $offset, 2);
        }
        foreach ([40 => $version === 4 ? 1 : 0, 44 => $fatCount, 48 => $directoryId, 56 => 4096,
            60 => $mini ? $miniFatId : $end, 64 => (int) $mini, 68 => $difatCount ? $difatId : $end, 72 => $difatCount] as $offset => $value) {
            $header = substr_replace($header, pack('V', $value), $offset, 4);
        }
        for ($i = 0; $i < 109; $i++) {
            $header = substr_replace($header, pack('V', $i < $fatCount ? $i : $free), 76 + $i * 4, 4);
        }
        $fat = array_fill(0, $fatCount * $entries, $free);
        for ($i = 0; $i < $fatCount; $i++) {
            $fat[$i] = 0xFFFFFFFD;
        }
        $fat[$directoryId] = $end;
        $difats = '';
        for ($i = 0; $i < $difatCount; $i++) {
            $fat[$difatId + $i] = 0xFFFFFFFC;
            $ids = [];
            for ($j = 0; $j < $entries - 1; $j++) {
                $id = 109 + $i * ($entries - 1) + $j;
                $ids[] = $id < $fatCount ? $id : $free;
            }
            $ids[] = $i === $difatCount - 1 ? $end : $difatId + $i + 1;
            $difats .= pack('V*', ...$ids);
        }
        $miniFat = '';
        if ($mini) {
            $fat[$miniFatId] = $end;
            $miniIds = array_fill(0, $entries, $free);
            $count = (int) ceil($size / 64);
            for ($i = 0; $i < $count; $i++) {
                $miniIds[$i] = $i === $count - 1 ? $end : $i + 1;
            }
            $miniFat = pack('V*', ...$miniIds);
        }
        for ($i = 0; $i < $dataCount; $i++) {
            $fat[$firstData + $i] = $i === $dataCount - 1 ? $end : $firstData + $i + 1;
        }
        $root = self::entry('Root Entry', 5, 3, $mini ? $firstData : $end, $mini ? (int) ceil($size / 64) * 64 : 0);
        $workbook = self::entry($biff === 0x0500 ? 'Book' : 'Workbook', 2, $free, $mini ? 0 : $firstData, $size);
        $directory = str_pad($root.str_repeat("\0", 256).$workbook, $sectorSize, "\0");
        $bof = pack('vvvvvv', 0x0809, $biff === 0x0500 ? 8 : 16, $biff, 5, 0x0DBB, 1997);
        if ($biff === 0x0600) {
            $bof .= pack('VV', 0x41, 6);
        }
        $payload = str_pad($bof.pack('vv', 10, 0), $dataCount * $sectorSize, "\0");

        return $header.pack('V*', ...$fat).$directory.$difats.$miniFat.$payload;
    }

    private static function entry(string $name, int $type, int $child, int $first, int $size): string
    {
        $encoded = mb_convert_encoding($name."\0", 'UTF-16LE', 'UTF-8');
        $entry = str_pad($encoded, 64, "\0").pack('vCCVVV', strlen($encoded), $type, 1, 0xFFFFFFFF, 0xFFFFFFFF, $child);

        return str_pad($entry, 116, "\0").pack('VVV', $first, $size, 0);
    }
}
