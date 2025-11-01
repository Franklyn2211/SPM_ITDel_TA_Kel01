<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\KetercapaianStandard;
use App\Models\StatusEvaluasi;

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
            DB::table('ref_ketercapaian_standard')->insert([
                'id' => KetercapaianStandard::generateNextId(),
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
            DB::table('ref_status_evaluasi')->insert([
                'id' => StatusEvaluasi::generateNextId(),
                'name' => $name,
                'active' => true,
            ]);
        }
    }
}
