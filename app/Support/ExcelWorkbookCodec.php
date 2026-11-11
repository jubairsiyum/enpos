<?php

namespace App\Support;

use RuntimeException;

/**
 * Pure-PHP codec for reading and writing genuine binary Excel (.xls BIFF8 OLE2)
 * and reading .xlsx (OOXML ZIP) workbooks without external PHP extensions.
 */
class ExcelWorkbookCodec
{
    private const OLE2_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const ZIP_MAGIC = "PK\x03\x04";
    private const FREE_SECT = 0xFFFFFFFF;
    private const END_OF_CHAIN = 0xFFFFFFFE;
    private const FAT_SECT = 0xFFFFFFFD;

    /**
     * Build a genuine MS-CFB (OLE2) + BIFF8 binary Excel (.xls) file.
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function buildXls(array $rows, string $sheetName = 'ImportContacts'): string
    {
        $biffStream = $this->buildBiff8Workbook($rows, $sheetName);

        return $this->wrapInOle2Container($biffStream);
    }

    /**
     * Determine whether binary content is a genuine OLE2 .xls file.
     */
    public function isBinaryXls(string $contents): bool
    {
        return str_starts_with($contents, self::OLE2_MAGIC);
    }

    /**
     * Determine whether binary content is an OOXML .xlsx (ZIP) file.
     */
    public function isXlsx(string $contents): bool
    {
        return str_starts_with($contents, self::ZIP_MAGIC);
    }

    /**
     * Parse rows from a binary OLE2 BIFF5/BIFF8 .xls file.
     *
     * @return array<int, array<int, string|null>>
     */
    public function parseBinaryXls(string $contents): array
    {
        $workbookStream = $this->isBinaryXls($contents)
            ? $this->extractOle2WorkbookStream($contents)
            : $contents;

        return $this->parseBiffRecords($workbookStream);
    }

    /**
     * Parse rows from an OOXML .xlsx file using PHP's built-in zlib/SimpleXML.
     *
     * @return array<int, array<int, string|null>>
     */
    public function parseXlsx(string $contents): array
    {
        $entries = $this->extractZipEntries($contents);
        $sharedStrings = $this->parseXlsxSharedStrings($entries['xl/sharedStrings.xml'] ?? null);
        $sheetXml = $this->findFirstXlsxWorksheet($entries);

        if ($sheetXml === null) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sheetXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('Unable to parse the worksheet inside the .xlsx archive.');
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $xml->registerXPathNamespace('m', $ns);
        $rowNodes = $xml->xpath('//m:sheetData/m:row') ?: $xml->xpath('//sheetData/row');

        if (! is_array($rowNodes)) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $row = [];
            $colFallback = 0;

            $cells = $rowNode->children($ns)->c;
            if (count($cells) === 0) {
                $cells = $rowNode->c;
            }

            foreach ($cells as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colIndex = $ref !== '' ? $this->columnLettersToIndex($ref) : $colFallback;
                $colFallback = $colIndex + 1;

                $type = (string) ($cell['t'] ?? '');
                $valueNode = $cell->children($ns)->v ?? $cell->v;
                $rawValue = (string) $valueNode;

                if ($type === 's') {
                    $idx = (int) $rawValue;
                    $row[$colIndex] = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $isNode = $cell->children($ns)->is ?? $cell->is;
                    $row[$colIndex] = $this->collectXmlText($isNode);
                } else {
                    $row[$colIndex] = trim($rawValue);
                }
            }

            if (! empty($row)) {
                $maxCol = max(array_keys($row));
                $normalized = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $normalized[] = $row[$c] ?? '';
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function buildBiff8Workbook(array $rows, string $sheetName): string
    {
        $strings = [];
        $stringIndexMap = [];
        $totalStringRefs = 0;

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell === null || $cell === '') {
                    continue;
                }
                $text = (string) $cell;
                $totalStringRefs++;
                if (! array_key_exists($text, $stringIndexMap)) {
                    $stringIndexMap[$text] = count($strings);
                    $strings[] = $text;
                }
            }
        }

        $globalsBeforeBoundsheet = '';
        // BOF (0x0809) - BIFF8, Workbook Globals (0x0005)
        $globalsBeforeBoundsheet .= $this->biffRecord(0x0809, pack('vvvvVV', 0x0600, 0x0005, 0x0DBB, 0x07CC, 0, 0x0600));
        // CODEPAGE (0x0042) - UTF-16LE (1200)
        $globalsBeforeBoundsheet .= $this->biffRecord(0x0042, pack('v', 1200));
        // WINDOW1 (0x003D)
        $globalsBeforeBoundsheet .= $this->biffRecord(0x003D, pack('vvvvvvvvv', 0, 0, 0x2000, 0x1500, 0x0038, 0, 0, 1, 0x0258));
        // FONT (0x0031) - default Arial 10pt
        $globalsBeforeBoundsheet .= $this->biffRecord(0x0031, pack('vvvvvCCCCC', 200, 0, 0x7FFF, 400, 0, 0, 0, 0, 0, 0) . $this->encodeShortUnicodeString('Arial'));
        // XF 0 (Style XF) & XF 1 (Default Cell XF)
        $globalsBeforeBoundsheet .= $this->biffRecord(0x00E0, pack('vvvVVv', 0, 0, 0xFFF5, 0x20, 0, 0x20C0));
        $globalsBeforeBoundsheet .= $this->biffRecord(0x00E0, pack('vvvVVv', 0, 0, 0x0001, 0x20, 0, 0x20C0));
        // STYLE (0x0293) - Normal built-in style pointing to XF 0
        $globalsBeforeBoundsheet .= $this->biffRecord(0x0293, pack('vCC', 0x8000, 0x00, 0xFF));

        $sstRecords = $this->buildSstRecords($totalStringRefs, $strings);
        $eofRecord = $this->biffRecord(0x000A, '');

        $sheetNameEncoded = $this->encodeShortUnicodeString($sheetName);
        $boundsheetHeaderLen = 4 + 6 + strlen($sheetNameEncoded); // 4B record header + 6B fixed + name

        $worksheetOffset = strlen($globalsBeforeBoundsheet) + $boundsheetHeaderLen + strlen($sstRecords) + strlen($eofRecord);

        $boundsheetRecord = $this->biffRecord(
            0x0085,
            pack('VCC', $worksheetOffset, 0, 0) . $sheetNameEncoded
        );

        $worksheet = $this->buildBiff8Worksheet($rows, $stringIndexMap);

        return $globalsBeforeBoundsheet . $boundsheetRecord . $sstRecords . $eofRecord . $worksheet;
    }

    /**
     * @param  array<int, string>  $strings
     */
    private function buildSstRecords(int $totalRefs, array $strings): string
    {
        $payload = pack('VV', $totalRefs, count($strings));
        foreach ($strings as $text) {
            $payload .= $this->encodeLongUnicodeString($text);
        }

        // Split into 8224-byte BIFF8 record chunks if ever exceeded (template headers are tiny)
        $maxPayload = 8220;
        if (strlen($payload) <= $maxPayload) {
            return $this->biffRecord(0x00FC, $payload);
        }

        $out = $this->biffRecord(0x00FC, substr($payload, 0, $maxPayload));
        $offset = $maxPayload;
        while ($offset < strlen($payload)) {
            $chunk = substr($payload, $offset, $maxPayload);
            $out .= $this->biffRecord(0x003C, $chunk);
            $offset += strlen($chunk);
        }

        return $out;
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<string, int>  $stringIndexMap
     */
    private function buildBiff8Worksheet(array $rows, array $stringIndexMap): string
    {
        $rowCount = count($rows);
        $maxCols = 0;
        foreach ($rows as $row) {
            $maxCols = max($maxCols, count($row));
        }

        // BOF (0x0809) - Worksheet (0x0010)
        $sheet = $this->biffRecord(0x0809, pack('vvvvVV', 0x0600, 0x0010, 0x0DBB, 0x07CC, 0, 0x0600));
        // DIMENSIONS (0x0200)
        $sheet .= $this->biffRecord(0x0200, pack('VVvvv', 0, $rowCount, 0, $maxCols, 0));

        foreach ($rows as $rowIdx => $row) {
            foreach (array_values($row) as $colIdx => $cell) {
                if ($cell === null || $cell === '') {
                    continue;
                }
                $sstIdx = $stringIndexMap[(string) $cell];
                // LABELSST (0x00FD): row, col, xfIndex=1, sstIndex
                $sheet .= $this->biffRecord(0x00FD, pack('vvvV', $rowIdx, $colIdx, 1, $sstIdx));
            }
        }

        // WINDOW2 (0x023E)
        $sheet .= $this->biffRecord(0x023E, pack('vvvvvvvvv', 0x06B6, 0, 0, 0x0040, 0, 0, 0, 0, 0));
        // EOF (0x000A)
        $sheet .= $this->biffRecord(0x000A, '');

        return $sheet;
    }

    private function wrapInOle2Container(string $workbookStream): string
    {
        $streamSize = strlen($workbookStream);
        // Keep stream >= 4096 bytes so it stays entirely in regular FAT sectors (no MiniFAT needed)
        $paddedSize = max(4096, (int) ceil($streamSize / 512) * 512);
        $paddedStream = str_pad($workbookStream, $paddedSize, "\x00");
        $streamSectors = intdiv($paddedSize, 512);

        $fatSector = $streamSectors;
        $dirSector = $streamSectors + 1;

        // 512-byte CFBF Header
        $difatEntries = pack('V', $fatSector) . str_repeat(pack('V', self::FREE_SECT), 108);
        $header = self::OLE2_MAGIC
            . str_repeat("\x00", 16)
            . pack('vvvvvv', 0x003E, 0x0003, 0xFFFE, 9, 6, 0)
            . str_repeat("\x00", 4)
            . pack('VVVV', 0, 1, $dirSector, 0)
            . pack('VVVVV', 4096, self::END_OF_CHAIN, 0, self::END_OF_CHAIN, 0)
            . $difatEntries;

        // Single 512-byte FAT sector (handles up to 128 total sectors = 64 KB)
        $fatEntries = [];
        for ($i = 0; $i < $streamSectors - 1; $i++) {
            $fatEntries[] = $i + 1;
        }
        $fatEntries[] = self::END_OF_CHAIN; // End of Workbook chain
        $fatEntries[] = self::FAT_SECT;     // FAT sector itself
        $fatEntries[] = self::END_OF_CHAIN; // Directory sector
        while (count($fatEntries) < 128) {
            $fatEntries[] = self::FREE_SECT;
        }
        $fatData = pack('V*', ...$fatEntries);

        // Single 512-byte Directory sector (4 x 128-byte entries)
        $dirData = $this->buildOle2DirectoryEntry('Root Entry', 5, 1, self::FREE_SECT, self::FREE_SECT, 1, self::END_OF_CHAIN, 0)
            . $this->buildOle2DirectoryEntry('Workbook', 2, 1, self::FREE_SECT, self::FREE_SECT, self::FREE_SECT, 0, $streamSize)
            . str_repeat($this->buildEmptyOle2DirectoryEntry(), 2);

        return $header . $paddedStream . $fatData . $dirData;
    }

    private function buildOle2DirectoryEntry(
        string $name,
        int $type,
        int $color,
        int $leftSibling,
        int $rightSibling,
        int $childId,
        int $startSector,
        int $streamSize,
    ): string {
        $utf16 = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8') . "\x00\x00";
        $nameByteLen = strlen($utf16);
        $nameField = str_pad(substr($utf16, 0, 64), 64, "\x00");

        return $nameField
            . pack('vCCVVV', $nameByteLen, $type, $color, $leftSibling, $rightSibling, $childId)
            . str_repeat("\x00", 16) // CLSID
            . str_repeat("\x00", 20) // State bits + creation/modified timestamps
            . pack('VVV', $startSector, $streamSize, 0);
    }

    private function buildEmptyOle2DirectoryEntry(): string
    {
        return str_repeat("\x00", 66)
            . pack('CCVVV', 0, 0, self::FREE_SECT, self::FREE_SECT, self::FREE_SECT)
            . str_repeat("\x00", 48);
    }

    private function biffRecord(int $type, string $payload): string
    {
        return pack('vv', $type, strlen($payload)) . $payload;
    }

    private function encodeShortUnicodeString(string $text): string
    {
        if (mb_check_encoding($text, 'ASCII')) {
            return pack('CC', strlen($text), 0) . $text;
        }

        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $charCount = intdiv(strlen($utf16), 2);

        return pack('CC', $charCount, 1) . $utf16;
    }

    private function encodeLongUnicodeString(string $text): string
    {
        if (mb_check_encoding($text, 'ASCII')) {
            return pack('vC', strlen($text), 0) . $text;
        }

        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $charCount = intdiv(strlen($utf16), 2);

        return pack('vC', $charCount, 1) . $utf16;
    }

    /**
     * Extract the Workbook (or Book) stream from an OLE2 CFBF binary container.
     */
    private function extractOle2WorkbookStream(string $data): string
    {
        if (strlen($data) < 512) {
            throw new RuntimeException('Invalid .xls file header.');
        }

        $sectorShift = unpack('v', substr($data, 30, 2))[1];
        $miniSectorShift = unpack('v', substr($data, 32, 2))[1];
        $sectorSize = 1 << $sectorShift;
        $miniSectorSize = 1 << $miniSectorShift;

        $firstDirSector = unpack('V', substr($data, 48, 4))[1];
        $miniCutoff = unpack('V', substr($data, 56, 4))[1];
        $firstMiniFatSector = unpack('V', substr($data, 60, 4))[1];
        $firstDifatSector = unpack('V', substr($data, 68, 4))[1];

        // Collect all FAT sector locations from header DIFAT (109 entries) + extra DIFAT chain
        $fatSectorIds = array_values(unpack('V109', substr($data, 76, 436)));
        $difatNext = $firstDifatSector;
        $visited = [];
        while ($difatNext < 0xFFFFFFFA && ! isset($visited[$difatNext])) {
            $visited[$difatNext] = true;
            $sector = $this->readSector($data, $difatNext, $sectorSize);
            $count = intdiv($sectorSize, 4) - 1;
            $entries = array_values(unpack('V' . ($count + 1), $sector));
            for ($i = 0; $i < $count; $i++) {
                $fatSectorIds[] = $entries[$i];
            }
            $difatNext = $entries[$count];
        }

        $fat = [];
        foreach ($fatSectorIds as $fatId) {
            if ($fatId >= 0xFFFFFFFA) {
                continue;
            }
            $sector = $this->readSector($data, $fatId, $sectorSize);
            if ($sector !== '') {
                $fat = array_merge($fat, array_values(unpack('V*', $sector)));
            }
        }

        $dirBytes = $this->readChain($data, $firstDirSector, $fat, $sectorSize);
        $entryCount = intdiv(strlen($dirBytes), 128);

        $rootStartSector = self::END_OF_CHAIN;
        $rootSize = 0;
        $workbookEntry = null;

        for ($i = 0; $i < $entryCount; $i++) {
            $entry = substr($dirBytes, $i * 128, 128);
            $nameLen = unpack('v', substr($entry, 64, 2))[1];
            $type = ord($entry[66]);
            if ($type === 0 || $nameLen < 2) {
                continue;
            }

            $rawName = substr($entry, 0, min(64, max(0, $nameLen - 2)));
            $name = mb_convert_encoding($rawName, 'UTF-8', 'UTF-16LE');
            $startSector = unpack('V', substr($entry, 116, 4))[1];
            $size = unpack('V', substr($entry, 120, 4))[1];

            if ($type === 5) {
                $rootStartSector = $startSector;
                $rootSize = $size;
            } elseif ($type === 2 && in_array(strtolower($name), ['workbook', 'book'], true)) {
                $workbookEntry = ['start' => $startSector, 'size' => $size];
                break;
            }
        }

        if ($workbookEntry === null) {
            throw new RuntimeException('Unable to locate the Workbook stream in the .xls file.');
        }

        if ($workbookEntry['size'] >= $miniCutoff || $firstMiniFatSector >= 0xFFFFFFFA || $rootStartSector >= 0xFFFFFFFA) {
            return substr($this->readChain($data, $workbookEntry['start'], $fat, $sectorSize), 0, $workbookEntry['size']);
        }

        // Handle Excel files saved with small Workbook streams inside the MiniStream
        $miniStream = substr($this->readChain($data, $rootStartSector, $fat, $sectorSize), 0, $rootSize);
        $miniFatBytes = $this->readChain($data, $firstMiniFatSector, $fat, $sectorSize);
        $miniFat = array_values(unpack('V*', $miniFatBytes));

        $out = '';
        $sectorId = $workbookEntry['start'];
        $guard = 0;
        while ($sectorId < 0xFFFFFFFA && $guard < 100000) {
            $out .= substr($miniStream, $sectorId * $miniSectorSize, $miniSectorSize);
            $sectorId = $miniFat[$sectorId] ?? self::END_OF_CHAIN;
            $guard++;
        }

        return substr($out, 0, $workbookEntry['size']);
    }

    private function readSector(string $data, int $sectorId, int $sectorSize): string
    {
        $offset = ($sectorId + 1) * $sectorSize;
        if ($offset < 0 || $offset >= strlen($data)) {
            return '';
        }

        return substr($data, $offset, $sectorSize);
    }

    /**
     * @param  array<int, int>  $fat
     */
    private function readChain(string $data, int $startSector, array $fat, int $sectorSize): string
    {
        $out = '';
        $sectorId = $startSector;
        $visited = [];

        while ($sectorId < 0xFFFFFFFA && ! isset($visited[$sectorId])) {
            $visited[$sectorId] = true;
            $out .= $this->readSector($data, $sectorId, $sectorSize);
            $sectorId = $fat[$sectorId] ?? self::END_OF_CHAIN;
        }

        return $out;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseBiffRecords(string $stream): array
    {
        $len = strlen($stream);
        $pos = 0;
        $biffVersion = 0x0600;
        $codepage = 'Windows-1252';
        $sst = [];
        $rows = [];
        $inFirstWorksheet = false;
        $lastFormulaRow = null;
        $lastFormulaCol = null;

        while ($pos + 4 <= $len) {
            $header = unpack('vtype/vsize', substr($stream, $pos, 4));
            $type = $header['type'];
            $size = $header['size'];
            $pos += 4;
            $payload = substr($stream, $pos, $size);
            $pos += $size;

            // Concatenate immediate CONTINUE (0x003C) records for SST or large records
            while ($type === 0x00FC && $pos + 4 <= $len) {
                $next = unpack('vtype/vsize', substr($stream, $pos, 4));
                if ($next['type'] !== 0x003C) {
                    break;
                }
                $pos += 4;
                $payload .= substr($stream, $pos, $next['size']);
                $pos += $next['size'];
            }

            if ($type === 0x0809 && strlen($payload) >= 4) { // BOF
                $bof = unpack('vvers/vdt', substr($payload, 0, 4));
                $biffVersion = $bof['vers'];
                if ($bof['dt'] === 0x0010) {
                    if (! empty($rows)) {
                        break; // Only read the first worksheet
                    }
                    $inFirstWorksheet = true;
                }
                continue;
            }

            if ($type === 0x0042 && strlen($payload) >= 2) { // CODEPAGE
                $cp = unpack('vcp', substr($payload, 0, 2))['cp'];
                $codepage = match ($cp) {
                    1200  => 'UTF-16LE',
                    65001 => 'UTF-8',
                    default => 'Windows-1252',
                };
                continue;
            }

            if ($type === 0x00FC) { // SST
                $sst = $this->parseSst($payload);
                continue;
            }

            if (! $inFirstWorksheet) {
                continue;
            }

            if ($type === 0x000A) { // EOF of first worksheet
                break;
            }

            if ($type === 0x00FD && strlen($payload) >= 10) { // LABELSST
                $rec = unpack('vrow/vcol/vxf/Vsst', substr($payload, 0, 10));
                $rows[$rec['row']][$rec['col']] = $sst[$rec['sst']] ?? '';
            } elseif ($type === 0x0204 && strlen($payload) >= 8) { // LABEL
                $rec = unpack('vrow/vcol/vxf', substr($payload, 0, 6));
                $rows[$rec['row']][$rec['col']] = $this->decodeBiffString(substr($payload, 6), $biffVersion, $codepage);
            } elseif ($type === 0x0203 && strlen($payload) >= 14) { // NUMBER
                $rec = unpack('vrow/vcol/vxf', substr($payload, 0, 6));
                $num = unpack('d', substr($payload, 6, 8))[1];
                $rows[$rec['row']][$rec['col']] = $this->formatExcelNumber($num);
            } elseif ($type === 0x027E && strlen($payload) >= 10) { // RK
                $rec = unpack('vrow/vcol/vxf/Vrk', substr($payload, 0, 10));
                $rows[$rec['row']][$rec['col']] = $this->formatExcelNumber($this->decodeRk($rec['rk']));
            } elseif ($type === 0x00BD && strlen($payload) >= 6) { // MULRK
                $rec = unpack('vrow/vcolFirst', substr($payload, 0, 4));
                $col = $rec['colFirst'];
                $rkOffset = 4;
                while ($rkOffset + 6 <= strlen($payload) - 2) {
                    $rk = unpack('V', substr($payload, $rkOffset + 2, 4))[1];
                    $rows[$rec['row']][$col] = $this->formatExcelNumber($this->decodeRk($rk));
                    $col++;
                    $rkOffset += 6;
                }
            } elseif ($type === 0x0006 && strlen($payload) >= 14) { // FORMULA cached value
                $rec = unpack('vrow/vcol/vxf', substr($payload, 0, 6));
                $valBytes = substr($payload, 6, 8);
                if (substr($valBytes, 6, 2) === "\xFF\xFF") {
                    if (ord($valBytes[0]) === 0) {
                        $lastFormulaRow = $rec['row'];
                        $lastFormulaCol = $rec['col'];
                    }
                } else {
                    $num = unpack('d', $valBytes)[1];
                    $rows[$rec['row']][$rec['col']] = $this->formatExcelNumber($num);
                }
            } elseif ($type === 0x0207 && $lastFormulaRow !== null && $lastFormulaCol !== null) { // STRING (formula result)
                $rows[$lastFormulaRow][$lastFormulaCol] = $this->decodeBiffString($payload, $biffVersion, $codepage);
                $lastFormulaRow = null;
                $lastFormulaCol = null;
            }
        }

        if (empty($rows)) {
            return [];
        }

        ksort($rows);
        $normalized = [];
        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }
            $maxCol = max(array_keys($row));
            $line = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $line[] = $row[$c] ?? '';
            }
            $normalized[] = $line;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function parseSst(string $payload): array
    {
        if (strlen($payload) < 8) {
            return [];
        }

        $counts = unpack('Vtotal/Vunique', substr($payload, 0, 8));
        $unique = $counts['unique'];
        $offset = 8;
        $totalLen = strlen($payload);
        $strings = [];

        for ($i = 0; $i < $unique && $offset + 3 <= $totalLen; $i++) {
            $cch = unpack('v', substr($payload, $offset, 2))[1];
            $flags = ord($payload[$offset + 2]);
            $offset += 3;

            $isUnicode = ($flags & 0x01) !== 0;
            $hasAsianPhonetic = ($flags & 0x04) !== 0;
            $hasRichText = ($flags & 0x08) !== 0;

            $rtRuns = 0;
            if ($hasRichText && $offset + 2 <= $totalLen) {
                $rtRuns = unpack('v', substr($payload, $offset, 2))[1];
                $offset += 2;
            }

            $asianSize = 0;
            if ($hasAsianPhonetic && $offset + 4 <= $totalLen) {
                $asianSize = unpack('V', substr($payload, $offset, 4))[1];
                $offset += 4;
            }

            $byteLen = $isUnicode ? $cch * 2 : $cch;
            $raw = substr($payload, $offset, $byteLen);
            $offset += $byteLen;
            $offset += ($rtRuns * 4) + $asianSize;

            $strings[] = $isUnicode
                ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE')
                : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $strings;
    }

    private function decodeBiffString(string $data, int $biffVersion, string $codepage): string
    {
        if ($biffVersion >= 0x0600 && strlen($data) >= 3) {
            $cch = unpack('v', substr($data, 0, 2))[1];
            $flags = ord($data[2]);
            $isUnicode = ($flags & 0x01) !== 0;
            $raw = substr($data, 3, $isUnicode ? $cch * 2 : $cch);

            return $isUnicode
                ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE')
                : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        if (strlen($data) >= 2) {
            $cch = unpack('v', substr($data, 0, 2))[1];
            $raw = substr($data, 2, $cch);

            return mb_convert_encoding($raw, 'UTF-8', $codepage);
        }

        return '';
    }

    private function decodeRk(int $rk): float
    {
        $isX100 = ($rk & 0x01) !== 0;
        $isInt = ($rk & 0x02) !== 0;

        if ($isInt) {
            // 30-bit signed integer stored in bits 2..31
            $signed = $rk & 0x80000000 ? ($rk | (-1 << 32)) : $rk;
            $value = (float) ($signed >> 2);
        } else {
            $value = unpack('d', pack('VV', 0, $rk & 0xFFFFFFFC))[1];
        }

        return $isX100 ? $value / 100.0 : $value;
    }

    private function formatExcelNumber(float $value): string
    {
        if (abs($value - round($value)) < 1e-9) {
            return sprintf('%.0f', round($value));
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * Extract uncompressed files from a standard ZIP archive using built-in zlib.
     *
     * @return array<string, string>
     */
    private function extractZipEntries(string $binary): array
    {
        $eocdPos = strrpos($binary, "PK\x05\x06");
        if ($eocdPos === false || strlen($binary) < $eocdPos + 22) {
            throw new RuntimeException('Invalid .xlsx ZIP archive.');
        }

        $eocd = unpack('vtotal/VcdSize/VcdOffset', substr($binary, $eocdPos + 10, 10));
        $cdOffset = $eocd['cdOffset'];
        $cdEnd = $cdOffset + $eocd['cdSize'];

        $entries = [];
        $pos = $cdOffset;
        while ($pos < $cdEnd && substr($binary, $pos, 4) === "PK\x01\x02") {
            $hdr = unpack(
                'vmethod/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen',
                substr($binary, $pos + 10, 22)
            );
            $localOffset = unpack('V', substr($binary, $pos + 42, 4))[1];
            $name = substr($binary, $pos + 46, $hdr['nameLen']);
            $pos += 46 + $hdr['nameLen'] + $hdr['extraLen'] + $hdr['commentLen'];

            if (substr($binary, $localOffset, 4) !== "PK\x03\x04") {
                continue;
            }

            $localMeta = unpack('vnameLen/vextraLen', substr($binary, $localOffset + 26, 4));
            $dataStart = $localOffset + 30 + $localMeta['nameLen'] + $localMeta['extraLen'];
            $compressed = substr($binary, $dataStart, $hdr['compSize']);

            if ($hdr['method'] === 0) {
                $entries[$name] = $compressed;
            } elseif ($hdr['method'] === 8) {
                $inflated = @gzinflate($compressed);
                if ($inflated !== false) {
                    $entries[$name] = $inflated;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function parseXlsxSharedStrings(?string $xmlContent): array
    {
        if ($xmlContent === null || $xmlContent === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $items = $xml->children($ns)->si;
        if (count($items) === 0) {
            $items = $xml->si;
        }

        $strings = [];
        foreach ($items as $si) {
            $strings[] = $this->collectXmlText($si);
        }

        return $strings;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function findFirstXlsxWorksheet(array $entries): ?string
    {
        if (isset($entries['xl/worksheets/sheet1.xml'])) {
            return $entries['xl/worksheets/sheet1.xml'];
        }

        foreach ($entries as $name => $content) {
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                return $content;
            }
        }

        return null;
    }

    private function collectXmlText(\SimpleXMLElement $node): string
    {
        $dom = dom_import_simplexml($node);

        return $dom !== false ? trim($dom->textContent) : trim((string) $node);
    }

    private function columnLettersToIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return 0;
        }

        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
