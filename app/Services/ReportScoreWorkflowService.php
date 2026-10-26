<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReportScoreWorkflowService
{
    public function lock(User $user, ReportScore $reportScore): ReportScore
    {
        if (! $user->can('lock', $reportScore)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $reportScore) {
            $beforeData = $reportScore->toArray();

            $reportScore->update([
                'status' => 'LOCKED',
                'locked_at' => now(),
                'locked_by' => $user->id,
            ]);

            $updatedReportScore = $reportScore->fresh();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'LOCK_REPORT_SCORE',
                'resource_type' => ReportScore::class,
                'resource_id' => $updatedReportScore->id,
                'before_data' => $beforeData,
                'after_data' => $updatedReportScore->toArray(),
            ]);

            return $updatedReportScore;
        });
    }

    public function finalize(User $user, ReportScore $reportScore): ReportScore
    {
        if (! $user->can('finalize', $reportScore)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $reportScore) {
            $beforeData = $reportScore->toArray();

            $reportScore->update([
                'status' => 'FINALIZED',
                'finalized_at' => now(),
                'finalized_by' => $user->id,
            ]);

            $updatedReportScore = $reportScore->fresh();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'FINALIZE_REPORT_SCORE',
                'resource_type' => ReportScore::class,
                'resource_id' => $updatedReportScore->id,
                'before_data' => $beforeData,
                'after_data' => $updatedReportScore->toArray(),
            ]);

            return $updatedReportScore;
        });
    }

    public function correct(
        User $user,
        ReportScore $reportScore,
        int $finalScore,
        ?string $letterGrade,
        ?string $description,
        string $reason
    ): ReportScore {
        if (! $user->can('correct', $reportScore)) {
            throw new AuthorizationException;
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Alasan koreksi wajib diisi.');
        }

        if ($finalScore < 0 || $finalScore > 100) {
            throw new \InvalidArgumentException('Nilai akhir harus antara 0 sampai 100.');
        }

        return DB::transaction(function () use (
            $user,
            $reportScore,
            $finalScore,
            $letterGrade,
            $description,
            $reason
        ) {
            $beforeData = $reportScore->toArray();

            $reportScore->update([
                'final_score' => $finalScore,
                'letter_grade' => $letterGrade,
                'description' => $description,
            ]);

            $updatedReportScore = $reportScore->fresh();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'CORRECT_FINALIZED_REPORT_SCORE',
                'resource_type' => ReportScore::class,
                'resource_id' => $updatedReportScore->id,
                'before_data' => $beforeData,
                'after_data' => array_merge(
                    $updatedReportScore->toArray(),
                    ['correction_reason' => $reason]
                ),
            ]);

            return $updatedReportScore;
        });
    }

    /**
     * Mengunci seluruh nilai rapor berstatus DRAFT pada suatu rombel dan semester.
     */
    public function lockRombel(User $user, Rombel $rombel, Semester $semester): int
    {
        $this->authorizeBulkLock($user, $rombel);

        $offeringIds = SubjectOffering::query()
            ->where('rombel_id', $rombel->id)
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->pluck('id');

        if ($offeringIds->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($user, $rombel, $semester, $offeringIds) {
            $scores = ReportScore::query()
                ->whereIn('subject_offering_id', $offeringIds)
                ->where('status', 'DRAFT')
                ->lockForUpdate()
                ->get();

            if ($scores->isEmpty()) {
                return 0;
            }

            $scoreIds = $scores->pluck('id')->all();
            $now = now();

            ReportScore::query()
                ->whereIn('id', $scoreIds)
                ->update([
                    'status' => 'LOCKED',
                    'locked_at' => $now,
                    'locked_by' => $user->id,
                    'updated_at' => $now,
                ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'BULK_LOCK_REPORT_SCORES',
                'resource_type' => Rombel::class,
                'resource_id' => $rombel->id,
                'before_data' => null,
                'after_data' => [
                    'semester_id' => $semester->id,
                    'locked_count' => count($scoreIds),
                    'score_ids' => $scoreIds,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return count($scoreIds);
        });
    }

    /**
     * Memfinalisasi seluruh nilai rapor berstatus LOCKED pada suatu rombel dan semester.
     */
    public function finalizeRombel(User $user, Rombel $rombel, Semester $semester): int
    {
        $this->authorizeBulkFinalize($user);

        $offerings = SubjectOffering::query()
            ->where('rombel_id', $rombel->id)
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->get();

        if ($offerings->isEmpty()) {
            return 0;
        }

        $missingKkm = $offerings->contains(fn ($o) => $o->kkm === null);
        if ($missingKkm) {
            throw new \InvalidArgumentException('Seluruh mata pelajaran wajib memiliki KKM sebelum finalisasi.');
        }

        $offeringIds = $offerings->pluck('id');

        return DB::transaction(function () use ($user, $rombel, $semester, $offeringIds) {
            $scores = ReportScore::query()
                ->whereIn('subject_offering_id', $offeringIds)
                ->where('status', 'LOCKED')
                ->lockForUpdate()
                ->get();

            if ($scores->isEmpty()) {
                return 0;
            }

            $scoreIds = $scores->pluck('id')->all();
            $now = now();

            ReportScore::query()
                ->whereIn('id', $scoreIds)
                ->update([
                    'status' => 'FINALIZED',
                    'finalized_at' => $now,
                    'finalized_by' => $user->id,
                    'updated_at' => $now,
                ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'BULK_FINALIZE_REPORT_SCORES',
                'resource_type' => Rombel::class,
                'resource_id' => $rombel->id,
                'before_data' => null,
                'after_data' => [
                    'semester_id' => $semester->id,
                    'finalized_count' => count($scoreIds),
                    'score_ids' => $scoreIds,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return count($scoreIds);
        });
    }

    private function authorizeBulkLock(User $user, Rombel $rombel): void
    {
        if (! $user->hasPermission('LOCK_REPORT_SCORE')) {
            throw new AuthorizationException;
        }

        if ($user->role?->code === 'ADMIN') {
            return;
        }

        if ($user->role?->code === 'WALI_KELAS') {
            $isAssigned = $user->classAssignments()
                ->where('rombel_id', $rombel->id)
                ->where('status', 'ACTIVE')
                ->exists();

            if ($isAssigned) {
                return;
            }
        }

        if ($user->role?->code === 'OPERATOR') {
            $hasScope = $user->operatorRombelScopes()
                ->where('rombel_id', $rombel->id)
                ->where('is_active', true)
                ->exists();

            if ($hasScope) {
                return;
            }
        }

        throw new AuthorizationException;
    }

    private function authorizeBulkFinalize(User $user): void
    {
        if ($user->role?->code === 'ADMIN' && $user->hasPermission('FINALIZE_REPORT_SCORE')) {
            return;
        }

        throw new AuthorizationException;
    }
}
