<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\SubjectSemester;
use App\Models\SubjectUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facultyId = 1;        // Faculty of Medicine
        $programId = 1;        // , Bachelor of Medicine

        $subject = Subject::firstOrCreate([
            'subject_code' => 'MED101',
        ], [
            'subject_name'       => 'Introduction to Human Anatomy',
            'description'        => 'Covers basic human anatomy concepts for medical students.',
            'credit'             => 3,
            'total_hours'        => 45,
            'practice_hours'     => 15,
            'program_id'         => $programId,
            'department_id'      => $facultyId,
        ]);
        $subject2 = Subject::firstOrCreate([
            'subject_code' => 'MED102',
        ], [
            'subject_name'       => 'Medical Biochemistry',
            'description'        => 'Introduction to biochemical processes relevant to medicine.',
            'credit'             => 4,
            'total_hours'        => 60,
            'practice_hours'     => 20,
            'program_id'         => $programId,
            'department_id'      => $facultyId,
        ]);

        // Attach a teacher (assume teacher with ID 2 exists)
        if (!$subject->teachers->contains(2)) {
            $subject->teachers()->attach(2);
        }

        $subjectSemester = SubjectSemester::firstOrCreate([
            'subject_id' => $subject->id,
            'semester_id' => 1,
        ]);
        $subjectSemester2 = SubjectSemester::firstOrCreate([
            'subject_id' => $subject2->id,
            'semester_id' => 1,
        ]);



    }
}
