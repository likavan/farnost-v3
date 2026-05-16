# Roadmap

Etapy implementácie pre v3. Časové odhady sú orientačné a počítajú s jedným vývojárom na čiastočný úväzok. Každá etapa má **definíciu hotovosti**, ktorá je kontrolovaná pred prechodom na ďalšiu.

## Etapa 0 — Bootstrap ✅

**Cieľ**: prázdny ale funkčný Bedrock projekt s prázdnymi scaffoldmi pluginu a témy, ktorý sa dá lokálne spustiť.

- [x] `composer create-project roots/bedrock .` v koreňovom priečinku.
- [x] Lokálny dev environment (DDEV — `.ddev/config.yaml`). PHP 8.5, ARM64 DDEV, porty 8080 / 8443 (Docker Desktop bez vmnetd helpera neumožňuje 80/443).
- [x] `.env.example` zo šablóny + `.env` lokálne s DB údajmi.
- [x] Inštalácia WP cez `wp core install`, vytvorenie admin účtu (`admin` / `admin`).
- [x] Scaffold pluginu `farnost-plugin` (plugin header, `composer.json` s PSR-4, `package.json` s `@wordpress/scripts`, prázdne `src/Plugin.php`).
- [x] Scaffold block themy `farnost-theme` (`style.css`, minimálny `theme.json`, `templates/index.html`, `parts/header.html`, `parts/footer.html`).
- [x] `.gitignore` s exception pattern pre `farnost-plugin/` a `farnost-theme/`.
- [x] Plugin a téma aktivované, WP spustí „Hello world" s našou témou.
- [x] PHPCS (`phpcs.xml.dist`) + ESLint (`eslint.config.mjs`) + Prettier (`.prettierrc.json`) konfigurácia + root `package.json` so spoločnými dev závislosťami.
- [x] GitHub repo, README, LICENSE (GPL-2.0-or-later).

**Definícia hotovosti**: vývojár klonuje repo, spustí `composer install`, `npm install`, `ddev start` a vidí lokálny WP s aktívnou témou a aktívnym (prázdnym) pluginom. **Splnené.**

## Etapa 1 — Dátová vrstva

**Cieľ**: všetky CPT, post meta, term meta a settings existujú a sú spravovateľné cez WP REST. **Bez** custom admin UX, **bez** frontend prezentácie.

- [ ] Registrácia CPT `kostol`, `oznam`, `omsa_vynimka`, `umysel`, `upratovacia_skupina` (`src/PostTypes/`).
- [ ] Registrácia post meta pre každý CPT (`src/Meta/`), všetky `show_in_rest: true`.
- [ ] Registrácia post meta na natívne `post`: `farnost_event_when`, `farnost_event_where`.
- [ ] Registrácia term meta na `category`: `farnost_color`, `farnost_show_in_menu`.
- [ ] Registrácia settings `farnost_settings` ako WP option + REST endpoint `/wp-json/farnost/v1/settings`.
- [ ] Aktivačný hook: vytvorenie 3 default kategórií (`Udalosti`, `Zo života farnosti`, `Pozvánky`) s farbami a `show_in_menu = true`.
- [ ] Vlastná rola `farnost_asistent` s definovanými capabilities (vrátane custom `umysly` caps).
- [ ] Custom REST endpoint `/wp-json/farnost/v1/schedule?date=Y-m-d&kostol_id=N` (`src/Schedule/Resolver.php` + `src/Rest/ScheduleController.php`).
- [ ] Jednotkové testy pre `Resolver` (kombinácie rozpisu + výnimiek).
- [ ] WP-CLI seed skript v `scripts/seed.php` — 1 kostol s rozpisom, 3 oznamy, 2 výnimky, 5 úmyslov, 4 udalosti v rôznych kategóriách.

**Definícia hotovosti**: dáta sa dajú vytvoriť cez REST (`curl`/Postman) a `Resolver` vracia správny rozpis pre dátum vrátane aplikovaných výnimiek.

## Etapa 2 — Admin UX

**Cieľ**: farár si vie cez wp-admin spraviť všetko, čo bude potrebovať, **bez** nutnosti zdvihnúť ruku k vývojárovi.

- [ ] **React sidebar panely** (`editor/panels/`) pre každý CPT:
  - [ ] `RozpisOmsiPanel` na `kostol` — repeater UI s `@wordpress/components`.
  - [ ] `OznamPanel` na `oznam` — DateTimePicker × 2 + ToggleControl.
  - [ ] `VynimkaPanel` na `omsa_vynimka` — všetky polia, kostol ako ComboboxControl s REST lookup.
  - [ ] `UmyselPanel` na `umysel`.
  - [ ] `UdalostPanel` na bežné `post` — DateTimePicker + TextControl.
  - [ ] `UpratovacieSkupinyAdmin` — vlastná admin stránka so zoznamom skupín (drag-and-drop poradie, indikátor „aktuálne na rade", manuálny posun pointra).
- [ ] **Admin polia kategórie**: `category_add_form_fields` / `category_edit_form_fields` s color pickerom a checkboxom.
- [ ] **Settings stránka** `Farnosť → Nastavenia` postavená na `@wordpress/components`. Všetky sekcie z [`02-datovy-model.md`](02-datovy-model.md#per-farnosť-settings).
- [ ] **Setup wizard** — admin notice po aktivácii, 4-krokový wizard (identita, kontakt, prvý kostol, branding) + vytvorenie statických stránok s šablónami.
- [ ] **Vlastné top-level admin menu** „Farnosť" zoskupujúce: Sv. omše (→ Kostoly, Výnimky, Úmysly), Oznamy, Nastavenia, Návod.
- [ ] **Onboarding stránka** `Pomoc → Návod` s checklistom z [`04-obsah-a-roly.md`](04-obsah-a-roly.md#onboarding-nového-editora--checklist).
- [ ] i18n: všetky stringy cez `__()` / `_x()`, `farnost-plugin.pot` vygenerovaný.

**Definícia hotovosti**: druhá osoba (nie autor) vytvorí cez wp-admin: kostol s rozpisom, oznam s pripnutím, výnimku s režimom „náhrada", úmysel, udalosť s kategóriou a dátumom — bez dokumentácie, len intuitívne.

## Etapa 3 — Téma a frontend

**Cieľ**: verejný frontend zobrazuje obsah, je responzívny, dosahuje Lighthouse > 90 mobile, vyzerá ako farský web a nie ako prázdna WP šablóna.

- [ ] **`theme.json`** (base): farby, fonty, spacing scale, layout sizes — neutrálne defaulty.
- [ ] **5–10 style variations** v `styles/*.json` (klasická, mariánska, minimalistic, rustikálna, moderná svetlá, tradičná tmavá, …). Štruktúra layoutu je vo všetkých rovnaká — odlišné sú farby, typografia, spacing, per‑block štýly.
- [ ] **Náhľadové screenshoty** každej variant v `styles/screenshots/<slug>.webp` pre setup wizard.
- [ ] **Templates**:
  - [ ] `front-page.html` — hero, kombinovaný feed, najbližšie omše.
  - [ ] `singular.html`, `single-oznam.html`, `single-kostol.html`, `single.html` (pre posty/udalosti).
  - [ ] `archive-oznam.html`, `archive-kostol.html`, `archive-umysel.html`, `category.html`.
  - [ ] `404.html`.
- [ ] **Template parts**: `header.html` (s blokom `<Farské menu />`), `footer.html` (settings: kontakt, IBAN, sociálne).
- [ ] **Custom bloky v plugine** (`blocks/`):
  - [ ] `rozpis-omsi` — najbližších 7 dní pre vybraný kostol.
  - [ ] `aktualne-dianie` — kombinovaný feed oznamov + WP postov, sticky pripnuté, farebné badge kategórií.
  - [ ] `najnovsi-oznam` — najnovší publikovaný oznam.
  - [ ] `umysly-list` — najbližšie 4 týždne úmyslov.
  - [ ] `farnost-menu` — auto-generovaná navigácia.
  - [ ] `kostol-info` — kontakt + mapa pre stránku kostola.
  - [ ] `kontakt-farnost` — settings.kontakt do päty.
- [ ] **Block patterns** v téme: hero, feed sekcia, kontakt sekcia, modlitba pred omšou (voliteľné).
- [ ] **SEO**: Yoast nakonfigurovaný, sitemap, OG tags, schema.org JSON-LD pre `kostol` (Place + Church) a `oznam` (Article).
- [ ] **Prístupnosť**: kontrola WCAG 2.1 AA pre hlavné stránky (axe-core, manuálne s klávesnicou).
- [ ] **Performance**: Lighthouse run, optimalizácia (lazyload obrázkov, kritické CSS, minifikácia).

**Definícia hotovosti**: stránka prejde Lighthouse > 90 na mobile pre Domov, vyzerá ako produkcia, a farár vie cez Site Editor vymeniť hero patron na vlastný.

## Etapa 4 — Pilotná farnosť a polish

**Cieľ**: jedna reálna farnosť používa systém v produkcii, máme CI/CD a viditeľnosť do prevádzky.

- [ ] **Výber pilotnej farnosti** + dohoda o spolupráci (kto čo testuje).
- [ ] **Hosting** — server, doména, SSL, server cron.
- [ ] **CI/CD pipeline** (GitHub Actions): PHPCS, ESLint, testy na PR; build a release ZIP-ov na tag.
- [ ] **Distribučný model** — finalizovať voľbu (návrh: Plugin Update Checker + GitHub Releases ako update server).
- [ ] **Backup** — denné DB, týždenné uploads.
- [ ] **Uptime monitor** (UptimeRobot).
- [ ] **Tréning farára** — osobne / call, prejdeme onboarding návod.
- [ ] **Onboarding template** (`doc/onboarding-template.md`) — finálna verzia návodu pre farára.
- [ ] **Migrácia obsahu** zo starého webu (ak existuje) — alebo nový obsah od pilotnej farnosti.
- [ ] **2 týždne paralelnej prevádzky** so starým webom (ak je) pred DNS prepojením.
- [ ] **Bug-fix sprint** — týždeň reakcií na spätnú väzbu z pilotu.

**Definícia hotovosti**: produkčná URL pilotnej farnosti je live, farár publikuje obsah sám bez asistencie, žiadny P1 bug 7 dní v rade.

## Etapa 5 — Druhá a tretia farnosť (overenie multi-tenant)

**Cieľ**: overiť, že distribučný model reálne funguje pre viacero inštalácií, nájsť per-farnosť rozdiely.

- [ ] Setup druhej farnosti (klonovanie Bedrock skeletu, inštalácia z release ZIP-ov).
- [ ] Auto-update mechanizmus prvý raz overený v praxi (release v0.x.y → PUC notifikácia → klik update na všetkých inštaláciách).
- [ ] **MainWP master node** — centrálny dashboard nad všetkými inštaláciami.
- [ ] Spätná väzba z 2. a 3. farnosti → backlog úprav.
- [ ] Decision point: ostáva v3 stabilný, alebo začíname plánovať **v4 features** (udalosti CPT, kňazi CPT, online milodary…).

**Definícia hotovosti**: 3 farnosti používajú systém, update sa nasadil bez incidentu, máme zoznam reálnych požiadaviek pre v4.

## Etapa 6+ — Mimo v3 (možné rozšírenia)

Tieto rozšírenia **nie sú v scope v3** a treba ich najprv overiť rozhovorom s farármi a aktívnymi veriacimi:

- **Online prihlasovanie úmyslov** s e-mailovou notifikáciou farárovi a voliteľnou platbou.
- **Modul „Sviatosti"** — prihlasovanie na krst / sobáš / pohreb cez formulár, vlastný CPT namiesto statickej stránky.
- **CPT `udalost`** s opakovaním, RSVP, viacerými termínmi — namiesto WP postov, ak sa ukáže limit.
- **CPT `knaz`** — predstavenie duchovných farnosti, s biografiou a kontaktom.
- **Fotogaléria** ako CPT `galeria` (alebo lightbox blok cez WP médiá).
- **Newsletter** — týždenné oznamy e-mailom (Mailpoet integrácia).
- **Push notifikácie** cez PWA + Web Push API.
- **Multi-language** (slovenčina + maďarčina / poľština) cez WPML / Polylang.
- **Headless frontend** (Next.js na Verceli, WP ako REST/GraphQL API) — len ak naozaj bude potreba SPA UX.
- **Kalendárny pohľad udalostí** — mesačný / týždenný grid s udalosťami (popri zoznamovom feed-e). Klasika veľa farských webov, najmä pri farnostiach s veľa akciami. Pridáva sa v4, ak pilot ukáže, že zoznam vo feed-e nestačí.

> **Princíp**: každé rozšírenie pridávať na základe **konkrétneho problému veriaceho alebo farára**, nie preventívne „lebo je to cool". Zlým signálom by bolo, ak by sa scope zväčšil bez konkrétnej požiadavky z pilotu.

## Závislosti medzi etapami

```
Etapa 0 ─▶ Etapa 1 ─▶ Etapa 2 ─▶ Etapa 3 ─▶ Etapa 4 ─▶ Etapa 5
                                              │
                                              ▼
                                          Etapa 6+ (podľa pilotu)
```

Žiadne paralelné vetvy vo v3 — postupne, lineárne.

## Otvorené otázky pred štartom Etapy 0

Tieto rozhodnutia treba urobiť **pred** alebo **počas** Etapy 0 / Etapy 4:

- [ ] **Pilotná farnosť** — kandidát identifikovaný, čaká na potvrdenie dohody.
- [ ] **Hostingový model** — kandidát WebSupport (klasický slovenský hosting), final decision otvorená. **Nebráni implementácii** Etáp 0–3 — hosting reálne začne rozhodovať až pri pilotnom nasadení (Etapa 4).
- [x] **Licencia** — **GPL public** (repo na GitHub verejné). Monetizácia cez hosting / setup / support / custom dev — kód zostáva slobodný, peniaze sú za službu (model ako Yoast / Automattic / 90 % WP ekosystému).
- [x] **Brand pluginu/témy** — služba sa volá **„Farnosť Online"**, web na `farnost.online`. Plugin `farnost-plugin`, téma `farnost-theme`. Block namespace, PHP namespace, meta kľúče, settings option, REST namespace a rola asistenta sa všetky používajú prefix `farnost_*` / `Farnost\`.
- [x] **Tím** — solo vývojár pre v3 MVP. Možné rozšírenie tímu (kolega dev, dizajnér, content/community) zvažujeme až pri viacerých farnostiach (v4+).
- [x] **Komunikačný kanál** s pilotnou farnosťou — **kombinácia**: WhatsApp pre denné drobnosti („nefunguje mi toto", rýchle otázky), pravidelný hovor / stretnutie raz za 2 týždne pre väčšie témy (review, design diskusia, priority), e-mail len pre formality (zmluva, faktúra).
