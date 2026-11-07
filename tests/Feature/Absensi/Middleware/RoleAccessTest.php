<?php

namespace Tests\Feature\Absensi\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Tests\AbsensiTestCase;

class RoleAccessTest extends AbsensiTestCase
{
    public function test_guest_hitting_protected_route_is_redirected_to_login(): void
    {
        $response = $this->get('/menu-utama');

        $response->assertRedirect('/login');
    }

    public function test_admin_guru_kepsek_can_all_reach_dashboard(): void
    {
        $this->actingAsAdmin();
        $this->get('/menu-utama')->assertOk();

        $this->actingAsGuru();
        $this->get('/menu-utama')->assertOk();

        $this->actingAsKepsek();
        $this->get('/menu-utama')->assertOk();
    }

    public function test_guru_is_blocked_from_admin_only_kiosk_route(): void
    {
        $this->actingAsGuru();

        $response = $this->get('/kiosk');

        $response->assertForbidden();
    }

    public function test_kepsek_is_blocked_from_admin_only_kiosk_route(): void
    {
        $this->actingAsKepsek();

        $response = $this->get('/kiosk');

        $response->assertForbidden();
    }

    public function test_admin_is_blocked_from_guru_only_qr_route(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/qr');

        $response->assertForbidden();
    }

    public function test_kepsek_can_reach_admin_kepsek_shared_routes(): void
    {
        $this->actingAsKepsek();

        $response = $this->get('/data-guru');

        $response->assertOk();
    }

    public function test_guru_is_blocked_from_admin_kepsek_shared_routes(): void
    {
        $this->actingAsGuru();

        $response = $this->get('/data-guru');

        $response->assertForbidden();
    }

    /** bootstrap/app.php turns a 4xx HttpException into a redirect-back with a
     * flashed `message` error for Inertia requests specifically, so a denied
     * action renders as an inline form error instead of a blank error page. */
    public function test_forbidden_access_over_inertia_redirects_back_with_a_flashed_error(): void
    {
        $this->actingAsGuru();

        // Inertia forces a 409 "reload" response on a GET whenever the
        // client's asset version doesn't match the server's, overriding any
        // other response - so the header must carry the real current version
        // or this test would be exercising Inertia's version-conflict path
        // instead of the role check.
        $version = (new HandleInertiaRequests)->version(new Request);
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version];

        // Sejak digabung ke landing: aksi (non-GET) yang ditolak kembali dengan pesan form, sedangkan membuka halaman
        // (GET) yang ditolak menampilkan halaman 403 biasa, sama untuk semua modul.
        $response = $this->withHeaders($headers)->post('/hari-libur', ['tanggal' => '2026-01-01', 'keterangan' => 'Libur']);
        $response->assertRedirect();
        $response->assertSessionHasErrors('message');

        $this->withHeaders($headers)->get('/data-guru')->assertForbidden();
    }
}
