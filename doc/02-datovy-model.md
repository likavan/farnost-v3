# Dátový model

Tento dokument definuje **dátovú vrstvu**: aké entity existujú, kde sa ukladajú a ako sa editujú.

## Architektúrne princípy

1. **Všetka dátová vrstva žije v `farnost-plugin`** — CPT, post meta, term meta, settings sa registrujú v plugine. Téma ich len vykresľuje. Pri zmene témy (alebo úplnej deaktivácii) dáta zostávajú nedotknuté.
2. **Žiadne platené externé závislosti** — používame natívne `register_post_type`, `register_post_meta`, `register_term_meta`, `register_setting`. Žiadne ACF Pro, MetaBox Pro, Toolset.
3. **Block‑editor natívne UX** — komplexné polia sa editujú cez **React sidebar panely** (`PluginDocumentSettingPanel`) postavené na `@wordpress/components`. Žiadne staré PHP `add_meta_box` šablóny pre nový kód.
4. **REST‑first** — všetky meta sú `show_in_rest: true`. Frontend bloky aj externé klienti čítajú dáta cez WP REST API (`/wp-json/wp/v2/...`).
5. **Multi‑tenant agnostic** — žiadne polia hardcoded na konkrétnu farnosť. Per‑farnosť údaje sú v [Settings](#per-farnosť-settings).

## Prehľad entít

| Entita              | Typ              | Účel                                                    |
| ------------------- | ---------------- | ------------------------------------------------------- |
| `kostol`            | CPT              | Kostol / kaplnka + pravidelný týždenný rozpis omší      |
| `oznam`             | CPT              | Týždenný / aktuálny farský oznam                        |
| `omsa_vynimka`      | CPT              | Výnimka voči pravidelnému rozpisu pre konkrétny dátum   |
| `umysel`            | CPT              | Úmysel viažuci sa na konkrétnu inštanciu omše           |
| `upratovacia_skupina` | CPT            | Skupina farníkov upratujúca kostol, v cyklickej rotácii  |
| `post`              | natívny WP       | Udalosti, pozvánky, zo života farnosti                  |
| `category`          | natívna WP taxonómia | Triedenie postov                                    |
| `page`              | natívny WP       | Kontakt, O farnosti, Sviatosti — statický obsah         |
| `farnost_settings`   | WP option        | Per‑farnosť identita (názov, adresa, IBAN, sociálne)   |

## CPT `kostol`

| Atribút           | Hodnota                                                |
| ----------------- | ------------------------------------------------------ |
| Slug              | `kostol`                                                |
| REST base         | `kostoly`                                               |
| Public            | `true`                                                  |
| Has archive       | `true` (`/kostoly/`)                                    |
| Supports          | `title`, `editor`, `thumbnail`, `excerpt`, `revisions`  |
| `show_in_rest`    | `true`                                                  |
| Menu icon         | `dashicons-bank` (alebo vlastný SVG)                    |

### Post meta

| Meta key              | Typ            | Popis                                                                |
| --------------------- | -------------- | -------------------------------------------------------------------- |
| `farnost_adresa`       | string         | Adresa pre Google Maps                                                |
| `farnost_gps_lat`      | number         | Voliteľné, pre mapku                                                  |
| `farnost_gps_lng`      | number         | Voliteľné, pre mapku                                                  |
| `farnost_je_hlavny`    | bool           | Farský kostol — práve jeden naprieč inštaláciou (plugin validuje)    |
| `farnost_rozpis`       | JSON (string)  | Pravidelný týždenný rozpis omší (viď nižšie)                          |

### `farnost_rozpis` — formát

Jedno JSON-encoded post meta `farnost_rozpis`, hodnota je pole položiek:

```json
[
  { "den": "ne", "cas": "07:30", "typ": "omsa", "poznamka": "", "platnost_od": null, "platnost_do": null },
  { "den": "ne", "cas": "10:30", "typ": "omsa", "poznamka": "pre deti", "platnost_od": null, "platnost_do": null },
  { "den": "st", "cas": "18:00", "typ": "omsa", "poznamka": "", "platnost_od": "2026-03-04", "platnost_do": null },
  { "den": "pi", "cas": "17:00", "typ": "krizova_cesta", "poznamka": "len v Pôste", "platnost_od": "2026-02-18", "platnost_do": "2026-04-04" }
]
```

| Pole          | Typ                                | Popis                                              |
| ------------- | ---------------------------------- | -------------------------------------------------- |
| `den`         | `po`, `ut`, `st`, `st`, `pi`, `so`, `ne` | Deň v týždni                                  |
| `cas`         | `HH:MM` (`Europe/Bratislava`)      | Čas konania                                        |
| `typ`         | `omsa`, `adoracia`, `krizova_cesta`, `pobozenstvo`, `ine` | Typ          |
| `poznamka`    | string                             | Voľný text (napr. „pre deti", „latinsky")          |
| `platnost_od` | `YYYY-MM-DD` alebo `null`          | Sezónne obmedzenie od                              |
| `platnost_do` | `YYYY-MM-DD` alebo `null`          | Sezónne obmedzenie do                              |

### Editor UX

Custom **React sidebar panel** `RozpisOmsiPanel` registrovaný cez `PluginDocumentSettingPanel`, aktívny len pre post type `kostol`. Komponenty:

- Tabuľka pre každý deň v týždni so zoznamom omší.
- Tlačidlo **„Pridať omšu"** pre každý deň → modal s poliami `cas`, `typ`, `poznamka`, `platnost_od/do`.
- Drag-to-reorder v rámci jedného dňa (voliteľné v MVP).
- Validácia formátu `HH:MM` a unikátnosti časov v rámci dňa.

Postavené na `@wordpress/data` (`useEntityProp`) — auto-save spolu s ostatným postom.

## CPT `oznam`

| Atribút           | Hodnota                                                |
| ----------------- | ------------------------------------------------------ |
| Slug              | `oznam`                                                |
| REST base         | `oznamy`                                               |
| Public            | `true`                                                 |
| Has archive       | `true` (`/oznamy/`)                                    |
| Supports          | `title`, `editor`, `thumbnail`, `excerpt`, `revisions`, `author` |
| `show_in_rest`    | `true`                                                 |

### Post meta

| Meta key              | Typ         | Popis                                                       |
| --------------------- | ----------- | ----------------------------------------------------------- |
| `farnost_platnost_od`  | `YYYY-MM-DD`| Týždeň/obdobie, na ktoré sa oznam vzťahuje (od)              |
| `farnost_platnost_do`  | `YYYY-MM-DD`| Týždeň/obdobie (do)                                          |
| `farnost_pripnuty`     | bool        | Trvalo zvýraznený oznam (pin) — má prednosť vo feede        |

Stav publikovania (`draft` / `publish` / `future`) využíva natívny mechanizmus WP.

### Editor UX

Sidebar panel `OznamPanel` (PluginDocumentSettingPanel) s tromi poľami: dva `DateTimePicker` pre `platnost_od/do` a `ToggleControl` pre `pripnuty`. Featured image cez natívnu WP funkciu.

## CPT `omsa_vynimka`

| Atribút           | Hodnota                                                |
| ----------------- | ------------------------------------------------------ |
| Slug              | `omsa_vynimka`                                         |
| REST base         | `vynimky-omsi`                                         |
| Public            | `false` (nemá vlastnú stránku)                         |
| Has archive       | `false`                                                |
| Supports          | `title` (auto-generovaný z `farnost_datum + kostol`)    |
| `show_in_rest`    | `true`                                                 |
| Menu              | Submenu pod `Sv. omše` (vlastné top-level admin menu)  |

### Post meta

| Meta key            | Typ                                | Popis                                                       |
| ------------------- | ---------------------------------- | ----------------------------------------------------------- |
| `farnost_datum`      | `YYYY-MM-DD`                       | Dátum výnimky                                               |
| `farnost_kostol_id`  | int (post ID `kostol`)             | Ktorý kostol                                                |
| `farnost_rezim`      | `nahrada`, `pridana`, `zrusena`    | Režim výnimky                                               |
| `farnost_cas`        | `HH:MM` alebo `null`               | Pre `nahrada`/`pridana` — nový čas                          |
| `farnost_nahradza_cas` | `HH:MM` alebo `null`             | Pre `nahrada` — ktorý pôvodný čas nahrádza                  |
| `farnost_typ`        | enum alebo `null`                  | Pre `nahrada`/`pridana` — nový typ                          |
| `farnost_poznamka`   | string                             | Voľný text (napr. „pohreb †Jana M.", „prikázaný sviatok")   |

### Editor UX

Sidebar panel `VynimkaPanel` so všetkými poľami. Pole `farnost_kostol_id` je `ComboboxControl` s prepojením na CPT `kostol` cez REST.

## CPT `umysel`

| Atribút           | Hodnota                                                |
| ----------------- | ------------------------------------------------------ |
| Slug              | `umysel`                                               |
| REST base         | `umysly`                                               |
| Public            | `true`                                                 |
| Has archive       | `true` (`/umysly/`)                                    |
| Supports          | `title` (auto z dátumu a kostola), `author`            |
| `show_in_rest`    | `true`                                                 |

### Post meta

| Meta key              | Typ                       | Popis                                                   |
| --------------------- | ------------------------- | ------------------------------------------------------- |
| `farnost_datum`        | `YYYY-MM-DD`              | Dátum omše                                              |
| `farnost_cas`          | `HH:MM`                   | Čas omše                                                |
| `farnost_kostol_id`    | int (post ID `kostol`)    | Kde sa omša slávi                                       |
| `farnost_text`         | string                    | Krátky text úmyslu (1–3 riadky)                         |
| `farnost_anonymny`     | bool                      | Ak `true`, frontend zobrazí „súkromný úmysel"            |

Úmysel sa **neviaže na záznam `kostol.rozpis`** cez foreign key (pravidelné omše nie sú samostatné záznamy). Viaže sa na trojicu `(datum, cas, kostol_id)`. Frontend pri zobrazení rozpisu na konkrétny deň načíta úmysly pre dané `(datum, kostol_id)` a spáruje ich s časom.

## CPT `upratovacia_skupina`

Modeluje skupinu farníkov, ktorá sa stará o poriadok v kostole. Skupiny sa cyklicky striedajú; rotáciu riadi globálny pointer (viď [Per-farnosť settings](#per-farnosť-settings) — `farnost_upratovanie_dalsia_skupina`).

| Atribút           | Hodnota                                                |
| ----------------- | ------------------------------------------------------ |
| Slug              | `upratovacia_skupina`                                  |
| REST base         | `upratovacie-skupiny`                                  |
| Public            | `false` (frontend ich nemá vlastnú stránku — žijú len v ozname) |
| Has archive       | `false`                                                |
| Supports          | `title`, `page-attributes` (poradie cez `menu_order`)  |
| `show_in_rest`    | `true`                                                 |
| Menu icon         | `dashicons-groups` (alebo vlastný SVG)                 |

### Post meta

| Meta key                 | Typ     | Popis                                                |
| ------------------------ | ------- | ---------------------------------------------------- |
| `farnost_skupina_kontakt` | string  | Telefón / e-mail na vedúcu skupiny (voliteľné)       |
| `farnost_skupina_clenovia`| string  | Voľný text s menami členov („Mária N., Anna K., …") |

### Rotácia

- **Poradie** rotácie sa určuje hodnotou `menu_order` (drag-and-drop v admine).
- **Pointer** je v `farnost_settings.upratovanie_dalsia_skupina` (post ID skupiny, ktorá je „ďalšia na rade").
- **Pri publikácii oznamu** systém:
  1. Prečíta hodnotu pointra, vyrenderuje názov skupiny do oznamu.
  2. **Posunie pointer** na ďalšiu skupinu v poradí (modulo počet skupín).
- **Manuálny override** — farár v admine môže kedykoľvek kliknúť na ľubovoľnú skupinu → akcia „Nastaviť ako ďalšiu na rade", čím sa pointer prepíše. Použitie: po Vianočnom kombinovanom upratovaní farár posunie pointer o N pozícií dopredu, aby cyklus pokračoval tam, kde má.
- **Inline override v ozname** — pre kombinované týždne farár upraví riadok v ozname inline (snapshot model). To nemení pointer; pre korekciu cyklu treba dodatočne posunúť pointer v admine.

## WP posty pre udalosti

Bežný typ `post`. Plugin pridáva post meta cez `register_post_meta('post', ...)`:

| Meta key              | Typ                       | Popis                                                   |
| --------------------- | ------------------------- | ------------------------------------------------------- |
| `farnost_event_when`   | `YYYY-MM-DDTHH:MM` (ISO)  | Kedy sa udalosť koná. Voliteľné.                         |
| `farnost_event_where`  | string                    | Voľný text s miestom. Voliteľné.                         |

### Editor UX

Sidebar panel `UdalostPanel` (`PluginDocumentSettingPanel`) na všetkých postoch. Komponenty:

- `DateTimePicker` pre `farnost_event_when` (s tlačidlom „Vymazať" pre vyprázdnenie).
- `TextControl` pre `farnost_event_where`.

Panel je viditeľný na všetkých postoch — farár ho vyplní len keď to dáva zmysel.

## Kategórie postov (`category`)

Natívna WP taxonómia. Plugin registruje term meta:

| Meta key                | Typ          | Default      | Popis                                                       |
| ----------------------- | ------------ | ------------ | ----------------------------------------------------------- |
| `farnost_color`          | string (hex) | `#6b7280`    | Farba pre badge vo feede a accent v archíve                  |
| `farnost_show_in_menu`   | bool         | `true`       | Či sa kategória zobrazí v automatickom menu                  |

### Default kategórie (vytvorené pri aktivácii pluginu)

| Názov                   | Slug                  | `farnost_color` | `farnost_show_in_menu` |
| ----------------------- | --------------------- | -------------- | --------------------- |
| Udalosti                | `udalosti`            | `#0ea5e9`      | `true`                |
| Zo života farnosti      | `zo-zivota-farnosti`  | `#22c55e`      | `true`                |
| Pozvánky                | `pozvanky`            | `#f59e0b`      | `true`                |

Konkrétne hex hodnoty sú odporúčania — finálne palety doladí téma cez `theme.json`.

### Editor UX

Term meta polia sú editovateľné v admine `Príspevky → Kategórie` v sekcii **Pridať novú** aj v **Upraviť**. Plugin pridáva polia cez `category_add_form_fields` / `category_edit_form_fields`:

- **Farba** — `<input type="color">` (HTML5 native picker), fallback text input s validáciou hex.
- **Zobraziť v menu** — checkbox.

## Per‑farnosť settings

Plugin registruje **jednu WP option** `farnost_settings` (JSON) cez `register_setting`. Editovaná cez vlastnú admin stránku `Farnosť → Nastavenia` postavenú na `@wordpress/components` (`OptionsPanel` pattern).

Štruktúra:

```json
{
  "identita": {
    "nazov": "Rímskokatolícka farnosť sv. Martina",
    "patrocinium": "sv. Martin z Tours",
    "dekanat": "Trnavský",
    "dioceza": "Trnavská arcidiecéza",
    "rok_zalozenia": 1380
  },
  "kontakt": {
    "adresa": "Hlavná 1, 917 01 Trnava",
    "telefon": "+421 33 ...",
    "email": "farnost@example.sk",
    "web": "https://www.example.sk",
    "uradne_hodiny": "Po–Pi 09:00–11:00, 15:00–17:00"
  },
  "financie": {
    "iban": "SK00 1100 0000 0000 0000 0000",
    "dva_percenta": "12345678/1234",
    "ico": "00000000"
  },
  "socialne": {
    "facebook": "https://www.facebook.com/...",
    "youtube": "https://www.youtube.com/@...",
    "instagram": ""
  },
  "branding": {
    "logo_id": 123,
    "primary_color": "#1e40af"
  },
  "moduly": {
    "oznamy_zapnute": true,
    "umysly_zapnute": true,
    "rozpis_omsi_zapnuty": true,
    "zdielanie_zapnute": true
  },
  "oznamy": {
    "publikacny_den": "sunday",
    "publikacny_cas": "08:00"
  },
  "upratovanie": {
    "dalsia_skupina": 0
  },
  "citaty": [
    { "text": "Boh je láska.", "autor": "1 Jn 4,8" }
  ]
}
```

Bloky a téma čítajú tieto hodnoty cez REST endpoint `/wp-json/farnost/v1/settings` (read-only public — adresa, IBAN atď. sú aj tak verejné údaje, ktoré farnosť dáva do päty).

## Vzťahy

```
kostol            1 ── *  omsa_vynimka      (cez farnost_kostol_id)
kostol            1 ── *  umysel            (cez farnost_kostol_id)
kostol            1 ── 1  rozpis            (JSON v post meta)
oznam             (samostatne)
post (udalost)    *  ── *  category
category          1 ── *  post
farnost_settings   (singleton, WP option)
```

## Pravidlá vyhodnotenia rozpisu

Pri zobrazení rozpisu na konkrétny `(dátum, kostol)`:

1. Načítaj `kostol.farnost_rozpis` a vyber položky, kde:
   - `den` zodpovedá dňu v týždni daného dátumu,
   - dátum spadá do intervalu `[platnost_od, platnost_do]` (ak sú zadané).
2. Načítaj `omsa_vynimka` pre `(datum, kostol_id)` a aplikuj:
   - `zrusena` — odstráň zo zoznamu omšu zhodujúcu sa s `farnost_nahradza_cas` (alebo všetky, ak `farnost_nahradza_cas` je `null`).
   - `nahrada` — nahraď omšu so zhodným `farnost_nahradza_cas` novou (`cas`, `typ`).
   - `pridana` — pridaj novú omšu (`cas`, `typ`).
3. Zoraď podľa `cas` ascendentne.
4. Pre každú výslednú omšu načítaj prislúchajúci `umysel` cez trojicu `(datum, cas, kostol_id)` (môže byť `null`).

Logika je v `farnost-plugin/src/Schedule/Resolver.php`. Frontend bloky volajú `Resolver::for_date($date, $kostol_id)` a dostávajú pole vyhodnotených omší.

## Otvorené otázky

- **Anonymizácia úmyslu**: vo v3 je to bool flag. Ak by sa neskôr chcelo aj „polo-anonymné" (iniciály), bude treba doplniť ďalšie pole alebo enum.
- **Historické úmysly v zozname**: koľko dní dozadu zobrazovať na verejnom archíve? Návrh: rolling window 4 týždne dopredu + 2 týždne dozadu na frontend; v admine bez limitu.
- **Reorder položiek v rozpise omší**: drag-to-reorder v sidebar paneli je nice-to-have, nie blocker pre MVP. Alternatíva: triediť automaticky podľa `cas`.
- **Caching vyhodnoteného rozpisu**: Resolver môže byť pomalý pri veľkom počte výnimiek. Pre MVP necachovať, profilovať pri pilote; ak treba, pridať transient cache invalidovanú pri save výnimky.
- **Sviatok / liturgické dáta**: vo v3 nie sú v dátovom modeli (žiadny CPT `sviatok` ani feed liturgického kalendára). Ak farár chce zobraziť liturgické označenie, dá ho do textu výnimky alebo do názvu oznamu.
- **Featured image pre `kostol`**: post type podporuje `thumbnail`. Použije sa v archíve kostolov a na stránke kostola.
