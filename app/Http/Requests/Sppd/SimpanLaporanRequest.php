<?php

namespace App\Http\Requests\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SimpanLaporanRequest extends FormRequest
{
    public const MAKS_FOTO = 10;

    public function authorize(): bool
    {
        return $this->user()->can('isiLaporan', $this->route('pengajuan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ringkasan' => ['required', 'string', 'max:5000'],
            'fotos' => ['nullable', 'array', 'max:'.self::MAKS_FOTO],
            'fotos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_foto_ids' => ['nullable', 'array'],
            'hapus_foto_ids.*' => ['integer'],
        ];
    }

    /**
     * Foto dokumentasi wajib ada (sisa foto lama ditambah foto baru), dan tidak boleh lebih dari batas.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var PengajuanSppd $pengajuan */
            $pengajuan = $this->route('pengajuan');

            $lama = $pengajuan->laporan?->fotos()->pluck('id') ?? collect();
            $sisa = $lama->diff($this->input('hapus_foto_ids', []))->count();
            $total = $sisa + count($this->file('fotos', []));

            if ($total < 1) {
                $validator->errors()->add('fotos', 'Lampirkan minimal satu foto dokumentasi.');
            } elseif ($total > self::MAKS_FOTO) {
                $validator->errors()->add('fotos', 'Foto dokumentasi maksimal '.self::MAKS_FOTO.' berkas.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ringkasan.required' => 'Rangkuman perjalanan wajib diisi.',
            'ringkasan.max' => 'Rangkuman maksimal 5000 karakter.',
            'fotos.max' => 'Foto dokumentasi maksimal '.self::MAKS_FOTO.' berkas.',
            'fotos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'fotos.*.max' => 'Ukuran tiap foto maksimal 5 MB.',
        ];
    }
}
