<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Application data is stored in Firestore, not MySQL.
     * Add Firestore-specific seed scripts here if needed.
     */
    public function run(): void
    {
        //
    }
}
