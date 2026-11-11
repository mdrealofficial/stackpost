<?php

namespace Modules\AdminMailServer\Support;

use Illuminate\Mail\MailManager;
use Modules\AdminSettings\Support\OptionStore;

class MailServerConfigurator
{
    public static function fromOptions(OptionStore $options): array
    {
        return [
            'mail_protocol' => (string) $options->get('mail_protocol', config('mail.default', 'log')),
            'mail_sender_email' => (string) $options->get('mail_sender_email', config('mail.from.address', 'hello@example.com')),
            'mail_sender_name' => (string) $options->get('mail_sender_name', config('mail.from.name', config('app.name', 'Stackposts'))),
            'smtp_server' => (string) $options->get('smtp_server', config('mail.mailers.smtp.host', '')),
            'smtp_username' => (string) $options->get('smtp_username', config('mail.mailers.smtp.username', '')),
            'smtp_password' => (string) $options->get('smtp_password', config('mail.mailers.smtp.password', '')),
            'smtp_port' => (string) $options->get('smtp_port', (string) config('mail.mailers.smtp.port', 587)),
            'smtp_encryption' => (string) $options->get('smtp_encryption', (string) (config('mail.mailers.smtp.scheme') ?: 'tls')),
            'mail_timeout' => (string) $options->get('mail_timeout', (string) (config('mail.mailers.smtp.timeout') ?: '30')),
            'mail_ehlo_domain' => (string) $options->get('mail_ehlo_domain', (string) config('mail.mailers.smtp.local_domain', '')),
            'sendmail_path' => (string) $options->get('sendmail_path', (string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i')),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function apply(array $state): void
    {
        $transport = self::normalizeTransport($state['mail_protocol'] ?? null);
        $scheme = self::normalizeScheme($state['smtp_encryption'] ?? null);
        $dsnScheme = self::dsnScheme($scheme);
        $timeout = isset($state['mail_timeout']) && $state['mail_timeout'] !== ''
            ? (int) $state['mail_timeout']
            : null;
        $localDomain = trim((string) ($state['mail_ehlo_domain'] ?? ''));
        $sendmailPath = trim((string) ($state['sendmail_path'] ?? '/usr/sbin/sendmail -bs -i'));

        config([
            'mail.default' => $transport,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => trim((string) ($state['smtp_server'] ?? '')),
            'mail.mailers.smtp.port' => (int) ($state['smtp_port'] ?? 587),
            'mail.mailers.smtp.username' => trim((string) ($state['smtp_username'] ?? '')),
            'mail.mailers.smtp.password' => (string) ($state['smtp_password'] ?? ''),
            'mail.mailers.smtp.scheme' => $dsnScheme,
            'mail.mailers.smtp.encryption' => $scheme,
            'mail.mailers.smtp.timeout' => $timeout,
            'mail.mailers.smtp.local_domain' => $localDomain !== '' ? $localDomain : null,
            'mail.mailers.sendmail.transport' => 'sendmail',
            'mail.mailers.sendmail.path' => $sendmailPath !== '' ? $sendmailPath : '/usr/sbin/sendmail -bs -i',
            'mail.from.address' => trim((string) ($state['mail_sender_email'] ?? 'hello@example.com')),
            'mail.from.name' => trim((string) ($state['mail_sender_name'] ?? config('app.name', 'Stackposts'))),
        ]);

        app(MailManager::class)->forgetMailers();
    }

    public static function normalizeTransport(mixed $transport): string
    {
        return match (strtolower(trim((string) $transport))) {
            'smtp' => 'smtp',
            'sendmail', 'mail' => 'sendmail',
            'log' => 'log',
            default => 'log',
        };
    }

    public static function normalizeScheme(mixed $scheme): ?string
    {
        return match (strtolower(trim((string) $scheme))) {
            'tls' => 'tls',
            'ssl' => 'ssl',
            default => null,
        };
    }

    protected static function dsnScheme(?string $scheme): ?string
    {
        return match ($scheme) {
            'ssl' => 'smtps',
            default => null,
        };
    }
}
