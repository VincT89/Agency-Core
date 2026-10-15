<?php

namespace App\Support\Uploads;

use UnexpectedValueException;

/** Recognizes the CFB directory and BIFF workbook stream without evaluating cells or macros. */
final class LegacyExcelWorkbook
{
    private const END = 0xFFFFFFFE;

    private const FREE = 0xFFFFFFFF;

    private int $sectorSize;

    private int $sectorCount;

    private int $version;

    private array $fat = [];

    private function __construct(private readonly string $data) {}

    public static function matches(string $data): bool
    {
        // Bound work even when used outside the attachment validator.
        if (strlen($data) < 1536 || strlen($data) > 15 * 1024 * 1024) {
            return false;
        }

        try {
            return (new self($data))->hasWorkbook();
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    private function hasWorkbook(): bool
    {
        if (substr($this->data, 0, 8) !== hex2bin('d0cf11e0a1b11ae1')) {
            return false;
        }

        $this->version = $this->u16($this->data, 26);
        $shift = $this->u16($this->data, 30);
        if (! in_array([$this->version, $shift], [[3, 9], [4, 12]], true)
            || $this->u16($this->data, 28) !== 0xFFFE
            || $this->u16($this->data, 32) !== 6
            || $this->u32($this->data, 56) !== 4096) {
            return false;
        }

        $this->sectorSize = 1 << $shift;
        $this->sectorCount = intdiv(strlen($this->data), $this->sectorSize) - 1;
        if (strlen($this->data) % $this->sectorSize !== 0 || $this->sectorCount < 2) {
            return false;
        }

        $fatCount = $this->u32($this->data, 44);
        $difatCount = $this->u32($this->data, 72);
        if ($fatCount < 1 || $fatCount > $this->sectorCount || $difatCount > $this->sectorCount) {
            return false;
        }
        // A small malformed container must not expand into an unbounded allocation table.
        $entriesPerSector = intdiv($this->sectorSize, 4);
        if ($difatCount !== (int) ceil(max(0, $fatCount - 109) / ($entriesPerSector - 1))) {
            return false;
        }

        $fatIds = array_values(unpack('V*', substr($this->data, 76, 436)));
        $difatSector = $this->u32($this->data, 68);
        $seen = [];
        for ($i = 0; $i < $difatCount; $i++) {
            if (isset($seen[$difatSector])) {
                return false;
            }
            $seen[$difatSector] = true;
            $sector = $this->sector($difatSector);
            array_push($fatIds, ...array_values(unpack('V*', substr($sector, 0, -4))));
            $difatSector = $this->u32($sector, $this->sectorSize - 4);
        }
        if ($difatCount > 0 && $difatSector !== self::END) {
            return false;
        }
        $fatIds = array_values(array_filter($fatIds, fn ($id) => $id !== self::FREE));
        if (count($fatIds) !== $fatCount || count(array_unique($fatIds)) !== $fatCount) {
            return false;
        }
        foreach ($fatIds as $id) {
            $sector = $this->sector($id);
            $remaining = $this->sectorCount - count($this->fat);
            if ($remaining > 0) {
                array_push($this->fat, ...array_values(unpack('V*', substr($sector, 0, min($remaining, $entriesPerSector) * 4))));
            }
        }

        $directory = $this->readSectors($this->u32($this->data, 48));
        if (strlen($directory) < 128 || ord($directory[66]) !== 5) {
            return false;
        }
        $root = substr($directory, 0, 128);
        $pending = [$this->u32($root, 76)];
        $seen = [];
        $workbook = null;
        // Inspect only root-level streams, not Excel objects embedded in other documents.
        while ($pending) {
            $id = array_pop($pending);
            if ($id === self::FREE) {
                continue;
            }
            if ($id === 0 || $id >= intdiv(strlen($directory), 128) || isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            $entry = substr($directory, $id * 128, 128);
            array_push($pending, $this->u32($entry, 68), $this->u32($entry, 72));
            $nameLength = $this->u16($entry, 64);
            if ($nameLength < 2 || $nameLength > 64 || $nameLength % 2 !== 0
                || substr($entry, $nameLength - 2, 2) !== "\0\0") {
                return false;
            }
            $name = mb_convert_encoding(substr($entry, 0, $nameLength - 2), 'UTF-8', 'UTF-16LE');
            if (ord($entry[66]) === 2 && in_array(strtolower($name), ['workbook', 'book'], true)) {
                if ($workbook !== null) {
                    return false;
                }
                $workbook = $entry;
            }
        }
        if ($workbook === null) {
            return false;
        }

        $size = $this->streamSize($workbook);
        if ($size < 12) {
            return false;
        }
        $first = $this->u32($workbook, 116);
        if ($size >= 4096) {
            $contents = $this->readSectors($first, $size);
        } else {
            $miniFatCount = $this->u32($this->data, 64);
            if ($miniFatCount < 1 || $miniFatCount > $this->sectorCount) {
                return false;
            }
            $miniFat = array_values(unpack('V*', $this->readSectors($this->u32($this->data, 60), $miniFatCount * $this->sectorSize)));
            $miniStream = $this->readSectors($this->u32($root, 116), $this->streamSize($root));
            $chain = $this->chain($first, $miniFat, intdiv(strlen($miniStream), 64));
            if (count($chain) !== (int) ceil($size / 64)) {
                return false;
            }
            $contents = substr(implode('', array_map(fn ($id) => substr($miniStream, $id * 64, 64), $chain)), 0, $size);
        }

        // MS-XLS: a BIFF5/BIFF8 BOF for workbook globals, not a worksheet or arbitrary OLE payload.
        $biff = $this->u16($contents, 4);

        return $this->u16($contents, 0) === 0x0809
            && in_array([$biff, $this->u16($contents, 2)], [[0x0500, 8], [0x0600, 16]], true)
            && strlen($contents) >= $this->u16($contents, 2) + 4
            && $this->u16($contents, 6) === 0x0005;
    }

    private function readSectors(int $first, ?int $size = null): string
    {
        $chain = $this->chain($first, $this->fat, $this->sectorCount);
        if ($size !== null && count($chain) !== (int) ceil($size / $this->sectorSize)) {
            throw new UnexpectedValueException('Invalid stream size.');
        }
        $data = implode('', array_map($this->sector(...), $chain));

        return $size === null ? $data : substr($data, 0, $size);
    }

    private function chain(int $first, array $fat, int $limit): array
    {
        $ids = [];
        $seen = [];
        for ($id = $first; $id !== self::END; $id = $fat[$id]) {
            if ($id < 0 || $id >= $limit || ! isset($fat[$id]) || isset($seen[$id])) {
                throw new UnexpectedValueException('Invalid sector chain.');
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    private function sector(int $id): string
    {
        if ($id < 0 || $id >= $this->sectorCount) {
            throw new UnexpectedValueException('Invalid sector.');
        }

        return substr($this->data, ($id + 1) * $this->sectorSize, $this->sectorSize);
    }

    private function streamSize(string $entry): int
    {
        $size = $this->u32($entry, 120);
        if (($this->version === 4 && $this->u32($entry, 124) !== 0) || $size > strlen($this->data)) {
            throw new UnexpectedValueException('Invalid stream size.');
        }

        return $size;
    }

    private function u16(string $data, int $offset): int
    {
        return unpack('v', substr($data, $offset, 2))[1];
    }

    private function u32(string $data, int $offset): int
    {
        return unpack('V', substr($data, $offset, 4))[1];
    }
}
