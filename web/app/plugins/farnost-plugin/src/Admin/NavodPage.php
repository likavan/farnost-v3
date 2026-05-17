<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Statická obrazovka `Farnosť → Návod`.
 *
 * Žiadny React, žiadna AI — len knowledge base s odpoveďami na „ako spravím X".
 * Checklist „prvé kroky" sa počíta zo skutočného stavu (wizard, prvý kostol,
 * prvý oznam, prvá upratovacia skupina), nie zo samostatného state trackera.
 * Detaily zámeru v doc/07-admin-ux.md → „Pomoc a dokumentácia".
 */
final class NavodPage
{
    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }

        $steps = self::computeChecklist();
        $done  = 0;
        foreach ($steps as $s) {
            if ($s['done']) {
                $done++;
            }
        }
        $total = count($steps);

        ?>
        <div class="wrap farnost-navod">
            <h1><?php esc_html_e('Návod', 'farnost-plugin'); ?></h1>
            <p class="description" style="max-width:720px;margin:6px 0 20px;">
                <?php esc_html_e('Stručná príručka k farskému webu. Nájdete tu prvé kroky po inštalácii, návody na bežné situácie (pohreb, mimoriadny oznam, upratovanie) a odpovede na časté otázky.', 'farnost-plugin'); ?>
            </p>

            <style>
                .farnost-navod { max-width: 900px; }
                .farnost-navod-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                @media (max-width: 900px) { .farnost-navod-grid { grid-template-columns: 1fr; } }
                .farnost-navod-card {
                    background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
                    padding: 18px 20px; margin-bottom: 16px;
                }
                .farnost-navod-card h2 { margin: 0 0 10px; font-size: 16px; }
                .farnost-navod-card p { margin: 0 0 8px; }
                .farnost-navod-checklist { list-style: none; padding: 0; margin: 8px 0 0; }
                .farnost-navod-checklist li {
                    padding: 6px 0; border-top: 1px solid #f3f4f6;
                    display: flex; align-items: center; gap: 10px;
                }
                .farnost-navod-checklist li:first-child { border-top: none; }
                .farnost-navod-check {
                    flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%;
                    border: 1.5px solid #d1d5db; display: inline-flex; align-items: center;
                    justify-content: center; font-size: 11px; font-weight: 700; color: #fff;
                }
                .farnost-navod-check.done { background: #15803d; border-color: #15803d; }
                .farnost-navod-step.done .farnost-navod-step-title { color: #6b7280; text-decoration: line-through; }
                .farnost-navod-step-title { font-weight: 500; }
                .farnost-navod-step-cta { margin-left: auto; }
                .farnost-navod-progress {
                    height: 6px; background: #f3f4f6; border-radius: 3px; overflow: hidden;
                    margin: 10px 0 4px;
                }
                .farnost-navod-progress-bar { height: 100%; background: #1d4ed8; transition: width 0.3s; }
                .farnost-navod-howto details {
                    border-bottom: 1px solid #f3f4f6; padding: 10px 0;
                }
                .farnost-navod-howto details:last-child { border-bottom: none; }
                .farnost-navod-howto summary {
                    cursor: pointer; font-weight: 500; padding: 4px 0;
                }
                .farnost-navod-howto summary:hover { color: #1d4ed8; }
                .farnost-navod-howto details[open] summary { color: #1d4ed8; margin-bottom: 8px; }
                .farnost-navod-howto ol, .farnost-navod-howto ul { margin: 8px 0 8px 20px; }
                .farnost-navod-howto li { margin-bottom: 4px; }
                .farnost-navod-howto code {
                    background: #f3f4f6; padding: 2px 5px; border-radius: 3px;
                    font-size: 12px;
                }
                .farnost-navod-howto p { margin: 8px 0; }
            </style>

            <div class="farnost-navod-card">
                <h2><?php esc_html_e('Prvé kroky', 'farnost-plugin'); ?></h2>
                <div class="farnost-navod-progress">
                    <div class="farnost-navod-progress-bar" style="width: <?php echo esc_attr((string) ($total > 0 ? round($done / $total * 100) : 0)); ?>%;"></div>
                </div>
                <p style="font-size:12px;color:#6b7280;margin:0 0 4px;">
                    <?php
                    printf(
                        /* translators: %1$d = done count, %2$d = total count */
                        esc_html__('Hotových: %1$d z %2$d', 'farnost-plugin'),
                        (int) $done,
                        (int) $total
                    );
                    ?>
                </p>
                <ul class="farnost-navod-checklist">
                    <?php foreach ($steps as $s) : ?>
                        <li class="farnost-navod-step<?php echo $s['done'] ? ' done' : ''; ?>">
                            <span class="farnost-navod-check<?php echo $s['done'] ? ' done' : ''; ?>">
                                <?php echo $s['done'] ? '✓' : ''; ?>
                            </span>
                            <span class="farnost-navod-step-title"><?php echo esc_html($s['title']); ?></span>
                            <?php if (!$s['done'] && !empty($s['url'])) : ?>
                                <a class="button button-small farnost-navod-step-cta" href="<?php echo esc_url($s['url']); ?>">
                                    <?php echo esc_html($s['cta'] ?? __('Otvoriť', 'farnost-plugin')); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="farnost-navod-grid">
                <div class="farnost-navod-card">
                    <h2><?php esc_html_e('Týždenný rytmus farára', 'farnost-plugin'); ?></h2>
                    <ol style="margin:0 0 0 20px;">
                        <li><?php esc_html_e('V Kalendári omší pridáte úmysly a prípadné pohreby / sobáše pre nasledujúci týždeň.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Otvoríte rozpracovaný oznam — rozpis omší + úmysly sú už predvyplnené, dopíšete bežné oznamy.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Oznam sa publikuje sám podľa nastaveného času (defaultne nedeľa 08:00).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pointer upratovania sa posunie sám na ďalšiu skupinu.', 'farnost-plugin'); ?></li>
                    </ol>
                </div>

                <div class="farnost-navod-card">
                    <h2><?php esc_html_e('Krízový moment', 'farnost-plugin'); ?></h2>
                    <p><?php esc_html_e('Potrebujete oznámiť mimoriadnu situáciu (úmrtie, zmena času omše, výpadok)?', 'farnost-plugin'); ?></p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=farnost-mimoriadny-oznam')); ?>">
                            <?php esc_html_e('Mimoriadny oznam →', 'farnost-plugin'); ?>
                        </a>
                    </p>
                    <p style="font-size:12px;color:#6b7280;margin-top:8px;">
                        <?php esc_html_e('Banner sa zobrazí navrchu na celom webe. Voliteľne sa skryje po nastavenom dátume.', 'farnost-plugin'); ?>
                    </p>
                </div>
            </div>

            <div class="farnost-navod-card farnost-navod-howto">
                <h2><?php esc_html_e('Bežné situácie — krok za krokom', 'farnost-plugin'); ?></h2>

                <details>
                    <summary><?php esc_html_e('Ako napísať týždenný oznam', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('V menu Farnosť → Oznamy. Otvorte rozpracovaný oznam pre nasledujúci týždeň (systém ho pripravuje dopredu sám).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Karty s omšami sú už predvyplnené z rozpisu a úmyslov. Ak treba korekciu, kliknite priamo do bunky.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pod kartami je voľný blok „Bežné oznamy" — sem píšete pohreby, zbierky, akcie, ďakovania.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Riadok „Tento týždeň upratuje" sa vkladá automaticky podľa aktuálnej skupiny.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Stačí uložiť ako Draft — publikuje sa sám v nastavený deň a čas. Nemusíte byť pri počítači.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako pridať pohreb (alebo sobáš, púť…)', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Otvorte Kalendár omší (Farnosť → Kalendár omší).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('V príslušnom dni kliknite na „+" tlačidlo pod existujúcimi omšami.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Zadajte čas, kostol, označenie (napr. „pohrebná") a voliteľne úmysel. Uložte.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pohreb sa automaticky objaví v najbližšom rozpracovanom ozname pre daný týždeň.', 'farnost-plugin'); ?></li>
                    </ol>
                    <p style="font-size:12px;color:#6b7280;">
                        <?php esc_html_e('Toto je „výnimka" v rozpise — pridaná omša nad rámec pravidelnej. Ak naopak chcete pravidelnú omšu na daný deň zrušiť, použite režim „bez omše" v kalendári.', 'farnost-plugin'); ?>
                    </p>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako spravovať úmysly omší', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Kalendár omší — kliknite priamo na čas omše v danom dni.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('V dialógu napíšte úmysel (napr. „† Mária Nováková" alebo „Za zdravie rodiny"). Uložte.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Ak chcete úmysel skryť na verejnom webe (zostáva len v internej evidencii), zapnite prepínač „Anonymný".', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Úmysly sa potom automaticky vložia do rozpracovaného oznamu pre daný týždeň.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako spravovať upratovacie skupiny', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Farnosť → Upratovacie skupiny. Pridajte skupinu zadaním názvu a kliknutím Pridať.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pretiahnutím (☰) zmeníte poradie rotácie. Indikátor „• Aktuálne na rade" označuje skupinu, ktorú systém vloží do ďalšieho oznamu.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Po publikácii oznamu pointer skočí sám na ďalšiu skupinu (modulo počet skupín).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Po výnimočných obdobiach (Vianoce, kombinované upratovania) kliknite „Nastaviť ako ďalšiu na rade" na tej skupine, ktorá má reálne nasledovať.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako publikovať mimoriadny oznam (banner)', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Farnosť → Mimoriadny oznam.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Vyplňte text (môže obsahovať tučné, kurzívu, odkaz, zoznam). Voliteľne nastavte dátum a čas, po ktorom banner sám zmizne.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Kliknite Publikovať. Banner sa zobrazí navrchu všetkých stránok webu, kým ho nezrušíte alebo nevyprší.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako zmeniť kontakt, IBAN, logo, sociálne siete', 'farnost-plugin'); ?></summary>
                    <p><?php esc_html_e('Všetky tieto údaje sú v Nastaveniach a aktualizujú sa naraz po celom webe.', 'farnost-plugin'); ?></p>
                    <p>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=farnost-nastavenia')); ?>">
                            <?php esc_html_e('Otvoriť Nastavenia', 'farnost-plugin'); ?>
                        </a>
                    </p>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako pridať nový kostol s rozpisom', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Farnosť → Kostoly.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Hore napíšte názov (napr. „Kostol sv. Martina") a kliknite Pridať. Kostol dostane farbu automaticky podľa poradia — môžete ju zmeniť klikom na farebný štvorček.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pri jednom kostole zapnite prepínač „Hlavný" — slúži ako default v dropdownoch (napr. pri vytváraní mimoriadnej omše).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Kliknite Rozpis → otvorí sa 7-dňový grid. Pre každý deň pridávate časy s voliteľným označením („detská", „sviatočná"). Klik na čas alebo označenie ich umožní inline upraviť.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Poradie kostolov v zozname (a v kalendárnej legende) zmeníte pretiahnutím ☰ ikony naľavo.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako pridať udalosť (akciu, púť, koncert)', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Wp-admin → Príspevky → Pridať nový.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Napíšte názov a popis. V pravom sidebare zvoľte kategóriu (Udalosti / Pozvánky / Zo života farnosti).', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('V paneli „Detaily udalosti" doplňte Kedy (dátum / čas / textovo) a Kde. Tieto polia sú voliteľné.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Publikujte. Udalosť sa objaví v kombinovanom feed-e na úvodnej stránke.', 'farnost-plugin'); ?></li>
                    </ol>
                </details>

                <details>
                    <summary><?php esc_html_e('Ako zmeniť farbu kategórie (badge vo feed-e)', 'farnost-plugin'); ?></summary>
                    <ol>
                        <li><?php esc_html_e('Wp-admin → Príspevky → Kategórie → kliknite názov kategórie.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Pole „Farba kategórie" — kliknite a vyberte farbu z palety.', 'farnost-plugin'); ?></li>
                        <li><?php esc_html_e('Voliteľne zapnite / vypnite „Zobraziť v menu webu".', 'farnost-plugin'); ?></li>
                    </ol>
                </details>
            </div>

            <div class="farnost-navod-card">
                <h2><?php esc_html_e('Niečo nefunguje?', 'farnost-plugin'); ?></h2>
                <p><?php esc_html_e('Ak narazíte na chybu alebo niečo robí inak, ako by malo, napíšte nám. Snažíme sa reagovať rýchlo.', 'farnost-plugin'); ?></p>
                <p style="font-size:12px;color:#6b7280;margin-top:8px;">
                    <?php esc_html_e('Pred nahlásením skúste obnoviť stránku (Ctrl+R) — pomôže pri väčšine zamrznutí.', 'farnost-plugin'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Spočíta checklist „prvé kroky" zo skutočného stavu DB.
     *
     * Žiadny separátny state — všetky kroky sa zisťujú z existencie reálnych
     * objektov. Vďaka tomu sa checklist nikdy nerozsynchronuje s realitou.
     *
     * @return list<array{title:string, done:bool, url?:string, cta?:string}>
     */
    private static function computeChecklist(): array
    {
        $settings = Settings::get();

        $wizardCompleted = (bool) ($settings['setup']['completed'] ?? false);
        $nazovFilled     = is_string($settings['identita']['nazov'] ?? null)
            && trim((string) $settings['identita']['nazov']) !== '';
        $kontaktFilled = !empty($settings['kontakt']['adresa'])
            || !empty($settings['kontakt']['telefony'])
            || !empty($settings['kontakt']['emaily']);

        $kostolCount   = (int) wp_count_posts('kostol')->publish;
        $skupinaCount  = (int) wp_count_posts('upratovacia_skupina')->publish;
        $oznamPublishCount = (int) wp_count_posts('oznam')->publish;

        return [
            [
                'title' => __('Prejsť úvodným sprievodcom', 'farnost-plugin'),
                'done'  => $wizardCompleted,
                'url'   => WizardPage::url(),
                'cta'   => __('Otvoriť wizard', 'farnost-plugin'),
            ],
            [
                'title' => __('Vyplniť názov farnosti', 'farnost-plugin'),
                'done'  => $nazovFilled,
                'url'   => admin_url('admin.php?page=farnost-nastavenia'),
                'cta'   => __('Nastavenia', 'farnost-plugin'),
            ],
            [
                'title' => __('Vyplniť kontakt (adresa, telefón alebo e-mail)', 'farnost-plugin'),
                'done'  => $kontaktFilled,
                'url'   => admin_url('admin.php?page=farnost-nastavenia'),
                'cta'   => __('Nastavenia', 'farnost-plugin'),
            ],
            [
                'title' => __('Pridať aspoň jeden kostol s rozpisom omší', 'farnost-plugin'),
                'done'  => $kostolCount > 0,
                'url'   => admin_url('admin.php?page=' . KostolyPage::SLUG),
                'cta'   => __('Pridať kostol', 'farnost-plugin'),
            ],
            [
                'title' => __('Pridať upratovacie skupiny (voliteľné, ale odporúčané)', 'farnost-plugin'),
                'done'  => $skupinaCount > 0,
                'url'   => admin_url('admin.php?page=' . UpratovacieSkupinyPage::SLUG),
                'cta'   => __('Pridať skupinu', 'farnost-plugin'),
            ],
            [
                'title' => __('Publikovať prvý oznam', 'farnost-plugin'),
                'done'  => $oznamPublishCount > 0,
                'url'   => admin_url('edit.php?post_type=oznam'),
                'cta'   => __('Otvoriť oznamy', 'farnost-plugin'),
            ],
        ];
    }
}
