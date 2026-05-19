# Feedback & open backlog

Pripomienky a TODO z reálneho používania systému (Etapa 3 — frontend
implementácia, máj 2026). Slúži ako pracovný backlog pred ďalšou iteráciou.

Položky sú zoradené podľa zdroja a témy, nie podľa priority. Konkrétne
detaily / scope si dohodneme pred implementáciou každej.

## Frontend — feed

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 35 | **Udalosti — krátky náhľad namiesto plného obsahu** | open | Vo feede zobraziť excerpt (cez `get_the_excerpt` / `wp_trim_words`); plný content až na single page. Pre `oznam` CPT pravdepodobne ponechať plný (týždenný rozpis je relevantný v feede). |
| 37 | **Zmeniť formát oznamov vo feede** | open | Aktuálne renderuje plný post_content (rozpis-snapshot block + paragraph upratuje + voľný text). Upresniť čo má vyzerať inak (excerpt? prvé 2-3 odrážky?). |
| 43 | **Single template pre oznam** | open | Vlastný `templates/single-oznam.html` s page-header (eyebrow "Farské oznamy" + dátum + autor) podľa štýlu feed karty. Aktuálne `single.html` je generický. |
| 45 | **Pridávanie a zobrazenie PDF ako oznamov** | open | Možnosť nahrať PDF (sken/originál tlačených oznamov) a publikovať ho ako oznam vo feede. Open otázky: upload UX, link vs. embedded preview, OCR pre textový obsah / fulltext search. |

## Frontend — galéria

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 36 | **Vlastná galéria (`farnost/gallery` block)** | open | Nahradiť `core/gallery` vlastným dynamic blockom podľa Farnost.html: 1+2 mosaic pre 3 fotky, 2×2 pre 4, big + overlay „+N" pri 5+, custom lightbox so šípkami a počítadlom. UX uploadu (gallery picker cez `wp.media`?), editor v Gutenbergu (custom block alebo wrapper okolo `core/gallery`?). |

## Frontend — footer

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 32 | **Odkazy vo footri** | open | Aktuálne 4 statické externé linky (BB diecéza, KBS, Liturgia hodín, Denný evanjelista). Upresniť čo meniť. |
| 33 | **Kontakt vo footri** | open | Aktuálne 1. stĺpec: nazov + adresa + tel · email zo settings. Upresniť. |
| 34 | **Footer: banka a variabilný symbol preč** | open | 3. stĺpec — odstrániť muted lines (banka, VS). Nechať len IBAN? Alebo celý stĺpec preč? Upresniť. |

## Frontend — sidebar

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 38 | **Sidebar kontakt zmeniť** | open | `ContactWidget` — formát/obsah. Upresniť čo presne meniť. |
| 39 | **Pridať úradné hodiny** | open | `settings.kontakt.uradne_hodiny` (textarea, viacriadkový text) sa zatiaľ nezobrazuje. Pridať do `ContactWidget` v sidebare ako samostatnú sekciu s preserved newlines (`white-space: pre-line`). Možno aj na Kontakt page. |
| 53 | **Sidebar twin-sticky** | open (nice-to-have) | User chce: scroll dole → spodok pripnúť na vp bottom; scroll hore → vrch pripnúť na vp top. Dvakrát implementované cez transform algoritmus, pri rýchlom scrolle "trasie" (rAF throttle, multi-pixel deltas). Revertnuté na default `position: sticky; top: 24px`. Pre kvalitné riešenie pravdepodobne potreba knižnice (StickySidebar.js) alebo CSS scroll-driven animations keď bude širšia podpora. Nice-to-have, neskôr. |

## Admin

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 40 | **Settings: upraviť popisky** | open | Niektoré labely v Farnosť → Nastavenia potrebujú úpravu. Upresniť ktoré sekcie / polia. |
| 46 | **Settings sekcia Moduly — ghost toggles** | open | `farnost_settings.moduly.*` flags (oznamy_zapnute, umysly_zapnute, rozpis_omsi_zapnuty, zdielanie_zapnute) sa zapisujú ale nikde sa nečítajú. Doc/07-admin-ux.md sľubuje že ovplyvňujú menu. Rozhodnutie: implementovať alebo skryť sekciu. |
| 47 | **Settings: odstrániť sekciu Upratovacie skupiny** | open | Dropdown "Ďalšia skupina na rade" v Settings je duplikát — pointer rotácie sa nastavuje priamo v Farnosť → Upratovacie skupiny (drag-and-drop + tlačidlo "Nastaviť ako ďalšiu"). Sekciu zo Settings preč; option key `upratovanie.dalsia_skupina` zostáva (číta BufferManager + Upratovanie). |
| 48 | **Sociálne siete: flexible repeater** | open | Aktuálne `settings.socialne` má 3 fixed polia (facebook, youtube, instagram). Pre flexibilitu (TikTok, X/Twitter, Threads, LinkedIn, Discord, ...) prerobiť na repeater {label, URL, optional ikona slug} ako kontakt.telefony. Frontend (footer/sidebar) potrebuje mapping URL alebo slug → SVG ikonu. |
| 49 | **Settings Financie úpravy** | open | IBAN ✓, Banka ✓. Pridať meno majiteľa účtu (nové pole — SEPA štandard vyžaduje). Odstrániť pole "2 %" (`financie.dva_percenta` preč). IČO — čaká rozhodnutie. |
| 50 | **Footer: QR platba s interaktívnou sumou** | open | Pridať do footra QR platbu pre rýchle darcovstvo. Input na sumu + voliteľný popis, generovať QR dynamicky (PayBySquare SK / EPC SEPA QR). Zdroj zo settings.financie (IBAN + meno majiteľa #49). Knižnica: qrcodejs (client-side) alebo endroid/qrcode (server-side). Závisí od #49. |
| 51 | **UX: dve „Nastavenia" v admin sú mätúce** | open | WP core Nastavenia + Farnosť → Nastavenia → farár nevie kam ísť. Možnosti: (a) premenovať naše na "Profil farnosti" / "Údaje farnosti", (b) skryť WP core pre Editor, (c) konsolidovať (naše absorbuje site title + timezone + …), (d) split na 2 sekcie (Údaje farnosti + Nastavenia webu). Rozhodnúť. |
| 52 | **Citáty: nefungujú + presunúť mimo Nastavení** | open | Citáty (`settings.citaty[]`) cez QuoteWidget rotujú po day-of-year, ale user reportoval že nefungujú — diagnostikovať parsing (Settings::citatyFromText textarea pipe) alebo render. Plus presunúť z Nastavení → samostatná admin obrazovka „Farnosť → Citáty" s riadnym repeater UI (drag-and-drop poradie). |
| 41 | **Skryť niektoré bloky z inserter-u** | open | Filtrovať dostupné Gutenberg bloky cez `allowed_block_types_all`. Upresniť ktoré skryť (napr. social-icons, embeds, core/calendar) a v ktorých post typoch. |
| 42 | **Kalendár omší: edit kostol + označenie omše** | open | V admin React UI pri editácii omše (modal s úmyslom) doplniť možnosť meniť aj kostol a označenie omše. Pre výnimky (`omsa_vynimka`) ide o meta `farnost_kostol_id` a `farnost_oznacenie`. Pre pravidelné omše editácia ide cez Kostoly admin. |

## Layout / responsive (zápisky 2026-05-18)

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 73 | **Šírka containera — doladiť** | open | `.site-main` má `max-width: 1200px` + padding 56px/40px. Upresniť či je container príliš úzky/široký, či sa paddingy zarovnávajú s feed kartami, alebo či sa zmení sidebar šírka (`--sidebar-w: 320px`). |
| 74 | **Sidebar: časy omší wrap na 2 riadky** | open | `.farnost-mass-time { white-space: nowrap }` v kombinácii s `.farnost-mass-times { display: inline-flex; flex-wrap: wrap }` — pri viacerých časoch (napr. nedeľa 7:30 · 9:30 · 11:00 · 18:00) sa wrap nemusí spustiť cleanly. Overiť že separátor `·` neostane sám na začiatku riadku. |
| 75 | **Tooltip popup na mobile zúžiť** | open | Aktuálne tooltip má `left: 0; right: 0` (plnej šírke `.farnost-mass-row` = plnej šírke widgetu). V mobile drawer (360px) je to príliš. Constraint na cca 200-240px (max-width) + zarovnať k row left, alebo center pod čas. |
| 76 | **Mobile menu fullscreen overlay** | open | `.site-nav-list.is-open` je dropdown pod hamburger button-om. User chce full-screen overlay — navigácia centrovaná, plus close X, prípadne backdrop blur (podobný pattern ako sidebar drawer). |
| 77 | **Detail stránka: menšie bočné paddingy** | open | `.farnost-page` (cez `templates/page.html`, `single-oznam.html`, `single.html`) má veľké side paddingy zdedené z `.site-main`. Zmenšiť na 32-48px max alebo plynulý cez `clamp()`. |

## Infraštruktúra / Kvalita

| # | Položka | Stav | Poznámka |
|---|---|---|---|
| 78 | **Integračné testy cez wp-env + Playwright** | open | Pest unit testy pokrývajú pure logiku (Resolver, Gallery, Feed helpers), ale zlyhajú zachytiť real-world regressie pri WP majori (6→7), JS bundle bumpe alebo block.json schema zmenách. Hodí sa smoke suite (~5 testov) na golden path: (a) edit oznam → snapshot refresh → publish → view feed, (b) hover sidebar tooltip s úmyslom, (c) gallery lightbox carousel na detail strane, (d) mobile drawer open/close, (e) permaliny `/oznamy/<slug>/`. Setup: `@wordpress/env` (Docker WP), Playwright config, seed kostoly + posts fixture. ~1 deň práce, navždy zachytí 80% regressí. Kandidát pred WP 7 release. |

## Hotové z dnešnej iterácie (Etapa 3)

Pre referenciu — zoznam commitov ktoré tvorili Etapu 3 (frontend):

- `2504807` — **Vlna A**: kostra block themy (theme.json, header/footer/sidebar parts, templates, statické post karty)
- `3433fff` — **Vlna B**: 5 dynamic blocks (Banner, Feed, MassWidget, ContactWidget, QuoteWidget), time sorting numeric fix
- `f991c11` — **Vlna C**: sub-pages cez patterns (5) + 2 dynamic blocks (ScheduleTable, ArchiveList)
- `97c70f3` + `1a19765` + `ab556ca` — **Vlna D**: editor preview (ServerSideRender), search/404 templates, WeeklyResolver batch query optimization
- `d8e3c0d` — MassWidget zobrazuje úmysly výnimiek
- `b8aede4` — auto-menu z page hierarchie + hamburger mobile
- `11825e9` — feed: reálne WP kategórie + title link
- `a2f92cb` — tablet rozbalený search ako overlay
- `ca39f94` — kategória meta používa `farnost_color` term meta
- `71cb880` — feed top-border vo farbe kategórie, label uniform
- `a65d1a8` — header/footer dynamic blocks zo settings
- `1a2ac4f` — footer dorovnaný s designom (banka, VS, Denný evanjelista) + template-part className
- `71f737a` — header logo z settings + `farnost/site-header` block
- `6668985` + `cb42053` + `be978e4` — ContactWidget stack layout, tesný line-height
- `fe5fd1e` — MassWidget skrýva dni bez omší + kostoly bez omší
- `9a3f9ca` — feed „Čítať viac →" link
- `db6f907` — banner server-side cookie check (no FOUC)
- `cec1a05` — Activator default timezone Europe/Bratislava

Plus dva audit fixy z predchádzajúceho dňa (`f1696e2`, `b8eca47`, `5f11e2f`, `28c5f1c`, `baa3c3e`) a feedback z Etapy 2 (`a602474`, atď.).

## Nepriradené úvahy / možnosti

- **Lightbox force-enable pre core/gallery** — filter ktorý vždy nastaví behaviors.lightbox=true, aby admin nemusel pamätať zapnúť per gallery.
- **Single post (udalosť) template** — vlastný layout s `farnost-udalost-grid` (Kedy/Kde) ako vo feede.
- **Foto mosaic CSS pre core/gallery** — 1+2 / 2×2 layout cez CSS bez vlastného bloku (alternative k #36).
- **Performance optimalizácia ContactWidget / SiteFooter / SiteBrand** — settings sa load-uje 3× per request (1× per blok). Mohli by sme cache-ovať raz per request.
