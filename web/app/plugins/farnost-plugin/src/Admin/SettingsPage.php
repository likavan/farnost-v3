<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\PostTypes\UpratovaciaSkupina;
use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vykresľuje `Farnosť → Nastavenia` ako klasický WP form (PHP, žiadny React build zatiaľ).
 *
 * Štruktúra sekcií zrkadlí `farnost_settings` v doc/02-datovy-model.md a zhoduje sa s krokmi
 * Setup wizardu (doc/07-admin-ux.md → Setup wizard).
 */
final class SettingsPage
{
    public const NONCE_ACTION = 'farnost_settings_save';

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }

        $saved = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer(self::NONCE_ACTION);
            $raw = $_POST['farnost_settings'] ?? [];
            $sanitized = self::sanitize(is_array($raw) ? $raw : []);
            update_option(Settings::OPTION_KEY, $sanitized);
            $saved = true;
        }

        $s = Settings::get();
        $skupiny = self::loadUpratovacieSkupiny();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Nastavenia farnosti', 'farnost-plugin'); ?></h1>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Nastavenia boli uložené.', 'farnost-plugin'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>

                <h2><?php esc_html_e('Identita farnosti', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-nazov"><?php esc_html_e('Názov', 'farnost-plugin'); ?> <span class="description">(<?php esc_html_e('povinné', 'farnost-plugin'); ?>)</span></label></th>
                        <td><input type="text" id="fp-nazov" name="farnost_settings[identita][nazov]" value="<?php echo esc_attr($s['identita']['nazov']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-patrocinium"><?php esc_html_e('Patrón farnosti', 'farnost-plugin'); ?></label></th>
                        <td><input type="text" id="fp-patrocinium" name="farnost_settings[identita][patrocinium]" value="<?php echo esc_attr($s['identita']['patrocinium']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-dekanat"><?php esc_html_e('Dekanát', 'farnost-plugin'); ?></label></th>
                        <td><input type="text" id="fp-dekanat" name="farnost_settings[identita][dekanat]" value="<?php echo esc_attr($s['identita']['dekanat']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-dioceza"><?php esc_html_e('Diecéza', 'farnost-plugin'); ?></label></th>
                        <td><input type="text" id="fp-dioceza" name="farnost_settings[identita][dioceza]" value="<?php echo esc_attr($s['identita']['dioceza']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-rok"><?php esc_html_e('Rok založenia', 'farnost-plugin'); ?></label></th>
                        <td><input type="number" id="fp-rok" name="farnost_settings[identita][rok_zalozenia]" value="<?php echo esc_attr((string) $s['identita']['rok_zalozenia']); ?>" class="small-text" min="0" max="2100" step="1"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Kontakt', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-adresa"><?php esc_html_e('Adresa', 'farnost-plugin'); ?></label></th>
                        <td><input type="text" id="fp-adresa" name="farnost_settings[kontakt][adresa]" value="<?php echo esc_attr($s['kontakt']['adresa']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Telefóny', 'farnost-plugin'); ?></th>
                        <td>
                            <div class="fp-repeater" data-field="telefony" data-popis-placeholder="<?php esc_attr_e('napr. farár', 'farnost-plugin'); ?>" data-value-placeholder="+421 905 ..." data-value-name="cislo">
                                <?php
                                $telefony = is_array($s['kontakt']['telefony'] ?? null) ? $s['kontakt']['telefony'] : [];
                                if (empty($telefony)) {
                                    $telefony = [['popis' => '', 'cislo' => '']];
                                }
                                foreach ($telefony as $i => $row) :
                                    $popis = isset($row['popis']) ? (string) $row['popis'] : '';
                                    $cislo = isset($row['cislo']) ? (string) $row['cislo'] : '';
                                ?>
                                    <div class="fp-repeater-row">
                                        <input type="text" class="regular-text" name="farnost_settings[kontakt][telefony][<?php echo (int) $i; ?>][popis]" value="<?php echo esc_attr($popis); ?>" placeholder="<?php esc_attr_e('napr. farár', 'farnost-plugin'); ?>">
                                        <input type="text" class="regular-text" name="farnost_settings[kontakt][telefony][<?php echo (int) $i; ?>][cislo]" value="<?php echo esc_attr($cislo); ?>" placeholder="+421 905 ...">
                                        <button type="button" class="button-link-delete fp-repeater-remove" aria-label="<?php esc_attr_e('Odstrániť', 'farnost-plugin'); ?>">✕</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-secondary fp-repeater-add" data-target="telefony"><?php esc_html_e('+ Pridať telefón', 'farnost-plugin'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('E-maily', 'farnost-plugin'); ?></th>
                        <td>
                            <div class="fp-repeater" data-field="emaily" data-popis-placeholder="<?php esc_attr_e('napr. farár', 'farnost-plugin'); ?>" data-value-placeholder="meno@farnost.sk" data-value-name="adresa">
                                <?php
                                $emaily = is_array($s['kontakt']['emaily'] ?? null) ? $s['kontakt']['emaily'] : [];
                                if (empty($emaily)) {
                                    $emaily = [['popis' => '', 'adresa' => '']];
                                }
                                foreach ($emaily as $i => $row) :
                                    $popis = isset($row['popis']) ? (string) $row['popis'] : '';
                                    $adr   = isset($row['adresa']) ? (string) $row['adresa'] : '';
                                ?>
                                    <div class="fp-repeater-row">
                                        <input type="text" class="regular-text" name="farnost_settings[kontakt][emaily][<?php echo (int) $i; ?>][popis]" value="<?php echo esc_attr($popis); ?>" placeholder="<?php esc_attr_e('napr. farár', 'farnost-plugin'); ?>">
                                        <input type="email" class="regular-text" name="farnost_settings[kontakt][emaily][<?php echo (int) $i; ?>][adresa]" value="<?php echo esc_attr($adr); ?>" placeholder="meno@farnost.sk">
                                        <button type="button" class="button-link-delete fp-repeater-remove" aria-label="<?php esc_attr_e('Odstrániť', 'farnost-plugin'); ?>">✕</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-secondary fp-repeater-add" data-target="emaily"><?php esc_html_e('+ Pridať e-mail', 'farnost-plugin'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-web"><?php esc_html_e('Web', 'farnost-plugin'); ?></label></th>
                        <td><input type="url" id="fp-web" name="farnost_settings[kontakt][web]" value="<?php echo esc_attr($s['kontakt']['web']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-uh"><?php esc_html_e('Úradné hodiny', 'farnost-plugin'); ?></label></th>
                        <td>
                            <textarea id="fp-uh" name="farnost_settings[kontakt][uradne_hodiny]" rows="5" class="large-text" placeholder="Pondelok–Piatok: 09:00–11:00&#10;Utorok, Štvrtok: 15:00–17:00&#10;Sobota, Nedeľa: zatvorené"><?php echo esc_textarea($s['kontakt']['uradne_hodiny']); ?></textarea>
                            <p class="description"><?php esc_html_e('Voľný text — jeden riadok = jeden časový blok alebo deň. Frontend zachová zalomenia riadkov. Stačí napríklad „Po telefonickej dohode."', 'farnost-plugin'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Financie', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-iban">IBAN</label></th>
                        <td><input type="text" id="fp-iban" name="farnost_settings[financie][iban]" value="<?php echo esc_attr($s['financie']['iban']); ?>" class="regular-text" placeholder="SK00 0000 0000 0000 0000 0000"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-dvap">2 %</label></th>
                        <td><input type="text" id="fp-dvap" name="farnost_settings[financie][dva_percenta]" value="<?php echo esc_attr($s['financie']['dva_percenta']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-ico">IČO</label></th>
                        <td><input type="text" id="fp-ico" name="farnost_settings[financie][ico]" value="<?php echo esc_attr($s['financie']['ico']); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Sociálne siete', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-fb">Facebook</label></th>
                        <td><input type="url" id="fp-fb" name="farnost_settings[socialne][facebook]" value="<?php echo esc_attr($s['socialne']['facebook']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-yt">YouTube</label></th>
                        <td><input type="url" id="fp-yt" name="farnost_settings[socialne][youtube]" value="<?php echo esc_attr($s['socialne']['youtube']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-ig">Instagram</label></th>
                        <td><input type="url" id="fp-ig" name="farnost_settings[socialne][instagram]" value="<?php echo esc_attr($s['socialne']['instagram']); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Logo a farby', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo farnosti', 'farnost-plugin'); ?></th>
                        <td>
                            <?php
                            $logoId = (int) $s['branding']['logo_id'];
                            $logoUrl = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';
                            ?>
                            <div class="fp-media-picker">
                                <div class="fp-media-preview" style="margin-bottom:8px;">
                                    <?php if ($logoUrl !== '') : ?>
                                        <img src="<?php echo esc_url($logoUrl); ?>" alt="" style="max-width:240px;max-height:160px;height:auto;border:1px solid #e5e7eb;border-radius:4px;padding:4px;background:#fff;">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" class="fp-media-id" name="farnost_settings[branding][logo_id]" value="<?php echo esc_attr((string) $logoId); ?>">
                                <button type="button" class="button fp-media-pick"><?php esc_html_e('Vybrať z knižnice médií', 'farnost-plugin'); ?></button>
                                <button type="button" class="button fp-media-remove" style="<?php echo $logoId > 0 ? '' : 'display:none;'; ?>"><?php esc_html_e('Odstrániť', 'farnost-plugin'); ?></button>
                                <p class="description"><?php esc_html_e('Odporúčaný formát: PNG alebo SVG s priehľadným pozadím, šírka aspoň 480 px.', 'farnost-plugin'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-color"><?php esc_html_e('Primárna farba', 'farnost-plugin'); ?></label></th>
                        <td><input type="color" id="fp-color" name="farnost_settings[branding][primary_color]" value="<?php echo esc_attr($s['branding']['primary_color']); ?>"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Moduly', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Oznamy', 'farnost-plugin'); ?></th>
                        <td><label><input type="checkbox" name="farnost_settings[moduly][oznamy_zapnute]" value="1" <?php checked($s['moduly']['oznamy_zapnute']); ?>> <?php esc_html_e('CPT oznam, predvyplnená šablóna, životný cyklus s archívom', 'farnost-plugin'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Úmysly', 'farnost-plugin'); ?></th>
                        <td><label><input type="checkbox" name="farnost_settings[moduly][umysly_zapnute]" value="1" <?php checked($s['moduly']['umysly_zapnute']); ?>> <?php esc_html_e('Kalendár úmyslov, auto-dotiahnutie do oznamu', 'farnost-plugin'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rozpis omší', 'farnost-plugin'); ?></th>
                        <td><label><input type="checkbox" name="farnost_settings[moduly][rozpis_omsi_zapnuty]" value="1" <?php checked($s['moduly']['rozpis_omsi_zapnuty']); ?>> <?php esc_html_e('CPT kostol + výnimky, sidebar widget „Najbližšia omša"', 'farnost-plugin'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Zdieľanie', 'farnost-plugin'); ?></th>
                        <td><label><input type="checkbox" name="farnost_settings[moduly][zdielanie_zapnute]" value="1" <?php checked($s['moduly']['zdielanie_zapnute']); ?>> <?php esc_html_e('Tlačidlá Facebook / WhatsApp / Kopírovať odkaz pod detailom oznamu', 'farnost-plugin'); ?></label></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Oznamy — publikačný rytmus', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-pden"><?php esc_html_e('Deň', 'farnost-plugin'); ?></label></th>
                        <td>
                            <select id="fp-pden" name="farnost_settings[oznamy][publikacny_den]">
                                <?php foreach (self::days() as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($s['oznamy']['publikacny_den'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-pcas"><?php esc_html_e('Čas', 'farnost-plugin'); ?></label></th>
                        <td><input type="time" id="fp-pcas" name="farnost_settings[oznamy][publikacny_cas]" value="<?php echo esc_attr($s['oznamy']['publikacny_cas']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fp-dopredne"><?php esc_html_e('Dopredné drafty', 'farnost-plugin'); ?></label></th>
                        <td>
                            <input type="number" id="fp-dopredne" name="farnost_settings[oznamy][dopredne_drafty]" value="<?php echo esc_attr((string) ($s['oznamy']['dopredne_drafty'] ?? 2)); ?>" min="1" max="4" class="small-text">
                            <p class="description"><?php esc_html_e('Koľko budúcich oznamov má systém držať pripravených (status „naplánované"). Default 2 — tento týždeň a nasledujúci.', 'farnost-plugin'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Upratovacie skupiny', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-uprat"><?php esc_html_e('Ďalšia skupina na rade', 'farnost-plugin'); ?></label></th>
                        <td>
                            <?php if (empty($skupiny)) : ?>
                                <p class="description"><?php esc_html_e('Najskôr pridajte upratovacie skupiny v menu „Farnosť → Upratovacie skupiny".', 'farnost-plugin'); ?></p>
                                <input type="hidden" name="farnost_settings[upratovanie][dalsia_skupina]" value="0">
                            <?php else : ?>
                                <select id="fp-uprat" name="farnost_settings[upratovanie][dalsia_skupina]">
                                    <option value="0" <?php selected($s['upratovanie']['dalsia_skupina'], 0); ?>>— <?php esc_html_e('nezvolené', 'farnost-plugin'); ?> —</option>
                                    <?php foreach ($skupiny as $sk) : ?>
                                        <option value="<?php echo esc_attr((string) $sk->ID); ?>" <?php selected($s['upratovanie']['dalsia_skupina'], $sk->ID); ?>><?php echo esc_html($sk->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Skupina, ktorá je nasledujúca v rotácii pri tvorbe oznamu.', 'farnost-plugin'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Citáty pre sidebar', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp-citaty"><?php esc_html_e('Zoznam citátov', 'farnost-plugin'); ?></label></th>
                        <td>
                            <textarea id="fp-citaty" name="farnost_settings[citaty]" rows="8" class="large-text code" placeholder="Boh je láska. | 1 Jn 4,8"><?php echo esc_textarea(self::citatyToText($s['citaty'])); ?></textarea>
                            <p class="description"><?php esc_html_e('Jeden citát = jeden riadok. Voliteľný autor / zdroj sa oddeľuje znakom „|" (napr. „Boh je láska. | 1 Jn 4,8"). Plugin obsahuje prednastavený zoznam, sem si môžete dopísať vlastné.', 'farnost-plugin'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Uložiť zmeny', 'farnost-plugin'); ?></button>
                </p>
            </form>
        </div>

        <style>
            .fp-repeater { display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px; }
            .fp-repeater-row { display: flex; gap: 8px; align-items: center; }
            .fp-repeater-row input[type="text"],
            .fp-repeater-row input[type="email"] { flex: 1 1 auto; }
            .fp-repeater-row .fp-repeater-remove { color: #b32d2e; background: none; border: 0; cursor: pointer; font-size: 16px; padding: 4px 6px; }
            .fp-repeater-row .fp-repeater-remove:hover { color: #fff; background: #b32d2e; border-radius: 4px; }
        </style>

        <script>
        (function () {
            document.querySelectorAll('.fp-repeater-add').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetField = btn.getAttribute('data-target');
                    var container = document.querySelector('.fp-repeater[data-field="' + targetField + '"]');
                    if (!container) return;

                    var existing = container.querySelectorAll('.fp-repeater-row').length;
                    var valueName = container.getAttribute('data-value-name') || 'cislo';
                    var valuePh = container.getAttribute('data-value-placeholder') || '';
                    var popisPh = container.getAttribute('data-popis-placeholder') || '';
                    var valueType = (valueName === 'adresa') ? 'email' : 'text';

                    var row = document.createElement('div');
                    row.className = 'fp-repeater-row';
                    row.innerHTML =
                        '<input type="text" class="regular-text" name="farnost_settings[kontakt][' + targetField + '][' + existing + '][popis]" placeholder="' + popisPh + '">' +
                        '<input type="' + valueType + '" class="regular-text" name="farnost_settings[kontakt][' + targetField + '][' + existing + '][' + valueName + ']" placeholder="' + valuePh + '">' +
                        '<button type="button" class="button-link-delete fp-repeater-remove" aria-label="Odstrániť">✕</button>';

                    container.appendChild(row);
                    bindRemove(row.querySelector('.fp-repeater-remove'));
                });
            });

            function bindRemove(btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.fp-repeater-row');
                    if (!row) return;
                    var container = row.parentElement;
                    row.remove();
                    // Re-index zostávajúce riadky, aby PHP dostal súvislý array.
                    container.querySelectorAll('.fp-repeater-row').forEach(function (r, idx) {
                        r.querySelectorAll('input[name]').forEach(function (input) {
                            input.name = input.name.replace(/\[\d+\]/, '[' + idx + ']');
                        });
                    });
                });
            }

            document.querySelectorAll('.fp-repeater-remove').forEach(bindRemove);

            // Media picker pre logo
            document.querySelectorAll('.fp-media-pick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (typeof wp === 'undefined' || !wp.media) {
                        alert('WP Media JS sa nenačítalo.');
                        return;
                    }
                    var container = btn.closest('.fp-media-picker');
                    var frame = wp.media({
                        title: '<?php echo esc_js(__('Vyberte logo farnosti', 'farnost-plugin')); ?>',
                        multiple: false,
                        library: { type: 'image' },
                        button: { text: '<?php echo esc_js(__('Použiť ako logo', 'farnost-plugin')); ?>' },
                    });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                        container.querySelector('.fp-media-id').value = att.id;
                        var preview = container.querySelector('.fp-media-preview');
                        preview.innerHTML = '<img src="' + url + '" alt="" style="max-width:240px;max-height:160px;height:auto;border:1px solid #e5e7eb;border-radius:4px;padding:4px;background:#fff;">';
                        var removeBtn = container.querySelector('.fp-media-remove');
                        if (removeBtn) removeBtn.style.display = '';
                    });
                    frame.open();
                });
            });

            document.querySelectorAll('.fp-media-remove').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var container = btn.closest('.fp-media-picker');
                    container.querySelector('.fp-media-id').value = '0';
                    container.querySelector('.fp-media-preview').innerHTML = '';
                    btn.style.display = 'none';
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $defaults = Settings::defaults();

        $out = $defaults;
        $out['identita']['nazov']         = isset($input['identita']['nazov']) ? sanitize_text_field((string) $input['identita']['nazov']) : '';
        $out['identita']['patrocinium']   = isset($input['identita']['patrocinium']) ? sanitize_text_field((string) $input['identita']['patrocinium']) : '';
        $out['identita']['dekanat']       = isset($input['identita']['dekanat']) ? sanitize_text_field((string) $input['identita']['dekanat']) : '';
        $out['identita']['dioceza']       = isset($input['identita']['dioceza']) ? sanitize_text_field((string) $input['identita']['dioceza']) : '';
        $out['identita']['rok_zalozenia'] = isset($input['identita']['rok_zalozenia']) ? max(0, (int) $input['identita']['rok_zalozenia']) : 0;

        $out['kontakt']['adresa']        = isset($input['kontakt']['adresa']) ? sanitize_text_field((string) $input['kontakt']['adresa']) : '';
        $out['kontakt']['telefony']      = self::sanitizeContactList($input['kontakt']['telefony'] ?? [], 'cislo', false);
        $out['kontakt']['emaily']        = self::sanitizeContactList($input['kontakt']['emaily'] ?? [], 'adresa', true);
        $out['kontakt']['web']           = isset($input['kontakt']['web']) ? esc_url_raw((string) $input['kontakt']['web']) : '';
        $out['kontakt']['uradne_hodiny'] = isset($input['kontakt']['uradne_hodiny']) ? sanitize_textarea_field((string) $input['kontakt']['uradne_hodiny']) : '';

        $out['financie']['iban']         = isset($input['financie']['iban']) ? sanitize_text_field((string) $input['financie']['iban']) : '';
        $out['financie']['dva_percenta'] = isset($input['financie']['dva_percenta']) ? sanitize_text_field((string) $input['financie']['dva_percenta']) : '';
        $out['financie']['ico']          = isset($input['financie']['ico']) ? sanitize_text_field((string) $input['financie']['ico']) : '';

        $out['socialne']['facebook']  = isset($input['socialne']['facebook']) ? esc_url_raw((string) $input['socialne']['facebook']) : '';
        $out['socialne']['youtube']   = isset($input['socialne']['youtube']) ? esc_url_raw((string) $input['socialne']['youtube']) : '';
        $out['socialne']['instagram'] = isset($input['socialne']['instagram']) ? esc_url_raw((string) $input['socialne']['instagram']) : '';

        $out['branding']['logo_id']       = isset($input['branding']['logo_id']) ? max(0, (int) $input['branding']['logo_id']) : 0;
        $out['branding']['primary_color'] = isset($input['branding']['primary_color']) ? self::sanitizeColor((string) $input['branding']['primary_color']) : '#1e40af';

        $out['moduly']['oznamy_zapnute']      = !empty($input['moduly']['oznamy_zapnute']);
        $out['moduly']['umysly_zapnute']      = !empty($input['moduly']['umysly_zapnute']);
        $out['moduly']['rozpis_omsi_zapnuty'] = !empty($input['moduly']['rozpis_omsi_zapnuty']);
        $out['moduly']['zdielanie_zapnute']   = !empty($input['moduly']['zdielanie_zapnute']);

        $den = isset($input['oznamy']['publikacny_den']) ? (string) $input['oznamy']['publikacny_den'] : 'sunday';
        $out['oznamy']['publikacny_den'] = array_key_exists($den, self::days()) ? $den : 'sunday';
        $cas = isset($input['oznamy']['publikacny_cas']) ? (string) $input['oznamy']['publikacny_cas'] : '08:00';
        $out['oznamy']['publikacny_cas'] = preg_match('/^\d{2}:\d{2}$/', $cas) === 1 ? $cas : '08:00';
        $dopredne = isset($input['oznamy']['dopredne_drafty']) ? (int) $input['oznamy']['dopredne_drafty'] : 2;
        $out['oznamy']['dopredne_drafty'] = max(1, min(4, $dopredne));

        $out['upratovanie']['dalsia_skupina'] = isset($input['upratovanie']['dalsia_skupina']) ? max(0, (int) $input['upratovanie']['dalsia_skupina']) : 0;

        $citatyText = isset($input['citaty']) && is_string($input['citaty']) ? $input['citaty'] : '';
        $out['citaty'] = self::citatyFromText($citatyText);

        // Polia, ktoré sa nespravujú cez tento form — zachovať z aktuálnych settings,
        // aby ich save neprepísal na defaults. Konkrétne `setup.completed` (riadi sa
        // wizard-om); bez tohto by každé uloženie Nastavení znova spustilo wizard.
        $current = Settings::get();
        $out['setup'] = $current['setup'] ?? $defaults['setup'];

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function days(): array
    {
        return [
            'monday'    => __('Pondelok', 'farnost-plugin'),
            'tuesday'   => __('Utorok', 'farnost-plugin'),
            'wednesday' => __('Streda', 'farnost-plugin'),
            'thursday'  => __('Štvrtok', 'farnost-plugin'),
            'friday'    => __('Piatok', 'farnost-plugin'),
            'saturday'  => __('Sobota', 'farnost-plugin'),
            'sunday'    => __('Nedeľa', 'farnost-plugin'),
        ];
    }

    /**
     * @param mixed  $input         Surová hodnota z $_POST.
     * @param string $valueKey      Názov poľa s hodnotou (cislo / adresa).
     * @param bool   $isEmail       Či sanitizovať hodnotu ako e-mail.
     * @return array<int, array{popis: string, cislo?: string, adresa?: string}>
     */
    private static function sanitizeContactList(mixed $input, string $valueKey, bool $isEmail): array
    {
        if (!is_array($input)) {
            return [];
        }
        $out = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $popis = isset($row['popis']) ? sanitize_text_field((string) $row['popis']) : '';
            $val   = isset($row[$valueKey]) ? (string) $row[$valueKey] : '';
            $val   = $isEmail ? sanitize_email($val) : sanitize_text_field($val);
            if ($val === '') {
                continue; // prázdne riadky preskočiť
            }
            $out[] = [
                'popis'    => $popis,
                $valueKey  => $val,
            ];
        }
        return $out;
    }

    private static function sanitizeColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value) === 1) {
            return strtolower($value);
        }
        return '#1e40af';
    }

    /**
     * @param array<int, array{text: string, autor?: string}> $citaty
     */
    private static function citatyToText(array $citaty): string
    {
        $lines = [];
        foreach ($citaty as $c) {
            $text = isset($c['text']) ? (string) $c['text'] : '';
            $autor = isset($c['autor']) ? (string) $c['autor'] : '';
            if ($text === '') {
                continue;
            }
            $lines[] = $autor === '' ? $text : "{$text} | {$autor}";
        }
        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{text: string, autor: string}>
     */
    private static function citatyFromText(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = [
                'text'  => sanitize_text_field((string) $parts[0]),
                'autor' => isset($parts[1]) ? sanitize_text_field((string) $parts[1]) : '',
            ];
        }
        return $out;
    }

    /**
     * @return array<int, \WP_Post>
     */
    private static function loadUpratovacieSkupiny(): array
    {
        $posts = get_posts([
            'post_type'      => UpratovaciaSkupina::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);
        return is_array($posts) ? $posts : [];
    }
}
