<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envoi par l'API HTTP de Brevo (port 443). Utile là où le SMTP sortant est bloqué (ex. Render, offre gratuite).
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey, private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email')
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = [
            'sender' => $this->address($email->getFrom()[0] ?? new Address($envelope->getSender()->getAddress())),
            'to' => array_map(fn (Address $a) => $this->address($a), $this->recipients($envelope, $email)),
            'subject' => (string) $email->getSubject(),
        ];
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = (string) $email->getTextBody();
        }
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = ' ';
        }
        if ($reply = $email->getReplyTo()[0] ?? null) {
            $payload['replyTo'] = $this->address($reply);
        }
        if ($email->getAttachments()) {
            $payload['attachment'] = array_map(fn ($part) => [
                'name' => $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename') ?? 'piece-jointe',
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments());
        }

        $response = Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
            ->timeout(15)->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new TransportException('Brevo a refusé le message ('.$response->status().') : '.$response->body());
        }
    }

    /** @return list<Address> */
    private function recipients(Envelope $envelope, Email $email): array
    {
        return $envelope->getRecipients();
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $a): array
    {
        return array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]);
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
