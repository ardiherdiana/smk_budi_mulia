<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'max:0'], // honeypot: bot mengisi, manusia tidak
        ], [
            'website.max' => 'Pesan tidak dapat dikirim.',
        ]);

        // Header injection: nama/subjek/email tidak boleh memuat baris baru.
        foreach (['name', 'email', 'subject'] as $field) {
            if (preg_match('/[\r\n]/', $validated[$field])) {
                return back()->withErrors([$field => 'Karakter tidak valid.']);
            }
        }

        try {
            Mail::to(config('school.contact_mail_to'))->send(new ContactMessage(
                $validated['name'],
                $validated['email'],
                $validated['subject'],
                $validated['message'],
            ));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan kontak', ['error' => $e->getMessage()]);

            return back()->withErrors(['message' => 'Pesan belum dapat dikirim. Silakan coba lagi nanti atau hubungi kami lewat telepon.']);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Terima kasih! Pesan Anda sudah kami terima.']);
    }
}
