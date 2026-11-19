<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Accès envoyé au fondateur d'une entreprise créée depuis la console. */
class FounderAccess extends Mailable
{
    use Queueable;

    public function __construct(public Tenant $tenant, public string $name, public string $email, public string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Vos accès Salloubhe Gescom : {$this->tenant->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.founder-access', with: ['loginUrl' => rtrim(config('app.url'), '/').'/login']);
    }
}
