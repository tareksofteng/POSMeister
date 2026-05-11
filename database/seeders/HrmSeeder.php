<?php

namespace Database\Seeders;

use App\Modules\HRM\Models\Department;
use App\Modules\HRM\Models\Designation;
use App\Modules\HRM\Models\Shift;
use Illuminate\Database\Seeder;

class HrmSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Verwaltung',   'code' => 'ADM', 'description' => 'Administration und Management'],
            ['name' => 'Verkauf',      'code' => 'SAL', 'description' => 'Verkauf und Kundenbetreuung'],
            ['name' => 'Lager',        'code' => 'WHS', 'description' => 'Lager und Warenwirtschaft'],
            ['name' => 'Buchhaltung',  'code' => 'ACC', 'description' => 'Finanzen und Buchhaltung'],
        ];

        foreach ($departments as $row) {
            Department::firstOrCreate(['name' => $row['name']], $row);
        }

        $verwaltung   = Department::where('name', 'Verwaltung')->first();
        $verkauf      = Department::where('name', 'Verkauf')->first();
        $lager        = Department::where('name', 'Lager')->first();
        $buchhaltung  = Department::where('name', 'Buchhaltung')->first();

        $designations = [
            ['title' => 'Geschäftsführer', 'department_id' => $verwaltung?->id,  'hierarchy_level' => 1],
            ['title' => 'Filialleiter',    'department_id' => $verwaltung?->id,  'hierarchy_level' => 2],
            ['title' => 'Buchhalter',      'department_id' => $buchhaltung?->id, 'hierarchy_level' => 3],
            ['title' => 'Verkäufer',       'department_id' => $verkauf?->id,     'hierarchy_level' => 4],
            ['title' => 'Lagerist',        'department_id' => $lager?->id,       'hierarchy_level' => 4],
            ['title' => 'Aushilfe',        'department_id' => null,              'hierarchy_level' => 5],
        ];

        foreach ($designations as $row) {
            Designation::firstOrCreate(
                ['title' => $row['title']],
                $row
            );
        }

        $shifts = [
            ['name' => 'Tagschicht',   'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 10],
            ['name' => 'Spätschicht',  'start_time' => '14:00', 'end_time' => '22:00', 'grace_minutes' => 10],
            ['name' => 'Nachtschicht', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_minutes' => 15],
        ];

        foreach ($shifts as $row) {
            Shift::firstOrCreate(['name' => $row['name']], $row);
        }

        $this->command->info('HRM defaults seeded (departments, designations, shifts).');
    }
}
