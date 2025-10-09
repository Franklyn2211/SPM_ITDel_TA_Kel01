<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $username = 'adminspm';
        $email    = 'adminspm@gmail.com';
        $password = 'Password123!';

        User::updateOrCreate(
            ['username' => $username],
            [
                'username' => $username,
                'name'     => 'Admin SPM IT Del',
                'email'    => $email,
                'password' => Hash::make($password),
                'active'   => true,
            ]
        );
    }
}
