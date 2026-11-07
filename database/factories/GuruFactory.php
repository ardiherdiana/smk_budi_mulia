<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guru> */
class GuruFactory extends Factory
{
    protected $model = Guru::class;

    public function definition(): array
    {
        return [
            'userId' => User::factory(),
            'nama' => fake()->name(),
            'noHp' => fake()->numerify('08##########'),
            'mapel' => fake()->randomElement(['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS']),
            'aktif' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['aktif' => false]);
    }
}
