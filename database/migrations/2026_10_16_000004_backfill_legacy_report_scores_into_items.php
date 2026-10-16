<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyScores = DB::table('report_scores')
            ->whereNotNull('final_score')
            ->whereNull('pengetahuan_avg')
            ->get(['id', 'final_score', 'updated_at']);

        foreach ($legacyScores as $reportScore) {
            $now = now();

            DB::table('report_score_items')->insert([
                [
                    'report_score_id' => $reportScore->id,
                    'type' => 'PENGETAHUAN',
                    'label' => 'Nilai lama (agregat)',
                    'score' => $reportScore->final_score,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'report_score_id' => $reportScore->id,
                    'type' => 'KETERAMPILAN',
                    'label' => 'Nilai lama (agregat)',
                    'score' => $reportScore->final_score,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('report_scores')
                ->where('id', $reportScore->id)
                ->update([
                    'pengetahuan_avg' => $reportScore->final_score,
                    'keterampilan_avg' => $reportScore->final_score,
                ]);
        }
    }

    public function down(): void
    {
        $reportScoreIds = DB::table('report_score_items')
            ->where('label', 'Nilai lama (agregat)')
            ->pluck('report_score_id')
            ->unique();

        DB::table('report_score_items')
            ->where('label', 'Nilai lama (agregat)')
            ->delete();

        DB::table('report_scores')
            ->whereIn('id', $reportScoreIds)
            ->update([
                'pengetahuan_avg' => null,
                'keterampilan_avg' => null,
            ]);
    }
};
