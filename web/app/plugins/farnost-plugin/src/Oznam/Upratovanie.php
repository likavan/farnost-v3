<?php

declare(strict_types=1);

namespace Farnost\Plugin\Oznam;

use Farnost\Plugin\PostTypes\Oznam;
use Farnost\Plugin\PostTypes\UpratovaciaSkupina;
use Farnost\Plugin\Settings\Settings;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rotácia upratovacích skupín v týždennom ozname.
 *
 * Tok dát:
 *  1. `BufferManager::createWeekOznam` zavolá `pickForNextWeek()` — zistí, ktorá
 *     skupina patrí tomuto novovznikajúcemu týždňu. Výsledok ide do post meta
 *     `farnost_upratuje_id` a do paragraph bloku v `post_content` (riadok „Tento
 *     týždeň upratuje: …").
 *
 *  2. Pri prechode oznamu do `publish` (`onPublish`) sa pointer
 *     `farnost_settings.upratovanie.dalsia_skupina` posunie **na nasledujúcu**
 *     skupinu po tej, ktorú práve zverejnený oznam reprezentoval. Tým je
 *     pointer vždy „čo má dostať najbližší ešte nevytvorený týždeň".
 *
 * Princíp prideľovania (`pickForNextWeek`):
 *  - Ak je v buffere už neukončený oznam s `farnost_upratuje_id`, ten je „posledne
 *    pridelený" — nový oznam ide na ďalšiu skupinu v rotácii.
 *  - Inak: novej skupiny sa použije pointer (`dalsia_skupina`). Pre úplne čistú
 *    inštaláciu pointer = 0 → vezmeme prvú skupinu v poradí (menu_order asc).
 *
 * Skupiny sú zoradené podľa `menu_order` (drag-and-drop v
 * `Admin\UpratovacieSkupinyPage`). Rotácia je modulo počet skupín.
 */
final class Upratovanie
{
    public const META_KEY = 'farnost_upratuje_id';

    public static function register(): void
    {
        add_action('transition_post_status', [self::class, 'onTransition'], 11, 3);
    }

    public static function onTransition(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($post->post_type !== Oznam::POST_TYPE) {
            return;
        }
        if ($newStatus !== 'publish' || $oldStatus === $newStatus) {
            return;
        }
        self::advancePointerAfterPublish((int) $post->ID);
    }

    /**
     * Pre nový týždeň vráti pridelenú skupinu (id + title), alebo null ak
     * žiadne skupiny nie sú definované.
     *
     * @return array{id: int, title: string}|null
     */
    public static function pickForNextWeek(): ?array
    {
        $groups = self::loadGroupsOrdered();
        if (empty($groups)) {
            return null;
        }

        // 1) Najnovší ešte nepublikovaný oznam s pridelenou skupinou → nasledujúca.
        $lastAssignedGroupId = self::lastAssignedGroupIdInBuffer();
        if ($lastAssignedGroupId !== null) {
            $next = self::nextGroupId($lastAssignedGroupId, $groups);
            if ($next !== null) {
                return $next;
            }
        }

        // 2) Inak: pointer z nastavení (alebo prvá skupina, ak je 0 / chýba).
        $settings = Settings::get();
        $pointerId = (int) ($settings['upratovanie']['dalsia_skupina'] ?? 0);
        if ($pointerId > 0) {
            foreach ($groups as $g) {
                if ($g['id'] === $pointerId) {
                    return $g;
                }
            }
        }
        return $groups[0];
    }

    /**
     * HTML pre paragraph blok, ktorý ide do `post_content` pri vytvorení oznamu.
     * Farár môže text inline editovať — pointer to neovplyvní (snapshot model).
     */
    public static function renderParagraphBlock(string $title): string
    {
        $html = sprintf(
            /* translators: %s = názov upratovacej skupiny (renderuje sa tučne) */
            __('Tento týždeň upratuje: <strong>%s</strong>', 'farnost-plugin'),
            esc_html($title)
        );
        return "<!-- wp:paragraph -->\n<p>{$html}</p>\n<!-- /wp:paragraph -->";
    }

    private static function advancePointerAfterPublish(int $oznamId): void
    {
        $assignedRaw = get_post_meta($oznamId, self::META_KEY, true);
        $assigned    = (int) $assignedRaw;
        if ($assigned <= 0) {
            return; // oznam nemal pridelenú skupinu — nič neposúvame
        }

        $groups = self::loadGroupsOrdered();
        if (empty($groups)) {
            return;
        }
        $next = self::nextGroupId($assigned, $groups);
        if ($next === null) {
            return;
        }

        $settings = Settings::get();
        // Skip ak už pointer ukazuje kam má (idempotentné — duplicitné publish hooky nič nepokazia).
        if ((int) ($settings['upratovanie']['dalsia_skupina'] ?? 0) === $next['id']) {
            return;
        }
        $settings['upratovanie']['dalsia_skupina'] = $next['id'];
        update_option(Settings::OPTION_KEY, $settings);
    }

    /**
     * Vráti id+title skupiny nasledujúcej po `$currentId` v rotácii (modulo).
     * Ak `$currentId` neexistuje v skupinách (zmazaná?), vráti prvú skupinu.
     *
     * @param list<array{id: int, title: string}> $groups
     * @return array{id: int, title: string}|null
     */
    private static function nextGroupId(int $currentId, array $groups): ?array
    {
        if (empty($groups)) {
            return null;
        }
        $count = count($groups);
        foreach ($groups as $i => $g) {
            if ($g['id'] === $currentId) {
                return $groups[($i + 1) % $count];
            }
        }
        // currentId neexistuje (skupina bola zmazaná) → vrátime prvú.
        return $groups[0];
    }

    /**
     * Skupiny zoradené podľa `menu_order` ASC (drag-and-drop poradie z admin obrazovky).
     *
     * @return list<array{id: int, title: string}>
     */
    private static function loadGroupsOrdered(): array
    {
        $posts = get_posts([
            'post_type'      => UpratovaciaSkupina::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'id'    => (int) $p->ID,
                'title' => (string) $p->post_title,
            ];
        }
        return $out;
    }

    /**
     * Najnovší (najvyšší `farnost_tyzden_od`) ešte nepublikovaný oznam s
     * pridelenou skupinou. Slúži na pokračovanie reťazca rotácie, kým prvý
     * oznam v rade nepublikujeme a pointer sa neposunie.
     */
    private static function lastAssignedGroupIdInBuffer(): ?int
    {
        $q = new WP_Query([
            'post_type'      => Oznam::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => ['future', 'draft', 'pending'],
            'orderby'        => 'meta_value',
            'meta_key'       => 'farnost_tyzden_od',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'farnost_tyzden_od', 'compare' => 'EXISTS'],
                ['key' => self::META_KEY, 'compare' => 'EXISTS'],
                ['key' => self::META_KEY, 'value' => '0', 'compare' => '!='],
            ],
        ]);
        if (empty($q->posts)) {
            return null;
        }
        $id = (int) get_post_meta((int) $q->posts[0], self::META_KEY, true);
        return $id > 0 ? $id : null;
    }
}
