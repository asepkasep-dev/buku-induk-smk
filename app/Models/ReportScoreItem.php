<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportScoreItem extends Model
{
    public const TYPE_PENGETAHUAN = 'PENGETAHUAN';

    public const TYPE_KETERAMPILAN = 'KETERAMPILAN';

    protected $fillable = [
        'report_score_id',
        'type',
        'label',
        'score',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function reportScore(): BelongsTo
    {
        return $this->belongsTo(ReportScore::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePengetahuan($query)
    {
        return $query->where('type', self::TYPE_PENGETAHUAN);
    }

    public function scopeKeterampilan($query)
    {
        return $query->where('type', self::TYPE_KETERAMPILAN);
    }
}
