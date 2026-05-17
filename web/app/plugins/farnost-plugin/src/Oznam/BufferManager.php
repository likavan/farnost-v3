<?php

declare(strict_types=1);

namespace Farnost\Plugin\Oznam;

use DateTimeImmutable;
use DateTimeZone;
use Farnost\Plugin\PostTypes\Oznam;
use Farnost\Plugin\Settings\Settings;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Udržiavač buffera budúcich oznamov.
 *
 * Filozofia: farár neklikni „Pridať" — systém vždy drží `N` budúcich oznamov
 * v statuse `future` (WP cron ich automaticky publikuje pri dosiahnutí
 * `post_date`). N je nastaviteľné v `farnost_settings.oznamy.dopredne_drafty`.
 *
 * Refill sa spúšťa:
 *   - pri aktivácii pluginu (Activator)
 *   - cez WP cron raz denne
 *   - po publikácii predošlého oznamu (transition_post_status hook)
 *   - po uložení nastavení (môže sa zmeniť `dopredne_drafty` alebo publikačný rytmus)
 */
final class BufferManager
{
    public const CRON_HOOK = 'farnost_oznam_buffer_refill';

    public static function register(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'refill']);
        add_action('transition_post_status', [self::class, 'onTransition'], 10, 3);
        add_action('update_option_' . Settings::OPTION_KEY, [self::class, 'refill']);
        // Keď farár / asistent zmaže alebo zahodí oznam z buffera, doplníme medzeru hneď —
        // bez tohto by sa diera prejavila až pri ďalšom dennom cron tiku.
        // `deleted_post` (po reálnom mazaní), `trashed_post` (po presune do koša).
        // `before_delete_post` by bol predčasný — post je ešte v DB, refill by ho videl a preskočil.
        add_action('deleted_post', [self::class, 'onPostGone'], 10, 2);
        add_action('trashed_post', [self::class, 'onPostGone']);
    }

    public static function onPostGone(int $postId, ?\WP_Post $post = null): void
    {
        // `deleted_post` poskytne aj $post (post object kým bol mazaný), `trashed_post` len $postId.
        // Pre trashed musíme `get_post`, aby sme zistili post_type.
        if ($post === null) {
            $post = get_post($postId);
        }
        if (!$post || $post->post_type !== Oznam::POST_TYPE) {
            return;
        }
        self::refill();
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'daily', self::CRON_HOOK);
        }
    }

    public static function unscheduleCron(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    public static function onTransition(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($post->post_type !== Oznam::POST_TYPE) {
            return;
        }
        if ($newStatus !== 'publish' || $oldStatus === $newStatus) {
            return;
        }
        // Doplníme buffer (môže byť jeden menej, treba dotvoriť).
        self::refill();
    }

    /**
     * Zaistí, že existuje `N` budúcich oznamov v statuse `future` / `draft` / `publish`,
     * počnúc nasledujúcim Mon–Sun.
     */
    public static function refill(): void
    {
        $settings = Settings::get();
        $count = isset($settings['oznamy']['dopredne_drafty']) ? (int) $settings['oznamy']['dopredne_drafty'] : 2;
        $count = max(1, min(4, $count));

        for ($i = 0; $i < $count; $i++) {
            $mon = self::nthWeekMonday($i);
            $monIso = $mon->format('Y-m-d');
            if (self::weekHasOznam($monIso)) {
                continue;
            }
            self::createWeekOznam($mon, $settings);
        }
    }

    private static function nthWeekMonday(int $offset): DateTimeImmutable
    {
        $tz       = wp_timezone();
        $tomorrow = new DateTimeImmutable('tomorrow', $tz);
        $dow      = (int) $tomorrow->format('N'); // 1=Mon..7=Sun
        $daysToMon = $dow === 1 ? 0 : (8 - $dow);
        return $tomorrow
            ->modify("+{$daysToMon} day")
            ->modify('+' . ($offset * 7) . ' day');
    }

    private static function weekHasOznam(string $tyzdenOdIso): bool
    {
        $q = new WP_Query([
            'post_type'      => Oznam::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft', 'pending', 'future', 'private', 'auto-draft'],
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => 'farnost_tyzden_od', 'value' => $tyzdenOdIso, 'compare' => '='],
            ],
        ]);
        return !empty($q->posts);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function createWeekOznam(DateTimeImmutable $mon, array $settings): int
    {
        $sun = $mon->modify('+6 day');
        $monIso = $mon->format('Y-m-d');
        $sunIso = $sun->format('Y-m-d');

        $dni = SnapshotBuilder::buildForWeek($monIso, $sunIso);
        $attrs = [
            'tyzdenOd'   => $monIso,
            'tyzdenDo'   => $sunIso,
            'dni'        => $dni,
            'snapshotAt' => current_datetime()->format(DATE_ATOM),
        ];
        $json = wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log(sprintf(
                '[farnost-plugin] BufferManager: JSON encode rozpis-snapshot zlyhal pre týždeň %s–%s.',
                $monIso,
                $sunIso
            ));
            return 0;
        }

        // Pridelíme upratovaciu skupinu pre tento týždeň (rotácia + paragraph blok do obsahu).
        $upratuje = Upratovanie::pickForNextWeek();

        $content  = sprintf('<!-- wp:farnost/rozpis-snapshot %s /-->', $json);
        if ($upratuje !== null) {
            $content .= "\n\n" . Upratovanie::renderParagraphBlock($upratuje['title']);
        }
        $content .= "\n\n<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->";

        $publishLocal = self::computePublishDate($mon, $settings);
        $publishGmt   = $publishLocal->setTimezone(new DateTimeZone('UTC'));
        $nowLocal     = new DateTimeImmutable('now', wp_timezone());

        // Ak publikačný čas pre tento týždeň už prešiel (napr. aktivácia v polovici týždňa),
        // publikujeme rovno (post_status = publish), inak naplánujeme (future).
        $status = $publishLocal <= $nowLocal ? 'publish' : 'future';

        $metaInput = [
            'farnost_tyzden_od' => $monIso,
            'farnost_tyzden_do' => $sunIso,
        ];
        if ($upratuje !== null) {
            $metaInput['farnost_upratuje_id'] = $upratuje['id'];
        }

        $result = wp_insert_post([
            'post_type'     => Oznam::POST_TYPE,
            'post_status'   => $status,
            'post_title'    => sprintf('Oznam %s — %s', $monIso, $sunIso),
            'post_content'  => $content,
            'post_date'     => $publishLocal->format('Y-m-d H:i:s'),
            'post_date_gmt' => $publishGmt->format('Y-m-d H:i:s'),
            'meta_input'    => $metaInput,
        ], true);

        if (is_wp_error($result)) {
            error_log(sprintf(
                '[farnost-plugin] BufferManager: wp_insert_post zlyhal pre týždeň %s–%s — %s',
                $monIso,
                $sunIso,
                $result->get_error_message()
            ));
            return 0;
        }

        return (int) $result;
    }

    /**
     * Vráti publikačný čas pre oznam, ktorý kryje týždeň začínajúci `$mon`.
     *
     * Pravidlo: najnovšia okurencia `publikacny_den` ktorá je PRED `$mon` (v rozsahu
     * predchádzajúcich 7 dní). Príklad: oznam Mon 18.5. — Sun 24.5. s publikačným
     * dňom „nedeľa" → publikuje sa v nedeľu 17.5. o nastavenom čase.
     *
     * @param array<string, mixed> $settings
     */
    public static function computePublishDate(DateTimeImmutable $mon, array $settings): DateTimeImmutable
    {
        $den = (string) ($settings['oznamy']['publikacny_den'] ?? 'sunday');
        $cas = (string) ($settings['oznamy']['publikacny_cas'] ?? '08:00');

        $dayMap = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        ];
        $targetIso = $dayMap[$den] ?? 7;

        [$h, $m] = array_map('intval', explode(':', preg_match('/^\d{2}:\d{2}$/', $cas) === 1 ? $cas : '08:00'));

        // Hľadaj najnovšiu okurenciu `targetIso` v 7 dňoch pred `mon` (exclusive).
        $candidate = $mon->modify('-1 day');
        for ($i = 0; $i < 7; $i++) {
            if ((int) $candidate->format('N') === $targetIso) {
                return $candidate->setTime($h, $m);
            }
            $candidate = $candidate->modify('-1 day');
        }
        // Fallback — deň pred (neoptimálne, ale nikdy by sa to nemalo stať).
        return $mon->modify('-1 day')->setTime($h, $m);
    }
}
