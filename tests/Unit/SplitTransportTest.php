<?php

namespace Tests\Unit;

use App\Mail\SplitTransport;
use Tests\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

// pārbauda, ka pa īstam aiziet tikai adreses no saraksta
class SplitTransportTest extends TestCase
{
    private function fake(): TransportInterface
    {
        return new class implements TransportInterface {
            public int $sent = 0;

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->sent++;

                return new SentMessage($message, $envelope ?? Envelope::create($message));
            }

            public function __toString(): string
            {
                return 'fake';
            }
        };
    }

    private function email(string ...$to): Email
    {
        return (new Email())->from('noreply@bookloop.lv')->to(...$to)->subject('x')->text('x');
    }

    public function test_only_listed_address_goes_to_real_smtp(): void
    {
        $sandbox = $this->fake();
        $real = $this->fake();
        $split = new SplitTransport(fn() => $sandbox, fn() => $real, ['cisins.zupa@gmail.com'], true);

        $split->send($this->email('Cisins.Zupa@gmail.com'));
        $split->send($this->email('anna@e2e.test'));
        // ja kaut viens adresāts nav sarakstā - viss uz sandbox
        $split->send($this->email('cisins.zupa@gmail.com', 'anna@e2e.test'));

        $this->assertSame(1, $real->sent);
        $this->assertSame(2, $sandbox->sent);
    }

    public function test_without_password_everything_stays_in_sandbox(): void
    {
        $sandbox = $this->fake();
        $real = $this->fake();
        $split = new SplitTransport(fn() => $sandbox, fn() => $real, ['cisins.zupa@gmail.com'], false);

        $split->send($this->email('cisins.zupa@gmail.com'));

        $this->assertSame(0, $real->sent);
        $this->assertSame(1, $sandbox->sent);
    }
}
