<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumSubject extends Model
{
    protected $fillable = [
        'curriculum_id',
        'subject_id',
        'competency_id',
        'grade',
        'semester_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'semester_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }
}