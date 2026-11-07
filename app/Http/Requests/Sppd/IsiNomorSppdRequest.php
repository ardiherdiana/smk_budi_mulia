<?php

namespace App\Http\Requests\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IsiNomorSppdRequest extends FormRequest
{
    /** Batas panjang nomor supaya tata letak PDF tetap satu halaman. */
    public const MAKS_NOMOR = 50;

    public function authorize(): bool
    {
        return $this->user()->can('isiNomor', $this->route('pengajuan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PengajuanSppd $pengajuan */
        $pengajuan = $this->route('pengajuan');

        return [
            'nomor_sppd' => [
                'required', 'string', 'max:'.self::MAKS_NOMOR,
                Rule::unique('sppds', 'nomor_sppd')->ignore($pengajuan->sppd?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_sppd.required' => 'Nomor SPPD wajib diisi.',
            'nomor_sppd.max' => 'Nomor SPPD maksimal '.self::MAKS_NOMOR.' karakter.',
            'nomor_sppd.unique' => 'Nomor SPPD ini sudah dipakai SPPD lain.',
        ];
    }
}
