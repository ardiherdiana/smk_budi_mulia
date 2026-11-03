<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'subject' => 'Tanya PPDB',
            'message' => 'Kapan pendaftaran gelombang kedua dibuka?',
            ...$override,
        ];
    }

    public function test_message_is_emailed_to_the_school(): void
    {
        Mail::fake();

        $this->post('/kontak', $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo(config('school.contact_mail_to'))
            && $mail->hasReplyTo('budi@example.com')
            && $mail->subjectLine === 'Tanya PPDB');
    }

    public function test_validation_errors_are_indonesian(): void
    {
        Mail::fake();

        $this->post('/kontak', [])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->post('/kontak', $this->payload(['message' => 'pendek']))->assertSessionHasErrors('message');
        $this->post('/kontak', $this->payload(['email' => 'bukan-email']))->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertStringContainsString('email', strtolower($errors[0]));
        $this->assertStringNotContainsString('field is', $errors[0]);
        Mail::assertNothingSent();
    }

    public function test_honeypot_blocks_bots(): void
    {
        Mail::fake();

        $this->post('/kontak', $this->payload(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

        Mail::assertNothingSent();
    }

    public function test_header_injection_attempts_are_rejected(): void
    {
        Mail::fake();

        $this->post('/kontak', $this->payload(['subject' => "Halo\r\nBcc: korban@example.com"]))->assertSessionHasErrors('subject');

        Mail::assertNothingSent();
    }

    public function test_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $i) {
            $this->post('/kontak', $this->payload())->assertRedirect();
        }

        $this->post('/kontak', $this->payload())->assertStatus(429);
        Mail::assertSentCount(3);
    }

    public function test_mail_transport_failure_is_reported_without_leaking_details(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP password=rahasia salah'));

        $response = $this->post('/kontak', $this->payload());

        $response->assertSessionHasErrors('message');
        $this->assertStringNotContainsString('rahasia', json_encode(session('errors')->all()));
    }
}
