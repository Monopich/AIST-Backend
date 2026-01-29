<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([

            LocationSeeder::class,
            DepartmentSeeder::class,
            SemesterSeeder::class,
            UserSeeder::class,
            SubjectSeeder::class,
            // GenerationSeeder::class,
            // StudentSeeder::class,
            // AcademicYearSeeder::class,

        ]);
    }
}
