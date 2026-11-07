<?php

namespace Database\Factories\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengajuanSppd>
 */
class PengajuanSppdFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $berangkat = fake()->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'pemohon_id' => User::factory(),
            'tujuan' => fake()->company(),
            'maksud' => fake()->sentence(10),
            'alat_angkutan' => 'Sepeda motor pribadi',
            'tanggal_berangkat' => $berangkat,
            'jam_berangkat' => '08:00',
            'tanggal_kembali' => (clone $berangkat)->modify('+2 days'),
            'jam_kembali' => '16:00',
            'status' => PengajuanStatus::SedangDitugaskan,
            'undangan_path' => 'undangan/contoh.pdf',
        ];
    }
}
