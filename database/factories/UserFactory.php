<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Akun sekolah lengkap: baris `users` + profil `profil_akun` (lewat atribut virtual User). Default guru.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->bothify('user####??'),
            'password' => Hash::make('password'),
            'peran' => 'GURU',
        ];
    }

    public function admin(): static
    {
        return $this->state(['peran' => 'ADMIN']);
    }

    public function kepsek(): static
    {
        return $this->state(['peran' => 'KEPSEK']);
    }

    /** Pegawai yang hanya memakai SPPD (dulu `users.role` NULL di absensi). */
    public function staf(): static
    {
        return $this->state(['peran' => 'STAF']);
    }

    public function siswa(): static
    {
        return $this->state(['peran' => 'SISWA']);
    }
}
