<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['name', 'email', 'password', 'role_id', 'student_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function classAssignments(): HasMany
    {
        return $this->hasMany(ClassAssignment::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->role?->permissions()
            ->where('code', $permissionCode)
            ->exists() ?? false;
    }

    public function activeClassAssignments(): HasMany
    {
        return $this->classAssignments()->where('status', 'ACTIVE');
    }

    public function accessibleStudentsAsHomeroom(): Builder
    {
        $rombelIds = $this->activeClassAssignments()->pluck('rombel_id');

        return Student::query()
            ->whereHas('enrollments', function (Builder $query) use ($rombelIds) {
                $query->whereIn('rombel_id', $rombelIds)
                    ->where('status', 'ACTIVE');
            });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function canAccessOwnStudent(Student $student): bool
    {
        return $this->student_id !== null
            && $this->student_id === $student->id;
    }

    public function operatorRombelScopes(): HasMany
    {
        return $this->hasMany(OperatorRombelScope::class);
    }

    public function activeOperatorRombelScopes(): HasMany
    {
        return $this->operatorRombelScopes()->where('is_active', true);
    }

    public function accessibleStudentsAsOperator(): Builder
    {
        $rombelIds = $this->activeOperatorRombelScopes()->pluck('rombel_id');

        return Student::query()
            ->whereHas('enrollments', function (Builder $query) use ($rombelIds) {
                $query->whereIn('rombel_id', $rombelIds)
                    ->where('status', 'ACTIVE');
            });
    }
}
