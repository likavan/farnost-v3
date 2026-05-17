<?php

declare(strict_types=1);

namespace Farnost\Plugin\Oznam;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Oznam;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-prefill nového `oznam` postu.
 *
 * Pri vytvorení nového auto-draftu (klik „Pridať oznam") systém:
 *  - Vypočíta cieľový týždeň (Mon–Sun nasledujúci po dnešnom dni)
 *  - Uloží do post meta `farnost_tyzden_od` / `farnost_tyzden_do`
 *
 * Naplnenie obsahu bloku `farnost/rozpis-snapshot` aktuálnymi dátami (rozpis,
 * výnimky, úmysly, sviatky) pridáme v ďalšom kroku.
 */
final class AutoTemplate
{
    public static function register(): void
    {
        // Bezpečnostná sieť pre prípad, že oznam vznikne mimo BufferManagera
        // (priamy REST / WP-CLI / iný plugin) — doplníme aspoň meta a obsah.
        add_action('wp_insert_post', [self::class, 'onInsert'], 10, 3);
    }

    public static function onInsert(int $postId, \WP_Post $post, bool $update): void
    {
        if ($update) {
            return;
        }
        if ($post->post_type !== Oznam::POST_TYPE) {
            return;
        }
        $existingOd = get_post_meta($postId, 'farnost_tyzden_od', true);

        // 1) Týždeň meta — vypočítaj, ak ešte nie sú.
        if (empty($existingOd)) {
            [$od, $do] = self::computeNextWeek();
            update_post_meta($postId, 'farnost_tyzden_od', $od);
            update_post_meta($postId, 'farnost_tyzden_do', $do);
        } else {
            $od = (string) $existingOd;
            $do = (string) get_post_meta($postId, 'farnost_tyzden_do', true);
        }

        // 2) Pred-vyplnený obsah — len ak je content prázdny.
        $current = get_post_field('post_content', $postId);
        if (is_string($current) && trim($current) !== '') {
            return;
        }

        $dni = SnapshotBuilder::buildForWeek($od, $do);
        $attrs = [
            'tyzdenOd'   => $od,
            'tyzdenDo'   => $do,
            'dni'        => $dni,
            'snapshotAt' => current_datetime()->format(DATE_ATOM),
        ];
        $json = wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        $content = sprintf('<!-- wp:farnost/rozpis-snapshot %s /-->', $json);
        $content .= "\n\n";
        $content .= "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->";

        // wp_update_post znova spustí wp_insert_post hook s $update=true → naša guard hore preskočí.
        wp_update_post([
            'ID'           => $postId,
            'post_content' => $content,
        ]);
    }

    /**
     * Vráti dvojicu [Monday, Sunday] **nasledujúceho** týždňa po dnešnom dni.
     *
     * **Žiadne auto-skipovanie obsadených týždňov** — vždy vraciame ten istý,
     * najbližší týždeň. Ak je už obsadený existujúcim oznamom, riešime to
     * presmerovaním v `maybeRedirectToExisting` (farár ide editovať existujúci,
     * neprídava nový).
     *
     * Dôvod: snapshot model. Ak by sme vytvárali oznamy do budúcnosti,
     * neskoršie pridané úmysly / výnimky by sa do snapshotu nepremietli.
     * Farár tvorí jeden oznam pre jeden konkrétny nadchádzajúci týždeň.
     *
     * @return array{0: string, 1: string}  Mon a Sun ako 'Y-m-d'
     */
    public static function computeNextWeek(): array
    {
        $tz        = wp_timezone();
        $tomorrow  = new DateTimeImmutable('tomorrow', $tz);
        $dow       = (int) $tomorrow->format('N'); // 1=Mon..7=Sun
        $daysToMon = $dow === 1 ? 0 : (8 - $dow);
        $mon       = $tomorrow->modify("+{$daysToMon} day");
        return [
            $mon->format('Y-m-d'),
            $mon->modify('+6 day')->format('Y-m-d'),
        ];
    }

}
