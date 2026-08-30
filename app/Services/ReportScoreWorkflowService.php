<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReportScore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReportScoreWorkflowService
{
    public function lock(User $user, ReportScore $reportScore): ReportScore
    {
        if (! $user->can('lock', $reportScore)) {
            throw new AuthorizationException();
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
            throw new AuthorizationException();
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
            throw new AuthorizationException();
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
}