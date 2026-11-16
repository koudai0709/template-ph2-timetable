<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class W10InitialDataSeeder extends Seeder
{
    
public function run(): void
{
    DB::table('users')->updateOrInsert(
        ['email' => 'admin@example.com'],
        [
            'name' => '教務課',
            'password' => Hash::make('password123'),
            'is_admin' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DB::table('courses')
        ->where('code', 'SE300')
        ->update(['capacity' => 3]);
    }
}
