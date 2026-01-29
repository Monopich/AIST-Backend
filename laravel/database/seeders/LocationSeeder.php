<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $building1 = Building::create([
            "name"=> "A Building",
        ]);
        $building2 = Building::create([
            "name"=> "B Building",
        ]);

        Location::insert([
            [
                'name' => 'A101',
                'building_id' => $building1->id,
                'floor' => 1,
                'latitude' => 11.5564,
                'longitude' => 104.9282,
            ],
            [
                'name' => 'B201',
                'building_id' => $building2->id,
                'floor' => 2,
                'latitude' => 11.5568,
                'longitude' => 104.9275,
            ],
        ]);

    }
}
