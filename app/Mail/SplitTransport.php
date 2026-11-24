<?php

namespace App\Mail;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

// sadala e-pastus: adreses no MAIL_REAL_RECIPIENTS iet pa īstu smtp (gmail),
// visi pārējie uz mailtrap sandbox kā līdz šim
// tā var pārbaudīt īstā gmail/outlook, bet nejauši neaizsūtīt testa lietotājiem
class SplitTransport implements TransportInterface
{
    public function __construct(
        private $sandbox,      // fn() => TransportInterface
        private $real,         // fn() => TransportInterface
        private array $recipients,
        private bool $realReady,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $to = array_map(
            fn($a) => strtolower($a->getAddress()),
            ($envelope ?? Envelope::create($message))->getRecipients()
        );

        // pa īsto tikai ja VISI adresāti ir sarakstā, citādi drošāk uz sandbox
        $allReal = $to !== [] && array_diff($to, $this->recipients) === [];

        if ($allReal && !$this->realReady) {
            Log::warning('MAIL_REAL_RECIPIENTS adresāts, bet REAL_MAIL_USERNAME/PASSWORD nav iestatīti - sūtu uz mailtrap');
        }

        $transport = $allReal && $this->realReady ? ($this->real)() : ($this->sandbox)();

        return $transport->send($message, $envelope);
    }

    public function __toString(): string
    {
        return 'split';
    }
}
