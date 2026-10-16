<?php

namespace App\Services;

class ReportDescriptionGenerator
{
    /**
     * Membangun deskripsi rapor berbahasa Indonesia dari agregat nilai.
     *
     * @param  array{subject_name: string, pengetahuan_avg: float|null, keterampilan_avg: float|null, final_score: int|null, letter: string|null, predicate: string|null, tuntas: bool, kkm: int}  $context
     */
    public function generate(array $context): string
    {
        $subject = trim($context['subject_name'] ?? '') !== ''
            ? $context['subject_name']
            : 'mata pelajaran ini';

        $final = $context['final_score'];
        $predicate = $context['predicate'] ?? '-';
        $letter = $context['letter'] ?? '-';
        $kkm = (int) ($context['kkm'] ?? GradePredicateResolver::DEFAULT_KKM);

        if ($final === null) {
            return "Nilai {$subject} belum lengkap sehingga predikat dan deskripsi belum dapat dihitung.";
        }

        $kalimatUtama = "Capaian nilai {$subject} adalah {$final} (predikat {$letter}, {$predicate}) "
            .($context['tuntas']
                ? "dan telah mencapai KKM {$kkm}."
                : "dan belum mencapai KKM {$kkm}.");

        $rinciPengetahuan = $this->ratarata($context['pengetahuan_avg'] ?? null);
        $rinciKeterampilan = $this->ratarata($context['keterampilan_avg'] ?? null);

        if ($rinciPengetahuan !== null && $rinciKeterampilan !== null) {
            $fokus = $context['tuntas']
                ? 'Ananda disarankan mempertahankan konsistensi dan memperdalam keterampilan terapan.'
                : 'Ananda perlu peningkatan pada aspek pengetahuan dan keterampilan secara seimbang.';

            return $kalimatUtama
                ." Rata-rata pengetahuan {$rinciPengetahuan} dan keterampilan {$rinciKeterampilan}. {$fokus}";
        }

        return $kalimatUtama;
    }

    private function ratarata(?float $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        return number_format($nilai, 1, ',', '.');
    }
}
