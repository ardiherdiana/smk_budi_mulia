<?php

namespace App\Http\Requests\Sppd;

class RevisiPengajuanRequest extends StorePengajuanRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('revisi', $this->route('pengajuan'));
    }
}
