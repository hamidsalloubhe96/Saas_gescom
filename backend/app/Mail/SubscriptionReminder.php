<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Relance d'abonnement : échéance proche ou abonnement dépassé. */
class SubscriptionReminder extends Mailable
{
    use Queueable;

    public function __construct(public string $company, public string $kind, public string $date) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'expiring' ? 'Votre abonnement Salloubhe Gescom arrive à échéance' : 'Votre abonnement Salloubhe Gescom est arrivé à échéance');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.subscription-reminder');
    }
}
