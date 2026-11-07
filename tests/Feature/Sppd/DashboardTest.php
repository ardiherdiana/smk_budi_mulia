<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

class DashboardTest extends AbsensiTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('sppd.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_an_sppd_role_only_sees_their_own_pengajuan(): void
    {
        $tanpaRole = User::factory()->create();
        $punya = PengajuanSppd::factory()->for($tanpaRole, 'pemohon')->create();
        PengajuanSppd::factory()->create();

        $this->actingAs($tanpaRole)
            ->get(route('sppd.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 1)
                ->has('terbaru', 1)
                ->where('terbaru.0.id', $punya->id));
    }

    public function test_every_pengajuan_shown_on_the_dashboard_is_viewable_by_that_user(): void
    {
        $tanpaRole = User::factory()->create();
        $milikOrangLain = PengajuanSppd::factory()->create();

        $this->actingAs($tanpaRole)->get(route('sppd.dashboard'));

        $this->actingAs($tanpaRole)
            ->get(route('sppd.pengajuan.show', $milikOrangLain))
            ->assertForbidden();
    }

    public function test_stats_count_pengajuan_waiting_for_approval_and_for_a_nomor(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->create()->assignRole(RoleName::Tu->value);
        PengajuanSppd::factory()->count(3)->create(['status' => PengajuanStatus::DiajukanKeKepsek]);
        PengajuanSppd::factory()->count(2)->create(['status' => PengajuanStatus::MenungguNomor]);
        PengajuanSppd::factory()->create(['status' => PengajuanStatus::SedangDitugaskan]);

        $this->actingAs($tu)
            ->get(route('sppd.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 6)
                ->where('stats.menunggu_persetujuan', 3)
                ->where('stats.menunggu_nomor', 2)
                ->where('stats.sedang_ditugaskan', 1));
    }

    public function test_kepala_sekolah_sees_pengajuan_from_all_pemohon(): void
    {
        $this->seed(RoleSeeder::class);
        $kepsek = User::factory()->create()->assignRole(RoleName::KepalaSekolah->value);
        PengajuanSppd::factory()->count(2)->create();

        $this->actingAs($kepsek)
            ->get(route('sppd.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 2)
                ->has('terbaru', 2));
    }
}
