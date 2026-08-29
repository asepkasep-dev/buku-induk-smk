<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}