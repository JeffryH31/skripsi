<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            ['name' => 'Jeff',        'email' => 'admin@example.com',        'hourly_rate' => 100000],
            ['name' => 'Kevin',       'email' => 'kevin@example.com',        'hourly_rate' => 75000],
            ['name' => 'Christopher', 'email' => 'christopher@example.com',  'hourly_rate' => 75000],
            ['name' => 'Marvel',      'email' => 'marvel@example.com',       'hourly_rate' => 75000],
            ['name' => 'Devin',       'email' => 'devin@example.com',        'hourly_rate' => 75000],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'hourly_rate' => $data['hourly_rate'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
