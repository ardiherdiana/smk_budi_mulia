<?php

namespace Tests\Feature\Sppd;

use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

class AdminAccessTest extends AbsensiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_sees_every_pengajuan_on_the_dashboard_not_just_their_own(): void
    {
        $admin = User::factory()->admin()->create();
        PengajuanSppd::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('sppd.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 3)->has('terbaru', 3));
    }

    public function test_admin_can_open_a_pengajuan_they_do_not_own(): void
    {
        $admin = User::factory()->admin()->create();
        $pengajuan = PengajuanSppd::factory()->create();

        $this->actingAs($admin)
            ->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertOk();
    }

    public function test_admin_can_reach_every_role_gated_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('sppd.pengajuan.create'))->assertOk();
        $this->actingAs($admin)->get(route('sppd.pegawai.index'))->assertOk();
        $this->actingAs($admin)->get(route('sppd.laporan.index'))->assertOk();
    }

    public function test_a_non_admin_without_sppd_roles_is_still_blocked_from_role_gated_pages(): void
    {
        $tanpaRole = User::factory()->create();

        $this->actingAs($tanpaRole)->get(route('sppd.pengajuan.create'))->assertForbidden();
        $this->actingAs($tanpaRole)->get(route('sppd.pegawai.index'))->assertForbidden();
    }
}
