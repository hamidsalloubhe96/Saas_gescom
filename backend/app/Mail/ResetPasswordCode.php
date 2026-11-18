<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordCode extends Mailable
{
    use Queueable;

    public function __construct(public string $code, public int $minutes, public string $audience) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de réinitialisation de mot de passe');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reset-password-code');
    }
}
