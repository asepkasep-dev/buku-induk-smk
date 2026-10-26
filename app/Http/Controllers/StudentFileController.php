<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentFileController extends Controller
{
    /**
     * Download Excel template for bulk student import.
     */
    public function downloadImportTemplate(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->can('create', Student::class) && $user?->role?->code === 'ADMIN', 403);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Siswa');
        $sheet->fromArray([
            ['nis', 'nisn', 'nik', 'full_name', 'gender', 'entry_year', 'status', 'phone', 'email', 'address'],
            ['SMK-001', '', '', 'Nama Siswa', 'L', now()->year, 'CALON', '', '', ''],
        ], null, 'A1');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            'template-impor-siswa.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Download uploaded document for a specific student.
     */
    public function downloadDocument(Request $request, Student $student, StudentDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->can('view', $student), 403);
        abort_unless($user?->hasPermission('MANAGE_STUDENT_DOCUMENT'), 403);
        abort_unless((int) $document->student_id === (int) $student->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        $name = basename(str_replace('\\', '/', $document->file_name));
        $name = preg_replace('/[\r\n"]/', '_', $name);

        return Storage::disk('local')->download($document->file_path, $name);
    }
}
