<?php

namespace App\Services;

class GradePredicateResolver
{
    public const DEFAULT_KKM = 70;

    /**
     * Menentukan huruf predikat dari nilai akhir dan KKM.
     * Interval I = (100 - KKM) / 3, batas pakai titik potong riil.
     */
    public function letter(?float $finalScore, int $kkm): ?string
    {
        if ($finalScore === null) {
            return null;
        }

        $interval = $this->interval($kkm);

        return match (true) {
            $finalScore >= $kkm + (2 * $interval) => 'A',
            $finalScore >= $kkm + $interval => 'B',
            $finalScore >= $kkm => 'C',
            default => 'D',
        };
    }

    public function predicate(?string $letter): ?string
    {
        return match ($letter) {
            'A' => 'Sangat Baik',
            'B' => 'Baik',
            'C' => 'Cukup',
            'D' => 'Perlu Perbaikan',
            default => null,
        };
    }

    public function isTuntas(?float $finalScore, int $kkm): bool
    {
        return $finalScore !== null && $finalScore >= $kkm;
    }

    public function interval(int $kkm): float
    {
        return (100 - $kkm) / 3;
    }

    /**
     * Batas rentang integer untuk legend (nilai bulat terendah tiap pita, ceil).
     *
     * @return array<int, array{letter: string, min: int, max: int, label: string}>
     */
    public function ranges(int $kkm): array
    {
        $interval = $this->interval($kkm);
        $startB = (int) ceil($kkm + $interval);
        $startA = (int) ceil($kkm + (2 * $interval));

        return [
            ['letter' => 'A', 'min' => $startA, 'max' => 100, 'label' => 'Sangat Baik'],
            ['letter' => 'B', 'min' => $startB, 'max' => $startA - 1, 'label' => 'Baik'],
            ['letter' => 'C', 'min' => $kkm, 'max' => $startB - 1, 'label' => 'Cukup'],
            ['letter' => 'D', 'min' => 0, 'max' => $kkm - 1, 'label' => 'Perlu Perbaikan'],
        ];
    }

    /**
     * @return array{letter: string|null, predicate: string|null, tuntas: bool}
     */
    public function resolve(?float $finalScore, int $kkm): array
    {
        $letter = $this->letter($finalScore, $kkm);

        return [
            'letter' => $letter,
            'predicate' => $this->predicate($letter),
            'tuntas' => $this->isTuntas($finalScore, $kkm),
        ];
    }
}
