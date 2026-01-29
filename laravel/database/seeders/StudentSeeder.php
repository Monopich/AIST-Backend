<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserRole;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
    {
        $users = [
            [
                "name" => "Long Thany",
                "email" => "thany3.long@example.com",
                "password" => "securePassword123", // no hash
                "id_card" => "E2025074",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "សុធានី",
                "khmer_last_name" => "ឡុង",
                "latin_name" => "Sothany Long",
                "khmer_name" => "សុធានី លុង",
                "address" => "Phnom Penh, Cambodia",
                "date_of_birth" => "2002-02-20",
                "origin" => "Phnom Penh",
                "profile_picture" => "default.png",
                "gender" => "Male",
                "phone_number" => "0123456789",
                "role_key" => "Student"
            ],
            [
                "name" => "Sok Dara",
                "email" => "dara.sok@example.com",
                "password" => "StrongPass!456",
                "id_card" => "E2025075",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "ដារ៉ា",
                "khmer_last_name" => "សុខ",
                "latin_name" => "Dara Sok",
                "khmer_name" => "ដារ៉ា សុខ",
                "address" => "Siem Reap, Cambodia",
                "date_of_birth" => "1999-07-15",
                "origin" => "Siem Reap",
                "profile_picture" => "default.png",
                "gender" => "Male",
                "phone_number" => "0987654321",
                "role_key" => "Student"
            ],
            [
                "name" => "Chan Vanna",
                "email" => "vanna.chan@example.com",
                "password" => "Pass@2025",
                "id_card" => "E2025076",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "វណ្ណា",
                "khmer_last_name" => "ចាន់",
                "latin_name" => "Vanna Chan",
                "khmer_name" => "វណ្ណា ចាន់",
                "address" => "Kampot, Cambodia",
                "date_of_birth" => "2000-11-02",
                "origin" => "Kampot",
                "profile_picture" => "default.png",
                "gender" => "Female",
                "phone_number" => "011223344",
                "role_key" => "Student"
            ],
            [
                "name" => "Kim Sokha",
                "email" => "sokha.kim@example.com",
                "password" => "MyPassWord#321",
                "id_card" => "E2025077",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "សុខា",
                "khmer_last_name" => "គឹម",
                "latin_name" => "Sokha Kim",
                "khmer_name" => "សុខា គឹម",
                "address" => "Battambang, Cambodia",
                "date_of_birth" => "1998-03-28",
                "origin" => "Battambang",
                "profile_picture" => "default.png",
                "gender" => "Female",
                "phone_number" => "015555555",
                "role_key" => "Student"
            ],
            [
                "name" => "Phan Sophea",
                "email" => "sophea.phan@example.com",
                "password" => "SafePass789",
                "id_card" => "E2025078",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "សុភាព",
                "khmer_last_name" => "ផាន់",
                "latin_name" => "Sophea Phan",
                "khmer_name" => "សុភាព ផាន់",
                "address" => "Kandal, Cambodia",
                "date_of_birth" => "2001-09-10",
                "origin" => "Kandal",
                "profile_picture" => "default.png",
                "gender" => "Male",
                "phone_number" => "017777777",
                "role_key" => "Student"
            ],
            [
                "name" => "Ly Chenda",
                "email" => "chenda.ly@example.com",
                "password" => "Secret_123",
                "id_card" => "E2025079",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "ចិន្តា",
                "khmer_last_name" => "លី",
                "latin_name" => "Chenda Ly",
                "khmer_name" => "ចិន្តា លី",
                "address" => "Takeo, Cambodia",
                "date_of_birth" => "2003-12-22",
                "origin" => "Takeo",
                "profile_picture" => "default.png",
                "gender" => "Female",
                "phone_number" => "092345678",
                "role_key" => "Student"
            ],
            [
                "name" => "Mean Rith",
                "email" => "rith.mean@example.com",
                "password" => "TopPass!999",
                "id_card" => "E2025080",
                "department_id" => 1,
                "sub_department_id" => 1,
                "khmer_first_name" => "រិទ្ធ",
                "khmer_last_name" => "មាន",
                "latin_name" => "Rith Mean",
                "khmer_name" => "រិទ្ធ មាន",
                "address" => "Prey Veng, Cambodia",
                "date_of_birth" => "1997-05-05",
                "origin" => "Prey Veng",
                "profile_picture" => "default.png",
                "gender" => "Male",
                "phone_number" => "016888888",
                "role_key" => "Student"
            ]
        ];

        DB::beginTransaction();
        try {
            foreach ($users as $data) {
                $role = Role::where('role_key', $data['role_key'])->first();

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                UserDetail::create([
                    'user_id' => $user->id,
                    'id_card' => $data['id_card'],
                    'department_id' => $data['department_id'],
                    'sub_department_id' => $data['sub_department_id'],
                    'khmer_first_name' => $data['khmer_first_name'],
                    'khmer_last_name' => $data['khmer_last_name'],
                    'latin_name' => $data['latin_name'],
                    'khmer_name' => $data['khmer_name'],
                    'address' => $data['address'],
                    'date_of_birth' => $data['date_of_birth'],
                    'origin' => $data['origin'],
                    'profile_picture' => $data['profile_picture'],
                    'gender' => $data['gender'],
                    'phone_number' => $data['phone_number'],
                ]);

                UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
