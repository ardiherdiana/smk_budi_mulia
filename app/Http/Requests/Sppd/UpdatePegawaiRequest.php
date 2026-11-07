<?php

namespace App\Http\Requests\Sppd;

use App\Enums\Sppd\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->hasRole(RoleName::Tu->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pegawai = $this->route('pegawai');

        return [
            'name' => ['required', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'regex:/^[a-z0-9._-]+$/', 'max:255', Rule::unique('profil_akun', 'username')->ignore($pegawai->id)],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [new Enum(RoleName::class)],
        ];
    }
}
