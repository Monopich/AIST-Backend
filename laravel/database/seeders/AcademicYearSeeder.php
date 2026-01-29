<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $academicYear = AcademicYear::firstOrCreate([
            'year_label' => '2025-2026',
        ], [
            'dates' => json_encode([
                'start_year' => '2025',
                'end_year' => '2026',
            ]),
        ]);
    }
}
