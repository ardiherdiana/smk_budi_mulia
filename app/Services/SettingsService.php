<?php

namespace App\Services;

use App\Models\Settings;

class SettingsService
{
    public function getSettings(): Settings
    {
        return Settings::find(1) ?? Settings::create(['id' => 1, 'namaSekolah' => 'SMK Budi Mulia Karawang']);
    }

    public function updateSettings(array $input): Settings
    {
        $settings = $this->getSettings();
        $settings->update($input);

        return $settings;
    }
}
