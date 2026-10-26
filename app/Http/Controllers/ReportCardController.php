<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ReportCardAssembler;
use App\Services\ReportCardPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportCardController extends Controller
{
    /**
     * Tampilan cetak A4 on-demand via browser print CSS.
     */
    public function print(
        Request $request,
        Student $student,
        Semester $semester,
        ReportCardAssembler $assembler
    ) {
        abort_unless($request->user()?->can('view', $student), 403);

        $data = $assembler->assemble($student, $semester);

        return view('report-cards.print', $data);
    }

    /**
     * Download PDF rapor tunggal satu siswa.
     */
    public function pdf(
        Request $request,
        Student $student,
        Semester $semester,
        ReportCardPdfService $pdfService
    ): Response {
        abort_unless($request->user()?->can('view', $student), 403);

        $pdf = $pdfService->renderPdf($student, $semester);
        $fileName = sprintf(
            'rapor-%s-%s.pdf',
            $student->nis ?: $student->id,
            Str::slug($semester->name)
        );

        return $pdf->download($fileName);
    }

    /**
     * Download batch export PDF seluruh siswa di rombel dalam satu arsip ZIP.
     */
    public function batchPdf(
        Request $request,
        Rombel $rombel,
        Semester $semester,
        ReportCardPdfService $pdfService
    ): BinaryFileResponse {
        $this->authorizeClassAccess($request, $rombel);

        $zipPath = $pdfService->renderBatchZip($rombel, $semester);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function authorizeClassAccess(Request $request, Rombel $rombel): void
    {
        $user = $request->user();

        if (in_array($user?->role?->code, ['ADMIN', 'KEPALA_SEKOLAH'], true)) {
            return;
        }

        if ($user?->role?->code === 'WALI_KELAS') {
            $isAssigned = $user->classAssignments()
                ->where('rombel_id', $rombel->id)
                ->where('status', 'ACTIVE')
                ->exists();

            abort_unless($isAssigned, 403, 'Akses ditolak: Anda bukan wali kelas rombel ini.');

            return;
        }

        if ($user?->role?->code === 'OPERATOR') {
            $hasScope = $user->operatorRombelScopes()
                ->where('rombel_id', $rombel->id)
                ->where('is_active', true)
                ->exists();

            abort_unless($hasScope, 403, 'Akses ditolak: Rombel di luar cakupan tugas operator Anda.');

            return;
        }

        abort(403, 'Akses ekspor massal rapor ditolak.');
    }
}
