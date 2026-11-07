<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_admin_is_sent_to_admin_panel_and_student_to_portal(): void
    {
        $this->admin();
        $this->student();

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123'])->assertRedirect('/menu-utama');
        $this->post('/logout');
        $this->post('/login', ['email' => 'siswa@test.local', 'password' => 'Rahasia123'])->assertRedirect('/menu-utama');
    }

    public function test_wrong_credentials_give_one_generic_error(): void
    {
        $this->admin();

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'salah'])->assertSessionHasErrors(['email' => 'Email/username atau password salah']);
        $this->post('/login', ['email' => 'tidak-ada@test.local', 'password' => 'salah'])->assertSessionHasErrors(['email' => 'Email/username atau password salah']);
        $this->assertGuest();
    }

    public function test_account_is_locked_after_five_failed_attempts_even_with_correct_password(): void
    {
        $this->admin();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'admin@test.local', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_legacy_plaintext_password_from_old_system_logs_in_once_then_is_hashed(): void
    {
        DB::table('users')->insert(['email' => 'admin@gmail.com', 'password' => 'admin123', 'role' => 'admin']);

        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'admin123'])->assertRedirect('/menu-utama');

        $stored = DB::table('users')->where('email', 'admin@gmail.com')->value('password');
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check('admin123', $stored));
    }

    public function test_legacy_plaintext_does_not_match_a_different_password(): void
    {
        DB::table('users')->insert(['email' => 'admin@gmail.com', 'password' => 'admin123', 'role' => 'admin']);

        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'admin124'])->assertSessionHasErrors('email');
        $this->assertSame('admin123', DB::table('users')->where('email', 'admin@gmail.com')->value('password'));
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $this->admin();
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_guests_are_redirected_and_roles_are_enforced(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/portal')->assertRedirect('/login');
        $this->get('/admin/berita')->assertRedirect('/login');
        $this->get('/skl')->assertRedirect('/login');

        $siswa = $this->student();
        $this->actingAs($siswa)->get('/admin')->assertForbidden();
        $this->actingAs($siswa)->get('/skl')->assertForbidden();
        $this->actingAs($siswa)->get('/skl/berkas')->assertForbidden();
        $this->actingAs($this->student('lain@test.local'))->post('/admin/berita', ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->admin())->get('/portal')->assertForbidden();
    }

    public function test_logged_in_users_visiting_login_are_redirected_by_role(): void
    {
        $this->actingAs($this->admin())->get('/login')->assertRedirect('/menu-utama');
        $this->actingAs($this->student())->get('/login')->assertRedirect('/menu-utama');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAsAdmin();

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_password_can_be_changed_with_current_password_and_strength_rules(): void
    {
        $user = $this->actingAsStudent();

        $this->patch('/profil/password', ['current_password' => 'salah', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrors('current_password');
        $this->patch('/profil/password', ['current_password' => 'Rahasia123', 'password' => 'hurufsaja', 'password_confirmation' => 'hurufsaja'])
            ->assertSessionHasErrors('password');
        $this->patch('/profil/password', ['current_password' => 'Rahasia123', 'password' => 'Baru12345', 'password_confirmation' => 'beda'])
            ->assertSessionHasErrors('password');

        $this->patch('/profil/password', ['current_password' => 'Rahasia123', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Baru12345', User::find($user->id)->password));
    }

    public function test_user_model_never_serialises_password_columns(): void
    {
        $user = $this->student();
        DB::table('users')->where('id', $user->id)->update(['password_plain' => 'terlihat']);

        $array = User::find($user->id)->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('password_plain', $array);
    }
}
