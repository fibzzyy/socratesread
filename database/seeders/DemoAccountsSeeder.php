<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Demo Guru
        User::updateOrCreate(
            ['email' => 'guru.demo@socratesread.id'],
            [
                'name' => 'Budi Raharjo, M.Pd.',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
                'school_origin' => 'SMA Negeri 1 Jakarta',
                'nip_nuptk' => '198503152010011002',
                'nisn' => null,
                'email_verified_at' => now(),
            ]
        );

        // 2. Akun Demo Siswa
        User::updateOrCreate(
            ['email' => 'siswa.demo@socratesread.id'],
            [
                'name' => 'Aulia Pratama',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'school_origin' => 'SMA Negeri 1 Jakarta',
                'nip_nuptk' => null,
                'nisn' => '0081234567',
                'entry_grade' => 10,
                'enrollment_year' => (int) date('Y'),
                'graduation_year' => (int) date('Y') + 2,
                'email_verified_at' => now(),
            ]
        );
    }
}
