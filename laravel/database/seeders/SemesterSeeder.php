<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\Group;
use App\Models\Program;
use App\Models\Semester;
use App\Models\TimeTable;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Academic Years first
        // $academicYear2024 = AcademicYear::firstOrCreate(
        //     ['year_label' => '2024-2025'],
        //     ['dates' => ['start_year' => 2024, 'end_year' => 2025]]
        // );
        
        // $academicYear2025 = AcademicYear::firstOrCreate(
        //     ['year_label' => '2025-2026'],
        //     ['dates' => ['start_year' => 2025, 'end_year' => 2026]]
        // );
        
        // $academicYear2023 = AcademicYear::firstOrCreate(
        //     ['year_label' => '2023-2024'],
        //     ['dates' => ['start_year' => 2023, 'end_year' => 2024]]
        // );
        
        // $academicYear2028 = AcademicYear::firstOrCreate(
        //     ['year_label' => '2028-2029'],
        //     ['dates' => ['start_year' => 2028, 'end_year' => 2029]]
        // );

        $program = Program::firstOrCreate([
            'program_name' => 'Bachelor of Medicine',
        ], [
            'degree_level' => 'Bachelor',
            'duration_years' => 6,
            'academic_year' => "2025-2026",
            'sub_department_id' => 1,
            'department_id' => 1,
        ]);
        $gen1 = Generation::create([
            'number_gen' => 1,
            'program_id' => $program->id,
            'start_year' => 2025
        ]);

        // Create Semester
        $semester = Semester::firstOrCreate([
            'semester_number' => 1,
            'program_id' => $program->id,
        ], [
            'semester_key' => '1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-01',
            // 'academic_year_id' => $academicYear2025->id,
        ]);
        $semester = Semester::firstOrCreate([
            'semester_number' => 2,
            'program_id' => $program->id,
        ], [
            'semester_key' => '1',
            'start_date' => '2026-02-15',
            'end_date' => '2026-06-15',
            // 'academic_year_id' => $academicYear2025->id,
        ]);

        // Create Group
        $group = Group::firstOrCreate([
            'name' => 'A1',
            'semester_id' => $semester->id,
            'department_id' => 1,
            'sub_department_id' => 1,
            'program_id'=> $program->id
        ], [
            'description' => 'Group A1 - General Medicine students for Semester 1',
        ]);
        $group2 = Group::firstOrCreate([
            'name' => 'A2',
            'semester_id' => $semester->id,
            'department_id' => 1,
            'sub_department_id' => 1,
            'program_id'=> $program->id
        ], [
            'description' => 'Group A1 - General Medicine students for Semester 1',
        ]);

        $timeTable = TimeTable::firstOrCreate([
            "name" => "A1-TimeTable",
            "description" => "This is time table attendance record for A1 group.",
            "group_id" => $group->id
        ]);
        $timeTable2 = TimeTable::firstOrCreate([
            "name" => "A2-TimeTable",
            "description" => "This is time table attendance record for A2 group.",
            "group_id" => $group2->id
        ]);
    }

}
