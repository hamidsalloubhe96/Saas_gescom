<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BrevoMailerTest extends TestCase
{
    private function useBrevo(): void
    {
        config(['mail.default' => 'brevo', 'mail.mailers.brevo' => ['transport' => 'brevo', 'key' => 'cle-test'],
            'mail.from' => ['address' => 'expediteur@example.com', 'name' => 'GestCom']]);
        Mail::purge('brevo');
    }

    public function test_message_envoye_par_api_brevo(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
        $this->useBrevo();

        Mail::to('client@example.com')->send(new VerifyEmailCode('123456', 15));

        Http::assertSent(function ($r) {
            return $r->url() === 'https://api.brevo.com/v3/smtp/email'
                && $r->hasHeader('api-key', 'cle-test')
                && $r['to'][0]['email'] === 'client@example.com'
                && $r['sender']['email'] === 'expediteur@example.com'
                && str_contains(($r['htmlContent'] ?? '').($r['textContent'] ?? ''), '123456');
        });
    }

    public function test_refus_de_brevo_leve_une_erreur(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401)]);
        $this->useBrevo();

        $this->expectException(TransportException::class);
        Mail::to('client@example.com')->send(new VerifyEmailCode('123456', 15));
    }
}
