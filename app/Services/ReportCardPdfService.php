<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ReportCardPdfService
{
    public function __construct(
        private ReportCardAssembler $assembler,
    ) {}

    /**
     * Menghasilkan instance PDF untuk satu siswa di semester tertentu.
     */
    public function renderPdf(Student $student, Semester $semester): DomPdfInstance
    {
        $data = $this->assembler->assemble($student, $semester);

        return Pdf::loadView('report-cards.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false);
    }

    /**
     * Menghasilkan batch export seluruh PDF rapor satu rombel dalam satu arsip ZIP.
     *
     * @return string Path absolut file ZIP yang dihasilkan
     */
    public function renderBatchZip(Rombel $rombel, Semester $semester): string
    {
        $enrollments = Enrollment::query()
            ->where('rombel_id', $rombel->id)
            ->where('academic_year_id', $semester->academic_year_id)
            ->where('status', 'ACTIVE')
            ->with('student')
            ->get();

        if ($enrollments->isEmpty()) {
            throw new RuntimeException('Tidak ada siswa aktif terdaftar di rombel ini.');
        }

        $tempDir = storage_path('app/temp/rapor_'.Str::random(12));
        File::ensureDirectoryExists($tempDir);

        $zipFileName = sprintf(
            'rapor_%s_%s_%s.zip',
            Str::slug($rombel->name),
            Str::slug($semester->name),
            now()->format('Ymd_His')
        );
        $zipFilePath = storage_path('app/temp/'.$zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($tempDir);
            throw new RuntimeException('Gagal membuat arsip ZIP rapor.');
        }

        try {
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                if (! $student) {
                    continue;
                }

                $pdf = $this->renderPdf($student, $semester);
                $pdfFileName = sprintf(
                    '%s_%s.pdf',
                    $student->nis ?: 'NIS_'.$student->id,
                    Str::slug($student->full_name)
                );

                $pdfFilePath = $tempDir.'/'.$pdfFileName;
                file_put_contents($pdfFilePath, $pdf->output());

                $zip->addFile($pdfFilePath, $pdfFileName);
            }

            $zip->close();
        } finally {
            File::deleteDirectory($tempDir);
        }

        return $zipFilePath;
    }
}
