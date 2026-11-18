<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class AttendanceReportController extends Controller
{
    public function __invoke(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json($this->reportData($request->validated()));
    }

    public function exportExcel(AttendanceReportRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        if ($validated['type'] !== 'monthly') {
            throw ValidationException::withMessages([
                'type' => 'Unduhan Excel hanya tersedia untuk rekap bulanan.',
            ]);
        }

        $report = $this->reportData($validated);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'kanu-attendance-');
        if ($temporaryFile === false) {
            throw new RuntimeException('File rekap Excel tidak dapat disiapkan.');
        }

        try {
            $this->writeExcelWorkbook($temporaryFile, $report);
        } catch (\Throwable $exception) {
            @unlink($temporaryFile);
            throw $exception;
        }

        return response()->download(
            $temporaryFile,
            'rekap-kehadiran-'.$validated['period'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    /** @param array{type: string, period: string, search?: string|null} $validated */
    private function reportData(array $validated): array
    {
        $period = $validated['type'] === 'weekly'
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['period'])
            : CarbonImmutable::createFromFormat('!Y-m', $validated['period']);

        $periodStart = $validated['type'] === 'weekly'
            ? $period->startOfWeek(Carbon::MONDAY)
            : $period->startOfMonth();
        $periodEnd = $validated['type'] === 'weekly'
            ? $periodStart->endOfWeek(Carbon::SUNDAY)
            : $periodStart->endOfMonth();

        $sessionIds = TrainingSession::query()
            ->whereDate('training_date', '>=', $periodStart->toDateString())
            ->whereDate('training_date', '<=', $periodEnd->toDateString())
            ->pluck('id');
        $sessionCount = $sessionIds->count();

        $attendanceCounts = Attendance::query()
            ->selectRaw('member_id, COUNT(*) as attendance_count')
            ->whereIn('training_session_id', $sessionIds)
            ->groupBy('member_id')
            ->pluck('attendance_count', 'member_id');

        $search = mb_strtolower(trim($validated['search'] ?? ''));

        $members = Member::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(function (Member $member) use ($attendanceCounts, $sessionCount): array {
                $presentCount = (int) ($attendanceCounts[$member->id] ?? 0);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'status' => $member->status,
                    'attendance_count' => $presentCount,
                    'session_count' => $sessionCount,
                    'attendance_percentage' => $sessionCount > 0
                        ? round(($presentCount / $sessionCount) * 100, 1)
                        : null,
                ];
            });

        return [
            'type' => $validated['type'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'session_count' => $sessionCount,
            'members' => $members,
        ];
    }

    /** @param array{type: string, period_start: string, period_end: string, session_count: int, members: iterable<array{id: int, name: string, status: string, attendance_count: int, session_count: int, attendance_percentage: float|null}>} $report */
    private function writeExcelWorkbook(string $path, array $report): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
        }

        $rows = [
            [
                ['value' => 'REKAP KEHADIRAN BULANAN', 'style' => 1],
            ],
            [
                ['value' => 'Periode', 'style' => 2],
                ['value' => $this->formatPeriod($report['period_start'], $report['period_end'])],
            ],
            [
                ['value' => 'Jumlah sesi latihan', 'style' => 2],
                ['value' => $report['session_count'], 'numeric' => true],
            ],
            [],
            [
                ['value' => 'No.', 'style' => 3],
                ['value' => 'Nama anggota', 'style' => 3],
                ['value' => 'Jumlah hadir', 'style' => 3],
                ['value' => 'Jumlah sesi', 'style' => 3],
                ['value' => 'Persentase kehadiran', 'style' => 3],
                ['value' => 'Status anggota', 'style' => 3],
            ],
        ];

        foreach ($report['members'] as $index => $member) {
            $rows[] = [
                ['value' => $index + 1, 'numeric' => true],
                ['value' => $member['name']],
                ['value' => $member['attendance_count'], 'numeric' => true],
                ['value' => $member['session_count'], 'numeric' => true],
                ['value' => $member['attendance_percentage'] === null ? '—' : $member['attendance_percentage'].'%'],
                ['value' => $member['status'] === 'active' ? 'Anggota Aktif' : 'Nonaktif'],
            ];
        }

        $worksheetRows = '';
        foreach ($rows as $rowIndex => $cells) {
            $excelRow = $rowIndex + 1;
            $cellXml = '';
            foreach ($cells as $columnIndex => $cell) {
                $column = chr(65 + $columnIndex);
                $cellXml .= $this->excelCell($column.$excelRow, $cell);
            }
            $worksheetRows .= '<row r="'.$excelRow.'">'.$cellXml.'</row>';
        }

        $lastRow = count($rows);
        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:F'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/><col min="3" max="6" width="22" customWidth="1"/></cols>'
            .'<sheetData>'.$worksheetRows.'</sheetData><autoFilter ref="A5:F'.$lastRow.'"/></worksheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap Bulanan" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="15"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $worksheet,
        ];

        foreach ($files as $file => $contents) {
            if (! $archive->addFromString($file, $contents)) {
                $archive->close();
                throw new RuntimeException('Workbook Excel tidak dapat disimpan.');
            }
        }

        $archive->close();
    }

    /** @param array{value: mixed, style?: int, numeric?: bool} $cell */
    private function excelCell(string $reference, array $cell): string
    {
        $style = isset($cell['style']) ? ' s="'.$cell['style'].'"' : '';
        $value = $cell['value'];
        if (($cell['numeric'] ?? false) && is_numeric($value)) {
            return '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
        }

        $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
    }

    private function formatPeriod(string $start, string $end): string
    {
        return Carbon::parse($start)->locale('id')->translatedFormat('j F Y')
            .' – '.Carbon::parse($end)->locale('id')->translatedFormat('j F Y');
    }
}
