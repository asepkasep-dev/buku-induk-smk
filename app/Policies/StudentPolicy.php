<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('VIEW_STUDENT');
    }

    public function view(User $user, Student $student): bool
    {
        if (! $user->hasPermission('VIEW_STUDENT')) {
            return false;
        }

        if ($user->role?->code === 'ADMIN') {
            return true;
        }

        if ($user->role?->code === 'OPERATOR') {
            return $user->accessibleStudentsAsOperator()
                ->whereKey($student->id)
                ->exists();
        }

        if ($user->role?->code === 'WALI_KELAS') {
            return $user->accessibleStudentsAsHomeroom()
                ->whereKey($student->id)
                ->exists();
        }

        if ($user->role?->code === 'STUDENT') {
            return $user->canAccessOwnStudent($student);
        }

        if ($user->role?->code === 'KEPALA_SEKOLAH') {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('MANAGE_STUDENT');
    }

    public function update(User $user, Student $student): bool
    {
        if (! $user->hasPermission('MANAGE_STUDENT')) {
            return false;
        }

        if ($user->role?->code === 'ADMIN') {
            return true;
        }

        if ($user->role?->code === 'OPERATOR') {
            return true;
        }

        return false;
    }

    public function delete(User $user, Student $student): bool
    {
        return false;
    }

    public function restore(User $user, Student $student): bool
    {
        return false;
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}