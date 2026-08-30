<?php

namespace App\Policies;

use App\Models\ReportScore;
use App\Models\User;

class ReportScorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('VIEW_REPORT_SCORE');
    }

    public function view(User $user, ReportScore $reportScore): bool
    {
        if (! $user->hasPermission('VIEW_REPORT_SCORE')) {
            return false;
        }

        if ($user->role?->code === 'ADMIN') {
            return true;
        }

        if ($user->role?->code === 'KEPALA_SEKOLAH') {
            return true;
        }

        if ($user->role?->code === 'STUDENT') {
            return $user->student_id === $reportScore->student_id;
        }

        if ($user->role?->code === 'WALI_KELAS') {
            return $user->accessibleStudentsAsHomeroom()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        if ($user->role?->code === 'OPERATOR') {
            return $user->accessibleStudentsAsOperator()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('INPUT_REPORT_SCORE');
    }

    public function update(User $user, ReportScore $reportScore): bool
    {
        if (! $user->hasPermission('INPUT_REPORT_SCORE')) {
            return false;
        }

        if ($reportScore->status === 'FINALIZED') {
            return false;
        }

        if ($user->role?->code === 'ADMIN') {
            return true;
        }

        if ($user->role?->code === 'WALI_KELAS') {
            return $user->accessibleStudentsAsHomeroom()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        if ($user->role?->code === 'OPERATOR') {
            return $user->accessibleStudentsAsOperator()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        return false;
    }

    public function lock(User $user, ReportScore $reportScore): bool
    {
        if (! $user->hasPermission('LOCK_REPORT_SCORE')) {
            return false;
        }

        if ($reportScore->status !== 'DRAFT') {
            return false;
        }

        if ($user->role?->code === 'ADMIN') {
            return true;
        }

        if ($user->role?->code === 'WALI_KELAS') {
            return $user->accessibleStudentsAsHomeroom()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        if ($user->role?->code === 'OPERATOR') {
            return $user->accessibleStudentsAsOperator()
                ->whereKey($reportScore->student_id)
                ->exists();
        }

        return false;
    }

    public function finalize(User $user, ReportScore $reportScore): bool
    {
        return $user->hasPermission('FINALIZE_REPORT_SCORE')
            && $user->role?->code === 'ADMIN'
            && $reportScore->status === 'LOCKED';
    }

    public function delete(User $user, ReportScore $reportScore): bool
    {
        return false;
    }

    public function restore(User $user, ReportScore $reportScore): bool
    {
        return false;
    }

    public function forceDelete(User $user, ReportScore $reportScore): bool
    {
        return false;
    }

    public function correct(User $user, ReportScore $reportScore): bool
    {
        return $user->role?->code === 'ADMIN'
            && $user->hasPermission('CORRECT_FINALIZED_REPORT_SCORE')
            && $reportScore->status === 'FINALIZED';
    }
}