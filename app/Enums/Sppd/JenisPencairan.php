<?php

namespace App\Enums\Sppd;

enum JenisPencairan: string
{
    // Dulu "uang muka"; diganti istilah formulir SPPD (baris 11, Lumsum Biaya).
    case Lumsum = 'lumsum';

    public function label(): string
    {
        return match ($this) {
            self::Lumsum => 'Lumsum',
        };
    }
}
