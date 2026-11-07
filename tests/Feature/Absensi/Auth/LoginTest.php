<?php

namespace Tests\Feature\Absensi\Auth;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\AbsensiTestCase;

/**
 * Login staf absensi lewat form login landing: satu kolom "Email / Username" bernama `email`, galat di kunci `email`.
 */
class LoginTest extends AbsensiTestCase
{
    public function test_guest_sees_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/login');

        $response->assertRedirect('/menu-utama');
    }

    public function test_admin_can_log_in_with_correct_credentials(): void
    {
        User::factory()->admin()->create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/menu-utama');
        $this->assertAuthenticated();
    }

    public function test_guru_can_log_in_with_correct_credentials(): void
    {
        $guru = Guru::factory()->create([
            'userId' => User::factory()->create(['password' => Hash::make('guru123')]),
        ]);

        $response = $this->post('/login', [
            'email' => $guru->user->username,
            'password' => 'guru123',
        ]);

        $response->assertRedirect('/menu-utama');
        $this->assertAuthenticatedAs($guru->user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->admin()->create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_username(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'tidak-ada',
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_guru_cannot_log_in(): void
    {
        $guru = Guru::factory()->inactive()->create([
            'userId' => User::factory()->create(['password' => Hash::make('guru123')]),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $guru->user->username,
            'password' => 'guru123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_kepsek_account_without_active_guru_profile_cannot_log_in(): void
    {
        $kepsek = Guru::factory()->inactive()->create([
            'userId' => User::factory()->kepsek()->create(['password' => Hash::make('kepsek123')]),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $kepsek->user->username,
            'password' => 'kepsek123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_profile_cannot_log_in(): void
    {
        User::factory()->staf()->create([
            'username' => 'bendahara',
            'password' => Hash::make('rahasia123'),
            'is_active' => false,
        ]);

        $this->from('/login')->post('/login', ['email' => 'bendahara', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
