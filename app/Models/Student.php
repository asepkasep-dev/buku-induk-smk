<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ReportScore;

class Student extends Model
{
    protected $fillable = [
        'nis',
        'nisn',
        'nik',
        'full_name',
        'nickname',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'entry_year',
        'previous_school',
        'previous_diploma_number',
        'previous_diploma_date',
        'phone',
        'email',
        'address',
        'village',
        'district',
        'city',
        'province',
        'postal_code',
        'status',
        'entry_date',
        'exit_date',
        'exit_notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'previous_diploma_date' => 'date',
            'entry_date' => 'date',
            'exit_date' => 'date',
            'entry_year' => 'integer',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function extracurricularRecords(): HasMany
    {
        return $this->hasMany(ExtracurricularRecord::class);
    }

    public function pklRecords(): HasMany
    {
        return $this->hasMany(PKLRecord::class);
    }

    public function ukkRecords(): HasMany
    {
        return $this->hasMany(UKKRecord::class);
    }

    public function diplomaScores(): HasMany
    {
        return $this->hasMany(DiplomaScore::class);
    }

    public function graduation(): HasOne
    {
        return $this->hasOne(Graduation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function studentGuardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function canAccessOwnStudent(Student $student): bool
    {
        return $this->student_id !== null
            && $this->student_id === $student->id;
    }

    public function reportScores(): HasMany
    {
        return $this->hasMany(ReportScore::class);
    }
}