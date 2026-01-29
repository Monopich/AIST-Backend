<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\TimeTable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Type\Time;

class InternshipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run(): void
    {

        $group = Group::create([
            "name" => "GIC-Internship",
            "semester_id" => 1,
            "program_id" => 1,
            "department_id" => 1,
            "sub_department_id" => 1,
            "description" => "This is testing attendance record for internship."
        ]);

        $timeTable = TimeTable::create([
            "name" => "GIC-Internship-TimeTable",
            "description" => "This is testing attendance record for internship.",
            "group_id" => $group->id
        ]);

        $timeTableId = $timeTable->id;
        $teacherId = 2;
        $subjects = 1;
        $locationId = 2;

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Standard Cambodia slots
        $dailySlots = [
            ['start' => '08:00:00', 'end' => '11:00:00'], // Morning
            ['start' => '13:00:00', 'end' => '17:00:00'], // Afternoon
        ];

        $timeTable = TimeTable::find($timeTableId);

        foreach ($daysOfWeek as $day) {
            foreach ($dailySlots as $index => $slot) {
                $timeTable->timeSlots()->create([
                    'day_of_week' => $day,
                    'time_slot' => json_encode([
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                    ]),
                    'teacher_id' => $teacherId,
                    'subject_id' => $subjects,
                    'location_id' => $locationId,
                    'remark' => $day . ' Class',
                ]);
            }
        }

        
    }
}
