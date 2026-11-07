<?php

namespace App\Http\Requests\Sppd;

use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PengajuanSppd::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $satuHari = $this->input('tanggal_berangkat') === $this->input('tanggal_kembali');

        return [
            'tujuan' => ['required', 'string', 'max:255'],
            'maksud' => ['required', 'string', 'max:300'],
            'alat_angkutan' => ['required', 'string', 'max:40'],
            'keterangan' => ['nullable', 'string', 'max:70'],
            'pengikut_ids' => ['nullable', 'array', 'max:3'],
            'pengikut_ids.*' => [
                // Id `users` (int) sejak digabung; di absensi lama ULID string.
                'integer',
                'distinct',
                Rule::notIn([$this->user()->id]),
                fn (string $attribute, mixed $value, Closure $fail) => User::pegawaiAktif()->whereKey($value)->exists()
                    || $fail('Pengikut harus pegawai aktif.'),
            ],
            'tanggal_berangkat' => ['required', 'date'],
            'jam_berangkat' => ['required', 'date_format:H:i'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'jam_kembali' => ['required', 'date_format:H:i', Rule::when($satuHari, ['after:jam_berangkat'])],
            'undangan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maksud.max' => 'Maksud perjalanan dinas maksimal 300 karakter agar muat pada form SPPD.',
            'pengikut_ids.max' => 'Pengikut maksimal 3 orang (sesuai kapasitas form SPPD).',
            'pengikut_ids.*.not_in' => 'Pemohon tidak perlu dipilih sebagai pengikut.',
            'jam_kembali.after' => 'Jam kembali harus setelah jam berangkat jika berangkat dan kembali di hari yang sama.',
        ];
    }
}
