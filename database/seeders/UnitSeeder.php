<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Stück',      'symbol' => 'Stk.'],
            ['name' => 'Kilogramm',  'symbol' => 'kg'],
            ['name' => 'Gramm',      'symbol' => 'g'],
            ['name' => 'Liter',      'symbol' => 'l'],
            ['name' => 'Milliliter', 'symbol' => 'ml'],
            ['name' => 'Meter',      'symbol' => 'm'],
            ['name' => 'Karton',     'symbol' => 'Ktn.'],
            ['name' => 'Packung',    'symbol' => 'Pck.'],
            ['name' => 'Flasche',    'symbol' => 'Fl.'],
            ['name' => 'Dose',       'symbol' => 'Ds.'],
        ];

        DB::table('units')->insertOrIgnore(array_map(fn($u) => array_merge($u, [
            'created_at' => now(),
            'updated_at' => now(),
        ]), $units));
    }
}
