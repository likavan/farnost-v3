<?php
/**
 * WP-CLI seed skript pre dev prostredie.
 *
 * Použitie:
 *   ddev wp --path=web/wp eval-file scripts/seed.php
 *
 * Naseeduje:
 *   - 1 kostol s týždenným rozpisom (4 omše: po, st, so, ne)
 *   - 2 výnimky (mimoriadne omše)
 *   - 5 úmyslov pre rôzne dátumy
 *   - 4 udalosti (WP posty) v 3 rôznych default kategóriách
 *   - 3 upratovacie skupiny, pointer na prvú
 *
 * Idempotentné — kontroluje, či už dáta existujú, a preskakuje.
 */

declare(strict_types=1);

if (!defined('WP_CLI')) {
    return;
}

\WP_CLI::log('Seed: začínam.');

// ─── Kostol + rozpis ────────────────────────────────────────────────────────
$kostolTitle = 'Farský kostol sv. Martina';
$existing = get_page_by_path(sanitize_title($kostolTitle), OBJECT, 'kostol');
if ($existing) {
    $kostolId = (int) $existing->ID;
    \WP_CLI::log("Kostol už existuje (ID {$kostolId}).");
} else {
    $kostolId = (int) wp_insert_post([
        'post_type'    => 'kostol',
        'post_title'   => $kostolTitle,
        'post_status'  => 'publish',
        'post_content' => 'Hlavný farský kostol s pravidelným rozpisom omší.',
    ]);
    update_post_meta($kostolId, 'farnost_adresa', 'Hlavná 1, 917 01 Trnava');
    update_post_meta($kostolId, 'farnost_je_hlavny', true);
    update_post_meta($kostolId, 'farnost_rozpis', json_encode([
        ['day_of_week' => 'mon', 'time' => '18:00', 'oznacenie' => ''],
        ['day_of_week' => 'wed', 'time' => '18:00', 'oznacenie' => 'detská'],
        ['day_of_week' => 'sat', 'time' => '07:30', 'oznacenie' => ''],
        ['day_of_week' => 'sun', 'time' => '08:00', 'oznacenie' => ''],
        ['day_of_week' => 'sun', 'time' => '10:30', 'oznacenie' => ''],
    ]));
    \WP_CLI::log("Kostol vytvorený (ID {$kostolId}).");
}

// ─── Výnimky ────────────────────────────────────────────────────────────────
$vynimky = [
    [
        'title'     => 'Mimoriadna pohrebná omša 2026-05-18',
        'datum'     => '2026-05-18',
        'cas'       => '14:00',
        'oznacenie' => 'pohrebná',
        'umysel'    => 'za zosnulú p. Novákovú',
    ],
    [
        'title'     => 'Mariánska púť 2026-05-23',
        'datum'     => '2026-05-23',
        'cas'       => '09:00',
        'oznacenie' => 'púťová',
        'umysel'    => 'za farníkov',
    ],
];

foreach ($vynimky as $v) {
    $found = get_posts([
        'post_type'      => 'omsa_vynimka',
        'title'          => $v['title'],
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ]);
    if (!empty($found)) {
        continue;
    }
    $vId = (int) wp_insert_post([
        'post_type'   => 'omsa_vynimka',
        'post_title'  => $v['title'],
        'post_status' => 'publish',
    ]);
    update_post_meta($vId, 'farnost_datum', $v['datum']);
    update_post_meta($vId, 'farnost_cas', $v['cas']);
    update_post_meta($vId, 'farnost_kostol_id', $kostolId);
    update_post_meta($vId, 'farnost_oznacenie', $v['oznacenie']);
    update_post_meta($vId, 'farnost_umysel', $v['umysel']);
}
\WP_CLI::log('Výnimky pripravené.');

// ─── Úmysly ─────────────────────────────────────────────────────────────────
$umysly = [
    ['datum' => '2026-05-17', 'cas' => '08:00', 'text' => 'Za zdravie rodiny Kovačovej'],
    ['datum' => '2026-05-17', 'cas' => '10:30', 'text' => 'Za farníkov'],
    ['datum' => '2026-05-20', 'cas' => '18:00', 'text' => '† Mária Nováková'],
    ['datum' => '2026-05-23', 'cas' => '07:30', 'text' => 'Poďakovanie za úrodu'],
    ['datum' => '2026-05-24', 'cas' => '08:00', 'text' => '† rodičia Kovačoví'],
];

foreach ($umysly as $u) {
    $title = "Úmysel {$u['datum']} {$u['cas']}";
    $found = get_posts([
        'post_type'      => 'umysel',
        'title'          => $title,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ]);
    if (!empty($found)) {
        continue;
    }
    $uId = (int) wp_insert_post([
        'post_type'   => 'umysel',
        'post_title'  => $title,
        'post_status' => 'publish',
    ]);
    update_post_meta($uId, 'farnost_datum', $u['datum']);
    update_post_meta($uId, 'farnost_cas', $u['cas']);
    update_post_meta($uId, 'farnost_kostol_id', $kostolId);
    update_post_meta($uId, 'farnost_text', $u['text']);
    update_post_meta($uId, 'farnost_anonymny', false);
}
\WP_CLI::log('Úmysly pripravené.');

// ─── Oznamy ─────────────────────────────────────────────────────────────────
$oznamy = [
    ['title' => 'Oznam 2026-05-11 — 2026-05-17', 'od' => '2026-05-11', 'do' => '2026-05-17'],
    ['title' => 'Oznam 2026-05-18 — 2026-05-24', 'od' => '2026-05-18', 'do' => '2026-05-24'],
    ['title' => 'Oznam 2026-05-25 — 2026-05-31', 'od' => '2026-05-25', 'do' => '2026-05-31'],
];

foreach ($oznamy as $o) {
    $foundO = get_posts([
        'post_type'      => 'oznam',
        'title'          => $o['title'],
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ]);
    if (!empty($foundO)) {
        continue;
    }
    $oId = (int) wp_insert_post([
        'post_type'    => 'oznam',
        'post_title'   => $o['title'],
        'post_status'  => 'publish',
        'post_content' => "Stub oznam (Etapa 1 seed). Šablónu doriešime v Etape 2.\n\nTento týždeň upratuje: <auto-vloží Etapa 2>.",
    ]);
    update_post_meta($oId, 'farnost_tyzden_od', $o['od']);
    update_post_meta($oId, 'farnost_tyzden_do', $o['do']);
}
\WP_CLI::log('Oznamy pripravené.');

// ─── Udalosti (WP posty) ────────────────────────────────────────────────────
$udalosti = [
    [
        'title'     => 'Mariánska púť do Šaštína',
        'content'   => 'Pozývame veriacich na púť dňa 23. mája.',
        'event_when'  => '2026-05-23 09:00',
        'event_where' => 'Šaštín',
        'category'  => 'pozvanky',
    ],
    [
        'title'     => 'Brigáda na úprave kostola',
        'content'   => 'V sobotu 31. mája pozývame farníkov na pomoc.',
        'event_when'  => '2026-05-31 09:00',
        'event_where' => 'Farský kostol',
        'category'  => 'zo-zivota-farnosti',
    ],
    [
        'title'     => 'Slávnostné prvé sv. prijímanie',
        'content'   => 'Deti našej farnosti prijmú sviatosť 7. júna.',
        'event_when'  => '2026-06-07 10:30',
        'event_where' => 'Farský kostol',
        'category'  => 'udalosti',
    ],
    [
        'title'     => 'Letný farský tábor',
        'content'   => 'Otvárame prihlasovanie na letný tábor pre deti.',
        'event_when'  => '2026-07-14 09:00',
        'event_where' => 'Farský dom',
        'category'  => 'pozvanky',
    ],
];

foreach ($udalosti as $e) {
    $existingPost = get_page_by_title($e['title'], OBJECT, 'post');
    if ($existingPost) {
        continue;
    }
    $postId = (int) wp_insert_post([
        'post_type'    => 'post',
        'post_title'   => $e['title'],
        'post_content' => $e['content'],
        'post_status'  => 'publish',
    ]);
    update_post_meta($postId, 'farnost_event_when', $e['event_when']);
    update_post_meta($postId, 'farnost_event_where', $e['event_where']);

    $term = get_term_by('slug', $e['category'], 'category');
    if ($term) {
        wp_set_post_terms($postId, [$term->term_id], 'category');
    }
}
\WP_CLI::log('Udalosti pripravené.');

// ─── Upratovacie skupiny ────────────────────────────────────────────────────
$skupiny = [
    ['name' => 'Skupina sv. Jozefa',  'kontakt' => 'Anna K., 0905 ...',  'clenovia' => 'Mária N., Anna K., Helena P.'],
    ['name' => 'Skupina č. 2',         'kontakt' => '',                   'clenovia' => 'Eva S., Jana L.'],
    ['name' => 'Skupina č. 3',         'kontakt' => '',                   'clenovia' => 'Peter D., Marta Ž.'],
];

$skupinyIds = [];
$order = 1;
foreach ($skupiny as $s) {
    $existingSk = get_posts([
        'post_type'      => 'upratovacia_skupina',
        'title'          => $s['name'],
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ]);
    if (!empty($existingSk)) {
        $skupinyIds[] = (int) $existingSk[0]->ID;
        $order++;
        continue;
    }
    $sId = (int) wp_insert_post([
        'post_type'   => 'upratovacia_skupina',
        'post_title'  => $s['name'],
        'post_status' => 'publish',
        'menu_order'  => $order,
    ]);
    update_post_meta($sId, 'farnost_skupina_kontakt', $s['kontakt']);
    update_post_meta($sId, 'farnost_skupina_clenovia', $s['clenovia']);
    $skupinyIds[] = $sId;
    $order++;
}

// Nastav pointer na prvú skupinu, ak ešte nie je nastavený.
$settings = get_option('farnost_settings', []);
if (!is_array($settings)) {
    $settings = [];
}
if (empty($settings['upratovanie']['dalsia_skupina']) && !empty($skupinyIds)) {
    $settings['upratovanie']['dalsia_skupina'] = $skupinyIds[0];
    update_option('farnost_settings', $settings);
}
\WP_CLI::log('Upratovacie skupiny pripravené.');

\WP_CLI::success('Seed dokončený.');
