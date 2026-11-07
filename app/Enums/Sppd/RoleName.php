<?php

namespace App\Enums\Sppd;

enum RoleName: string
{
    case Pemohon = 'pemohon';
    case KepalaSekolah = 'kepala_sekolah';
    case Tu = 'tu';
    case Bendahara = 'bendahara';

    public function label(): string
    {
        return match ($this) {
            self::Pemohon => 'Pemohon',
            self::KepalaSekolah => 'Kepala Sekolah',
            self::Tu => 'Tata Usaha',
            self::Bendahara => 'Bendahara',
        };
    }
}
