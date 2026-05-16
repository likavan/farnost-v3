<?php

declare(strict_types=1);

namespace Farnost\Plugin\MimoriadnyOznam;

use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data layer pre Mimoriadny oznam (banner).
 *
 * Vždy existuje maximálne **jeden** záznam — uložený ako WP option JSON štruktúra.
 * Frontend ho číta cez REST endpoint `/wp-json/farnost/v1/banner` a vykresľuje
 * pod sticky header-om (viď doc/06-struktura-stranky.md → „Mimoriadny oznam").
 *
 * Pole `id` sa pri každom uložení regeneruje, aby cookie zatvorenia z prehliadača
 * návštevníka stratila platnosť — keď farár publikuje nový banner, zobrazí sa
 * znova aj tým, čo predošlý zatvorili.
 */
final class Banner
{
    public const OPTION_KEY = 'farnost_mimoriadny_oznam';

    /**
     * @return null|array{text: string, expiry: string|null, published_at: string, id: string}
     */
    public static function get(): ?array
    {
        $raw = get_option(self::OPTION_KEY, null);
        if (!is_array($raw) || empty($raw['text'])) {
            return null;
        }
        $expiry = isset($raw['expiry']) && is_string($raw['expiry']) && $raw['expiry'] !== ''
            ? $raw['expiry']
            : null;

        if ($expiry !== null && self::isExpired($expiry)) {
            // Aktívne čistíme expirované záznamy aby option neostávala s dead dátami.
            self::clear();
            return null;
        }

        return [
            'text'         => (string) $raw['text'],
            'expiry'       => $expiry,
            'published_at' => (string) ($raw['published_at'] ?? ''),
            'id'           => (string) ($raw['id'] ?? ''),
        ];
    }

    public static function save(string $text, ?string $expiry): array
    {
        $sanitized = [
            'text'         => wp_kses_post($text),
            'expiry'       => self::sanitizeExpiry($expiry),
            'published_at' => current_datetime()->format(DATE_ATOM),
            'id'           => substr(md5(uniqid('', true)), 0, 12),
        ];
        update_option(self::OPTION_KEY, $sanitized);
        return $sanitized;
    }

    public static function clear(): void
    {
        delete_option(self::OPTION_KEY);
    }

    private static function isExpired(string $expiry): bool
    {
        $exp = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiry, wp_timezone());
        if ($exp === false) {
            return false;
        }
        return $exp <= current_datetime();
    }

    private static function sanitizeExpiry(?string $expiry): ?string
    {
        if ($expiry === null || $expiry === '') {
            return null;
        }
        // Akceptujeme formát z HTML5 datetime-local input: YYYY-MM-DDTHH:MM
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $expiry) !== 1) {
            return null;
        }
        return $expiry;
    }
}
