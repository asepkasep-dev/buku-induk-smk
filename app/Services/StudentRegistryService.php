<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StudentRegistryService
{
    public function create(User $user, array $data, ?int $rombelId = null): Student
    {
        Gate::forUser($user)->authorize('create', Student::class);

        if ($user->role?->code === 'OPERATOR' && $rombelId === null) {
            abort(403, 'Operator harus menempatkan siswa baru ke rombel yang ditugaskan.');
        }

        return DB::transaction(function () use ($user, $data, $rombelId) {
            $rombel = null;
            if ($rombelId !== null) {
                $rombel = Rombel::query()->lockForUpdate()->findOrFail($rombelId);
                abort_unless($rombel->is_active, 403, 'Rombel ini tidak aktif.');
                if ($user->role?->code === 'OPERATOR') {
                    abort_unless(
                        $user->activeOperatorRombelScopes()->where('rombel_id', $rombel->id)->exists(),
                        403,
                        'Rombel tersebut tidak termasuk ruang lingkup operator.'
                    );
                    abort_unless(
                        $rombel->academicYear()->value('status') === 'ACTIVE',
                        403,
                        'Siswa baru hanya dapat ditempatkan pada tahun ajaran aktif.'
                    );
                }
            }

            $student = Student::query()->create($data);

            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'CREATE_STUDENT',
                'resource_type' => Student::class,
                'resource_id' => $student->id,
                'after_data' => [
                    'nis' => $student->nis,
                    'changed_fields' => array_keys($data),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if ($rombel) {
                $enrollment = Enrollment::query()->create([
                    'student_id' => $student->id,
                    'academic_year_id' => $rombel->academic_year_id,
                    'rombel_id' => $rombel->id,
                    'status' => 'ACTIVE',
                ]);
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'CREATE_ENROLLMENT',
                    'resource_type' => Enrollment::class,
                    'resource_id' => $enrollment->id,
                    'after_data' => [
                        'student_id' => $student->id,
                        'academic_year_id' => $rombel->academic_year_id,
                        'rombel_id' => $rombel->id,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            return $student;
        });
    }

    public function update(User $user, Student $student, array $data): Student
    {
        Gate::forUser($user)->authorize('update', $student);

        return DB::transaction(function () use ($user, $student, $data) {
            $lockedStudent = Student::query()
                ->lockForUpdate()
                ->findOrFail($student->id);

            Gate::forUser($user)->authorize('update', $lockedStudent);

            $before = $lockedStudent->only(array_keys($data));
            $lockedStudent->update($data);
            $changedFields = array_keys(array_filter(
                $data,
                fn ($value, $key) => $before[$key] !== $value,
                ARRAY_FILTER_USE_BOTH
            ));

            if ($changedFields !== []) {
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'action' => 'UPDATE_STUDENT',
                    'resource_type' => Student::class,
                    'resource_id' => $lockedStudent->id,
                    'before_data' => array_intersect_key($before, array_flip($changedFields)),
                    'after_data' => [
                        'nis' => $lockedStudent->nis,
                        'changed_fields' => $changedFields,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            return $lockedStudent->fresh();
        });
    }
}
