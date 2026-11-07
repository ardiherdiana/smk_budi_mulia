<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\AbsensiTestCase;

class PegawaiTest extends AbsensiTestCase
{
    public function test_tu_cannot_delete_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->create()->assignRole(RoleName::Tu->value);

        $this->actingAs($tu)
            ->delete(route('sppd.pegawai.destroy', $tu))
            ->assertSessionHas('error', 'Anda tidak dapat menghapus akun Anda sendiri.');

        $this->assertModelExists($tu);
    }
}
