<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class MailerService
{
    public function __construct(
        private AppSettingsService $settings,
        private Logger $logger
    ) {
    }

    public function sendMagicLink(string $identity, string $link, string $code, string $expiresAt): array
    {
        $subject = 'Logowanie do aplikacji Seriale';
        $body = implode("\n\n", [
            'Kliknij w link, aby zalogowac sie do aplikacji:',
            $link,
            'Kod awaryjny: ' . $code,
            'Wazny do: ' . $expiresAt,
        ]);

        $transport = $this->settings->mailTransport();

        if ($transport === 'mail') {
            $headers = [
                'From: ' . $this->settings->get('mail_from_name', 'Seriale') . ' <' . $this->settings->get('mail_from_address', 'no-reply@example.com') . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $sent = @mail($identity, $subject, $body, implode("\r\n", $headers));

            if (!$sent) {
                $this->logger->warning('mail() returned false while sending magic link', ['identity' => $identity]);
            }

            return [
                'transport' => 'mail',
                'sent' => $sent,
            ];
        }

        $this->logger->info('Magic link generated', [
            'identity' => $identity,
            'link' => $link,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);

        return [
            'transport' => 'log',
            'sent' => false,
        ];
    }

    public function sendPasswordReset(string $identity, string $link, string $expiresAt): array
    {
        $subject = 'Reset hasla do aplikacji Seriale';
        $body = implode("\n\n", [
            'Kliknij w link, aby ustawic nowe haslo:',
            $link,
            'Wazny do: ' . $expiresAt,
        ]);

        $transport = $this->settings->mailTransport();

        if ($transport === 'mail') {
            $headers = [
                'From: ' . $this->settings->get('mail_from_name', 'Seriale') . ' <' . $this->settings->get('mail_from_address', 'no-reply@example.com') . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $sent = @mail($identity, $subject, $body, implode("\r\n", $headers));

            if (!$sent) {
                $this->logger->warning('mail() returned false while sending password reset link', ['identity' => $identity]);
            }

            return [
                'transport' => 'mail',
                'sent' => $sent,
            ];
        }

        $this->logger->info('Password reset link generated', [
            'identity' => $identity,
            'link' => $link,
            'expires_at' => $expiresAt,
        ]);

        return [
            'transport' => 'log',
            'sent' => false,
        ];
    }
}
