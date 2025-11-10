<?php

namespace Database\Seeders;

use App\Models\EvaluationStatus;
use App\Models\StandardAchievement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RefSeeder extends Seeder
{
    public function run(): void
    {
        // === Ref Ketercapaian Standard ===
        $ketercapaian = [
            'Melampaui',
            'Mencapai',
            'Tidak Mencapai',
            'Menyimpang',
        ];

        foreach ($ketercapaian as $name) {
            DB::table('ref_standard_achievements')->insert([
                'id' => StandardAchievement::generateNextId(),
                'name' => $name,
                'active' => true,
            ]);
        }

        // === Ref Status Evaluasi ===
        $status = [
            'Draft',
            'Dikirim',
            'Disetujui',
        ];

        foreach ($status as $name) {
            DB::table('ref_evaluation_status')->insert([
                'id' => EvaluationStatus::generateNextId(),
                'name' => $name,
                'active' => true,
            ]);
        }
    }
}
