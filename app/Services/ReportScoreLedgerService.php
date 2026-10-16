<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\ReportScoreItem;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportScoreLedgerService
{
    public function __construct(
        private GradePredicateResolver $resolver,
        private ReportDescriptionGenerator $descriptions,
    ) {}

    public function addItem(
        User $user,
        ReportScore $reportScore,
        string $type,
        string $label,
        int $score,
    ): ReportScoreItem {
        $this->assertDraftEditable($reportScore);
        $this->assertType($type);
        $this->assertScore($score);

        if (trim($label) === '') {
            throw new InvalidArgumentException('Label item nilai wajib diisi.');
        }

        return DB::transaction(function () use ($user, $reportScore, $type, $label, $score) {
            $locked = $this->lockReportScore($reportScore->id);

            $sortOrder = (int) ReportScoreItem::query()
                ->where('report_score_id', $locked->id)
                ->where('type', $type)
                ->max('sort_order') + 1;

            $item = ReportScoreItem::create([
                'report_score_id' => $locked->id,
                'type' => $type,
                'label' => trim($label),
                'score' => $score,
                'sort_order' => $sortOrder,
                'created_by' => $user->id,
            ]);

            $recalculated = $this->recalculate($locked);

            $this->audit($user, 'LEDGER_ADD_ITEM', $recalculated, [
                'item_id' => $item->id,
                'type' => $item->type,
                'label' => $item->label,
                'score' => $item->score,
            ]);

            return $item;
        });
    }

    public function updateItem(
        User $user,
        ReportScoreItem $item,
        ?string $label,
        ?int $score,
    ): ReportScoreItem {
        $reportScore = $item->reportScore;
        $this->assertDraftEditable($reportScore);

        if ($label !== null && trim($label) === '') {
            throw new InvalidArgumentException('Label item nilai wajib diisi.');
        }

        if ($score !== null) {
            $this->assertScore($score);
        }

        return DB::transaction(function () use ($user, $item, $reportScore, $label, $score) {
            $locked = $this->lockReportScore($reportScore->id);
            $item = ReportScoreItem::query()->lockForUpdate()->findOrFail($item->id);

            $before = $item->only(['label', 'score']);

            $item->update(array_filter([
                'label' => $label !== null ? trim($label) : null,
                'score' => $score,
            ], fn ($v) => $v !== null));

            $recalculated = $this->recalculate($locked);

            $this->audit($user, 'LEDGER_UPDATE_ITEM', $recalculated, [
                'item_id' => $item->id,
                'before' => $before,
                'after' => $item->only(['label', 'score']),
            ]);

            return $item;
        });
    }

    public function removeItem(User $user, ReportScoreItem $item): void
    {
        $reportScore = $item->reportScore;
        $this->assertDraftEditable($reportScore);

        DB::transaction(function () use ($user, $item, $reportScore) {
            $locked = $this->lockReportScore($reportScore->id);
            $item = ReportScoreItem::query()->lockForUpdate()->findOrFail($item->id);

            $before = $item->only(['type', 'label', 'score']);
            $itemId = $item->id;
            $item->delete();

            $recalculated = $this->recalculate($locked);

            $this->audit($user, 'LEDGER_DELETE_ITEM', $recalculated, [
                'item_id' => $itemId,
                'deleted' => $before,
            ]);
        });
    }

    public function updateOfferingWeights(
        User $user,
        SubjectOffering $offering,
        int $pengetahuanWeight,
        int $keterampilanWeight,
        ?int $kkm,
    ): SubjectOffering {
        $this->assertWeight($pengetahuanWeight, 'Bobot pengetahuan');
        $this->assertWeight($keterampilanWeight, 'Bobot keterampilan');

        if ($pengetahuanWeight + $keterampilanWeight <= 0) {
            throw new InvalidArgumentException('Total bobot pengetahuan dan keterampilan harus lebih dari 0.');
        }

        if ($kkm !== null) {
            $this->assertScore($kkm);
        }

        return DB::transaction(function () use ($user, $offering, $pengetahuanWeight, $keterampilanWeight, $kkm) {
            $lockedOffering = SubjectOffering::query()->lockForUpdate()->findOrFail($offering->id);

            $before = $lockedOffering->only(['pengetahuan_weight', 'keterampilan_weight', 'kkm']);

            $lockedOffering->update([
                'pengetahuan_weight' => $pengetahuanWeight,
                'keterampilan_weight' => $keterampilanWeight,
                'kkm' => $kkm,
            ]);

            $affected = ReportScore::query()
                ->where('subject_offering_id', $lockedOffering->id)
                ->pluck('id');

            foreach ($affected as $reportScoreId) {
                $this->recalculate($this->lockReportScore($reportScoreId));
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'UPDATE_OFFERING_WEIGHTS',
                'resource_type' => SubjectOffering::class,
                'resource_id' => $lockedOffering->id,
                'before_data' => $before,
                'after_data' => $lockedOffering->only(['pengetahuan_weight', 'keterampilan_weight', 'kkm']),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $lockedOffering;
        });
    }

    /**
     * Menghitung ulang seluruh kolom turunan report_scores dari ledger + bobot/KKM offering.
     */
    public function recalculate(ReportScore $reportScore): ReportScore
    {
        $offering = $reportScore->subjectOffering ?? SubjectOffering::query()->findOrFail($reportScore->subject_offering_id);

        $items = $reportScore->items()->get();

        $penAverage = $this->average($items->where('type', ReportScoreItem::TYPE_PENGETAHUAN)->pluck('score')->all());
        $ketAverage = $this->average($items->where('type', ReportScoreItem::TYPE_KETERAMPILAN)->pluck('score')->all());

        $finalScore = null;
        if ($penAverage !== null && $ketAverage !== null) {
            $penWeight = (int) ($offering->pengetahuan_weight ?? 50);
            $ketWeight = (int) ($offering->keterampilan_weight ?? 50);
            $totalWeight = max($penWeight + $ketWeight, 1);

            $finalScore = (int) round(($penAverage * $penWeight + $ketAverage * $ketWeight) / $totalWeight);
        }

        $kkm = (int) ($offering->kkm ?? GradePredicateResolver::DEFAULT_KKM);
        $resolved = $this->resolver->resolve($finalScore === null ? null : (float) $finalScore, $kkm);

        $subjectName = $offering->curriculumSubject?->subject?->name
            ?? $offering->curriculumSubject?->name
            ?? '';

        $description = $this->descriptions->generate([
            'subject_name' => $subjectName,
            'pengetahuan_avg' => $penAverage,
            'keterampilan_avg' => $ketAverage,
            'final_score' => $finalScore,
            'letter' => $resolved['letter'],
            'predicate' => $resolved['predicate'],
            'tuntas' => $resolved['tuntas'],
            'kkm' => $kkm,
        ]);

        $reportScore->update([
            'pengetahuan_avg' => $penAverage,
            'keterampilan_avg' => $ketAverage,
            'final_score' => $finalScore,
            'letter_grade' => $resolved['letter'],
            'description' => $description,
        ]);

        return $reportScore->refresh();
    }

    private function lockReportScore(int $id): ReportScore
    {
        return ReportScore::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<int, int|float>  $scores
     */
    private function average(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        return array_sum($scores) / count($scores);
    }

    private function assertDraftEditable(ReportScore $reportScore): void
    {
        if ($reportScore->status !== 'DRAFT') {
            throw new InvalidArgumentException('Nilai sudah dikunci/difinalisasi dan tidak dapat diubah.');
        }
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, [ReportScoreItem::TYPE_PENGETAHUAN, ReportScoreItem::TYPE_KETERAMPILAN], true)) {
            throw new InvalidArgumentException('Tipe item nilai tidak valid.');
        }
    }

    private function assertScore(int $score): void
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('Nilai harus antara 0 sampai 100.');
        }
    }

    private function assertWeight(int $weight, string $label): void
    {
        if ($weight < 0 || $weight > 100) {
            throw new InvalidArgumentException("{$label} harus antara 0 sampai 100.");
        }
    }

    private function audit(User $user, string $action, ReportScore $after, array $meta): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'resource_type' => ReportScore::class,
            'resource_id' => $after->id,
            'before_data' => null,
            'after_data' => array_merge($after->only([
                'pengetahuan_avg',
                'keterampilan_avg',
                'final_score',
                'letter_grade',
                'status',
            ]), ['ledger' => $meta]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
