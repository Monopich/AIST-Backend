<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculty = Department::create([
            "department_name" => "Faculty of Medicine",
            "description" => "Provides medical education and training for students.",
        ]);

        $subDepartments = [
            [
                "name" => "General Medicine",
                "description" => "Focuses on the study and practice of general medicine.",
            ],
            [
                "name" => "Surgery",
                "description" => "Covers surgical techniques and training.",
            ],
            [
                "name" => "Pediatrics",
                "description" => "Specializes in medical care for children and adolescents.",
            ],
        ];

        foreach ($subDepartments as $sub) {
            SubDepartment::create([
                "name" => $sub['name'],
                "department_id" => $faculty->id,
                "description" => $sub['description'],
            ]);
        }
    }
}
