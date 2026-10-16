<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectOffering extends Model
{
    protected $fillable = [
        'curriculum_subject_id',
        'rombel_id',
        'semester_id',
        'pengetahuan_weight',
        'keterampilan_weight',
        'kkm',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pengetahuan_weight' => 'integer',
            'keterampilan_weight' => 'integer',
            'kkm' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function effectiveKkm(): int
    {
        return $this->kkm ?? 70;
    }

    public function curriculumSubject(): BelongsTo
    {
        return $this->belongsTo(CurriculumSubject::class);
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function reportScores(): HasMany
    {
        return $this->hasMany(ReportScore::class);
    }
}
