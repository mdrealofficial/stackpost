<?php

namespace App\Social\Support;

/**
 * Publishing support alone is not enough to expose a channel in Inbox.
 * A provider needs a verified inbound and reply contract first.
 */
class SocialInboxProviderCapabilities
{
    /** @var array<string, array{label:string, icon:string, inbound:bool, reply:bool}> */
    protected const PROVIDERS = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'fa-facebook', 'inbound' => true, 'reply' => true],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-instagram', 'inbound' => true, 'reply' => true],
        'threads' => ['label' => 'Threads', 'icon' => 'fa-threads', 'inbound' => false, 'reply' => false],
    ];

    /** @return array<int, string> */
    public static function inboundProviderKeys(): array
    {
        return array_keys(array_filter(self::PROVIDERS, fn (array $provider): bool => $provider['inbound']));
    }

    public static function supportsInbound(string $providerKey): bool
    {
        return (bool) (self::PROVIDERS[$providerKey]['inbound'] ?? false);
    }

    public static function supportsReply(string $providerKey): bool
    {
        return (bool) (self::PROVIDERS[$providerKey]['reply'] ?? false);
    }

    public static function label(string $providerKey): string
    {
        return (string) (self::PROVIDERS[$providerKey]['label'] ?? ucfirst($providerKey));
    }

    public static function icon(string $providerKey): string
    {
        return (string) (self::PROVIDERS[$providerKey]['icon'] ?? 'fa-comments');
    }
}
