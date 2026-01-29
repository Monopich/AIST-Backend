<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleStudent = Role::create([
            "name"=> "Student",
            'description'=> "Student with limited access",
            'role_key'=> "Student",
        ]);
        $roleStaff = Role::create([
            "name"=> "Teacher",
            'description'=> "Teacher with extended access",
            'role_key'=> "Staff",
        ]);
        $roleHeadDepartment = Role::create([
            "name"=> "Head Department",
            'description'=> "Head of Department with extended access",
            'role_key'=> "Head Department",
        ]);
        $roleAdmin = Role::create([
            "name"=> "Admin",
            'description'=> "Administrator with full access",
            'role_key'=> "Admin",
        ]);
        // $student = User::create([
        //     "name"=> "Student",
        //     "email"=> "e20260000@rtc-bb.camai.kh",
        //     "password"=>"12345678",
        // ]);
        // $student->assignRole('Student');

        // $student->UserDetail()->create([
        //     "id_card"=> 'e20250000',
        //     "department_id"=> 1,
        //     "sub_department_id"=> 1,
        //     "khmer_first_name"=> "សុធានី",
        //     "khmer_last_name"=> "ឡុង",
        //     "latin_name"=> "Sothany Long",
        //     'gender' => 'Male',
        //     'is_active' => true,
        //     "khmer_name"=> "សុធានី លុង",
        //     "address"=> "Phnom Penh",
        //     "date_of_birth"=> "20-02-2004",
        //     "origin"=> "Kampong Cham",
        //     "phone_number"=> "060 123 456",
        //     'current_address' => "Phnom Penh",
        //     'place_of_birth' => 'Kampong Cham',
        //     'guardian_name' => 'Long Vanna',
        //     'guardian_phone' => '070 654 321',
        //     'bac_from' => 'High School',
        //     'bac_grade' => 'A',
        //     'high_school' => 'Phnom Penh High School',
        // ]);

        $teacher = User::create([
            "name"=> "Teacher",
            "email"=> "t20260000@rtc-bb.camai.kh",
            "password"=>"12345678",
        ]);
        $teacher->userDetail()->create([
            "id_card"=> 't20260000',
            "department_id"=> 1,
            "sub_department_id"=> 1,
            "khmer_first_name"=> "គ្រូ",
            "khmer_last_name"=> "បង្រៀន",
            "latin_name"=> "Kru Bongrean",
            "gender" => "Male",
            'is_active' => true,
            "khmer_name"=> "គ្រូ បង្រៀន",
            "address"=> "Phnom Penh",
            "date_of_birth"=> "01-01-1980",
            "origin"=> "Phnom Penh",
            "phone_number"=> "070 123 456",
            'current_address' => "Phnom Penh",
            'join_at' => '01-09-2020',
            'graduated_from' => 'Royal University of Phnom Penh',
            'graduated_at' => 2018,
            'experience' => '5 years of teaching experience',

        ]);

        $teacher->assignRole('Staff');

        $headDepartment = User::create([
            "name"=> "Head of Department",
            "email"=> "h20260001@rtc-bb.camai.kh",
            "password"=>"head-department",
        ]);
        $headDepartment->assignRole('Head Department');
        $headDepartment->userDetail()->create([
            "id_card"=> 'h20260001',
            "department_id"=> 1,
            "sub_department_id"=> 1,
            "khmer_first_name"=> "ប្រធាន",
            "khmer_last_name"=> "ដេប៉ាតឺម៉ង់",
            "latin_name"=> "Head of Department",
            "gender" => "Male",
            'is_active' => true,
            "khmer_name"=> "ប្រធាន ដេប៉ាតឺម៉ង់",
            "address"=> "Phnom Penh",
            "date_of_birth"=> "01-01-1980",
            "origin"=> "Phnom Penh",
            "phone_number"=> "070 123 457",
            'current_address' => "Phnom Penh",
            'join_at' => '01-09-2020',
            'graduated_from' => 'Institute of Technology of Tokyo',
            'graduated_at' => 2015,
            'experience' => '8 years in education sector',
        ]);

        $admin = User::create([
            "name"=> "Admin",
            "email"=> "admin@rtc-bb.camai.kh",
            "password"=>"admin-rtc",
        ]);
        $admin->assignRole('Admin');
        $admin->userDetail()->create([
            "id_card"=> 'a20260000',
            "department_id"=> 1,
            "sub_department_id"=> 1,
            "khmer_first_name"=> "អេដមីន",
            "khmer_last_name"=> "ប្រព័ន្ធ",
            "latin_name"=> "Admin System",
            'gender' => 'Female',
            'is_active' => true,
            "khmer_name"=> "អេដមីន ប្រព័ន្ធ",
            "address"=> "Phnom Penh",
            "date_of_birth"=> "01-01-1990",
            "origin"=> "Phnom Penh",
            "phone_number"=> "080 123 456",
            'current_address' => "Phnom Penh",
            'place_of_birth' => 'Phnom Penh',
            'join_at' => '01-09-2020',
            'graduated_from' => 'Royal University of Law and Economics',
            'graduated_at' => 2012,
            'experience' => '5 years of experience in IT management',
        ]);


        // UserRole::create([
        //     'user_id' => $student->id,
        //     'role_id'=> $roleStudent->id,
        // ]);
        // UserRole::create([
        //     'user_id' => $teacher->id,
        //     'role_id'=> $roleStaff->id,
        // ]);
        // UserRole::create([
        //     'user_id' => $headDepartment->id,
        //     'role_id'=> $roleHeadDepartment->id,
        // ]);
        // UserRole::create([
        //     'user_id' => $admin->id,
        //     'role_id'=> $roleAdmin->id,
        // ]);

    //   $studentDetail = UserDetail::create([
    //         "user_id"=> $student->id,
    //         "id_card"=> 'e20250222',
    //         "department_id"=> 1,
    //         "sub_department_id"=> 1,

    //         "khmer_first_name"=> "សុធានី",
    //         "khmer_last_name"=> "ឡុង",
    //         "latin_name"=> "Sothany Long",
    //         "khmer_name"=> "សុធានី លុង",
    //         "address"=> "Phnom Penh",
    //         "date_of_birth"=> "2000-02-20",
    //         "origin"=> "Phnom Penh",
    //         "profile_picture"=> "https://www.law.uchicago.edu/sites/default/files/styles/extra_large/public/2018-03/theisen_tarra.jpg?itok=Olm_LKro",
    //         "phone_number"=> "060 123 456",
    //         'current_address' => "Phnom Penh"
    //     ]);

        $department = Department::find(1);
        $department->assignHead(3);

        // $group = Group::create([
        //     'name' => 'First Group',
        //     'semester_id'=>1,
        //     'department_id'=>1,
        //     'sub_department_id'=>1,
        //     'description'=>'Group description'
        // ]);
        // $groupUser = GroupUser::create([
        //     'group_id'=>1,
        //     'user_id'=>$student->id
        // ]);




    }
}
