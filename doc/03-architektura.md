# Architektúra

Tento dokument popisuje **technickú stránku** projektu — z čoho je postavený, ako sa to skladá v repe a ako sa to dostane k farnostiam.

## Tech stack

| Vrstva           | Voľba                                                           |
| ---------------- | --------------------------------------------------------------- |
| CMS              | **WordPress** (najnovšia stabilná verzia)                       |
| Štruktúra inštalácie | **Roots Bedrock** (`composer create-project roots/bedrock`)  |
| PHP              | 8.2+                                                            |
| DB               | MariaDB 10.6+ alebo MySQL 8                                     |
| Téma             | **Block theme (FSE)** — `theme.json`, templates, parts, patterns |
| Frontend bloky   | Custom Gutenberg bloky v plugine (`@wordpress/scripts` build)   |
| Polia / meta     | Natívne `register_post_meta`, `register_term_meta` — žiadne ACF Pro |
| Build assets     | `@wordpress/scripts` (Webpack) pre bloky, Vite voliteľne pre tému |
| Závislosti       | Composer (jadro WP, pluginy z `wpackagist.org`), npm pre JS     |
| Verziovanie env  | `.env` (Bedrock štandard), gitignored                            |
| Coding standards | PHPCS s WordPress Coding Standards, ESLint + Prettier            |

## Architektonický princíp: build once, deploy many

```
                    ┌──────────────────────────┐
                    │     farnost-v3 (repo)    │
                    │     Bedrock projekt      │
                    │  ┌────────────────────┐  │
                    │  │  farnost-plugin/    │  │  ─┐
                    │  └────────────────────┘  │   │  release
                    │  ┌────────────────────┐  │   │  artefakty
                    │  │  farnost-theme/     │  │  ─┘  (ZIP / Composer)
                    │  └────────────────────┘  │
                    └──────────────────────────┘
                                  │
                                  │  distribúcia
                                  ▼
              ┌───────────────────┴────────────────────┐
              ▼                   ▼                    ▼
   ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
   │ farnost-A.sk     │  │ farnost-B.sk     │  │ farnost-C.sk     │
   │ Bedrock + WP     │  │ Bedrock + WP     │  │ Bedrock + WP     │
   │ + farnost-plugin  │  │ + farnost-plugin  │  │ + farnost-plugin  │
   │ + farnost-theme   │  │ + farnost-theme   │  │ + farnost-theme   │
   └──────────────────┘  └──────────────────┘  └──────────────────┘
```

Repo `farnost-v3` je zároveň:
- **Vývojové prostredie** — funkčný Bedrock WP s pluginom a témou priamo editovateľnými.
- **Zdroj distribuovaných artefaktov** — pri release sa **iba** `farnost-plugin/` a `farnost-theme/` zabalia a nasadia do produkčných inštalácií farností.

## Štruktúra repa

```
farnost-v3/
├─ composer.json              # Bedrock + WP jadro + pluginy
├─ composer.lock
├─ package.json               # workspace root (volá build do plugin/theme)
├─ .env                       # gitignored (DB credentials, salty)
├─ .env.example               # šablóna pre tím
├─ .gitignore
├─ phpcs.xml                  # WP Coding Standards config
├─ doc/                       # táto dokumentácia
│
├─ config/                    # Bedrock konfigurácia
│  ├─ application.php
│  └─ environments/
│     ├─ development.php
│     ├─ staging.php
│     └─ production.php
│
└─ web/                       # docroot
   ├─ index.php
   ├─ wp-config.php
   ├─ wp/                     # WP jadro (composer-managed, gitignored)
   └─ app/                    # ekvivalent wp-content/
      ├─ mu-plugins/          # len Bedrock skripty (autoload)
      ├─ plugins/             # zoznam pluginov
      │  └─ farnost-plugin/    # ⭐ NÁŠ ZDROJ — versioned
      ├─ themes/
      │  └─ farnost-theme/     # ⭐ NÁŠ ZDROJ — versioned
      └─ uploads/              # gitignored
```

**Čo je v gite:**
- Bedrock skelet (`composer.json`, `config/`, `web/index.php`, `web/wp-config.php`).
- `web/app/plugins/farnost-plugin/` — celý plugin.
- `web/app/themes/farnost-theme/` — celá téma.

**Čo nie je v gite:**
- `web/wp/` — WP jadro (composer install).
- `web/app/plugins/<všetko-okrem-farnost-plugin>/` — externé pluginy (composer install).
- `web/app/uploads/`, `.env`.

`.gitignore` exception pattern:
```gitignore
web/app/plugins/*
!web/app/plugins/farnost-plugin/
web/app/themes/*
!web/app/themes/farnost-theme/
```

## Plugin: `farnost-plugin`

### Účel

Všetka **dátová a funkčná** logika farského webu. Plugin musí byť aktívny pre fungovanie webu; téma sa môže meniť.

### Štruktúra

```
farnost-plugin/
├─ farnost-plugin.php          # plugin header + PSR-4 autoloader + boot
├─ composer.json
├─ package.json                # @wordpress/scripts
│
├─ src/                        # PHP, PSR-4 (Farnost\Plugin\*)
│  ├─ Plugin.php               # main bootstrap, hooks pre všetky moduly
│  ├─ Activator.php            # rola, caps, default kategórie, timezone, refill
│  │
│  ├─ PostTypes/               # registrácia CPT
│  │  ├─ Kostol.php            # public=false, custom-fields, vlastný admin UI
│  │  ├─ Oznam.php             # public=true, capabilities lockdown, status preserve
│  │  ├─ OmsaVynimka.php       # show_in_menu=false, vzniká z kalendára
│  │  ├─ Umysel.php            # capability_type=[umysel,umysly]
│  │  └─ UpratovaciaSkupina.php
│  │
│  ├─ Meta/                    # post meta + term meta
│  │  ├─ PostMeta.php          # farnost_event_when/where, farnost_show_in_menu pre page
│  │  └─ CategoryMeta.php      # farnost_color, farnost_show_in_menu
│  │
│  ├─ Schedule/                # logika rozpisu omší
│  │  ├─ Resolver.php          # pure, mergeuje rozpis + výnimky, time-numeric sort
│  │  ├─ RozpisReader.php      # WP adaptér, per-date query
│  │  └─ WeeklyResolver.php    # batch query pre celý týždeň + viac kostolov
│  │
│  ├─ Settings/
│  │  └─ Settings.php          # farnost_settings option + schema + defaults
│  │
│  ├─ Rest/                    # custom REST endpointy /wp-json/farnost/v1/
│  │  ├─ SettingsController.php       # GET /settings (verejné)
│  │  ├─ ScheduleController.php       # GET /schedule?date=&kostol_id=
│  │  ├─ BannerController.php         # GET /banner
│  │  ├─ SnapshotController.php       # POST /snapshot/build
│  │  └─ RotationPointerController.php # POST /rotation-pointer
│  │
│  ├─ Admin/                   # admin UX rozšírenia
│  │  ├─ Menu.php              # top-level „Farnosť" + submenu
│  │  ├─ SettingsPage.php      # Farnosť → Nastavenia (PHP form)
│  │  ├─ WizardPage.php        # plnoobrazovkový setup wizard
│  │  ├─ KostolyPage.php       # custom React obrazovka (build/kostoly.js)
│  │  ├─ UpratovacieSkupinyPage.php   # custom React (build/upratovacie.js)
│  │  ├─ MimoriadnyOznamPage.php      # rich text + expiry form
│  │  ├─ NavodPage.php         # statická knowledge base + checklist
│  │  ├─ CategoryAdmin.php     # color picker + show-in-menu na taxonomy
│  │  ├─ EditorAssets.php      # enqueue per-CPT editor entries + site-blocks
│  │  ├─ BlockCategory.php     # „Farnosť" kategória v block insert
│  │  ├─ PostRelabel.php       # natívny `post` → „Udalosti" labels
│  │  ├─ CommentsHide.php      # odstavenie komentárov
│  │  └─ SetupNotice.php       # admin notice ak wizard nedokončený
│  │
│  ├─ Oznam/                   # business logika oznamov
│  │  ├─ BufferManager.php     # auto-create budúcich oznamov + cron
│  │  ├─ AutoTemplate.php      # prefil obsahu pri novom ozname (fallback)
│  │  ├─ SnapshotBuilder.php   # zostavenie dni pre rozpis-snapshot block
│  │  └─ Upratovanie.php       # rotácia upratovacích skupín
│  │
│  ├─ MimoriadnyOznam/
│  │  └─ Banner.php            # data layer pre option-stored banner
│  │
│  └─ Blocks/                  # dynamic blocks (server-side render)
│     ├─ Banner.php            # farnost/banner
│     ├─ Feed.php              # farnost/feed
│     ├─ MainNav.php           # farnost/main-nav (auto z Pages)
│     ├─ MassWidget.php        # farnost/mass-widget
│     ├─ ContactWidget.php     # farnost/contact-widget
│     ├─ QuoteWidget.php       # farnost/quote-widget
│     ├─ ScheduleTable.php     # farnost/schedule-table
│     ├─ ArchiveList.php       # farnost/archive-list
│     ├─ SiteBrand.php         # farnost/site-brand (logo + nazov)
│     ├─ SiteHeader.php        # farnost/site-header (composition)
│     ├─ SiteFooter.php        # farnost/site-footer (3-col grid)
│     └─ RozpisSnapshot.php    # farnost/rozpis-snapshot (oznam content)
│
├─ editor/                     # JSX entry points
│  ├─ panel-oznam/             # per-CPT editor sidebar panely
│  ├─ panel-vynimka/
│  ├─ panel-umysel/
│  ├─ panel-udalost/
│  ├─ block-rozpis-snapshot/   # editor pre dynamic rozpis-snapshot block
│  ├─ calendar/                # custom admin obrazovka (Kalendár omší)
│  ├─ kostoly/                 # custom admin (Kostoly)
│  ├─ upratovacie/             # custom admin (Upratovacie skupiny)
│  ├─ wizard/                  # setup wizard
│  └─ site-blocks/             # ServerSideRender previews pre site bloky
│
├─ build/                      # gitignored: výstup wp-scripts (committed v3)
└─ languages/
   └─ farnost-plugin.pot
```

### Plugin header (vzor)

```php
/**
 * Plugin Name: Farnosť Online
 * Plugin URI:  https://github.com/likavan/farnost-v3
 * Description: CPT, bloky a logika pre farské weby.
 * Version:     0.1.0
 * Requires PHP: 8.2
 * Requires at least: 6.6
 * Author:      farnost.online
 * Author URI:  https://farnost.online
 * License:     GPL-2.0-or-later
 * Text Domain: farnost-plugin
 */
```

### Bloky — build pipeline

- `npm run build` v `farnost-plugin/` spustí `wp-scripts build` na všetky priečinky v `blocks/` aj `editor/`.
- Výstup ide do `build/`, ktorý nie je v gite.
- Pri release ich treba vybuildovať pred zazipovaním (CI step).
- Vývojom: `npm run start` pre live reload v block editore.

## Téma: `farnost-theme`

### Účel

Vzhľad a layout. **Žiadna business logika.** Téma môže byť plne nahradená inou — dáta v plugine zostanú.

### Štruktúra (block theme — aktuálny stav po Etape 3)

```
farnost-theme/
├─ style.css                  # theme header + frontend CSS (paper noise, brand,
│                             # search, nav, alert, post cards, sidebar, footer,
│                             # responsive 980/760 breakpointy)
├─ theme.json                 # v3 schema: krémová+burgundská paleta, Cormorant
│                             # Garamond + Source Serif 4, font-sizes, spacing
├─ functions.php              # enqueue Google Fonts + style + chrome.js,
│                             # register_block_pattern_category
│
├─ assets/
│  └─ chrome.js               # search expand/collapse + banner dismiss +
│                             # mobile hamburger toggle
│
├─ templates/                 # FSE templates
│  ├─ index.html              # home (header + feed + sidebar + footer)
│  ├─ single.html             # generický single (post / oznam / ...)
│  ├─ page.html               # farnost-page wrapper pre patterns
│  ├─ search.html             # wp:query výsledky
│  └─ 404.html                # page-header + CTA
│
├─ parts/                     # template parts
│  ├─ header.html             # <!-- wp:farnost/site-header /-->
│  ├─ footer.html             # <!-- wp:farnost/site-footer /-->
│  └─ sidebar.html            # composition 3 widgetov
│
├─ patterns/                  # patterns pre sub-pages
│  ├─ page-o-farnosti.php
│  ├─ page-bohosluzby.php     # + wp:farnost/schedule-table
│  ├─ page-sviatosti.php
│  ├─ page-kontakt.php        # 2-col grid + form
│  └─ page-oznamy-archiv.php  # + wp:farnost/archive-list
│
└─ (styles/ + screenshots — naplánované pre v3.1)
```

Téma je **tenká** — všetka business logika (rendering feedu, mass widget,
contact widget, atď.) žije v plugine ako dynamic blocks. Template parts
header/footer obsahujú jediný riadok s blokom; templates sú minimálne layout
wrappre. Tým je téma vymeniteľná — admin si môže nahodiť inú a celý dynamic
obsah (z pluginu) ostane funkčný.

### Dizajnová stratégia: style variations only

**Štruktúra všetkých dizajnov je rovnaká** — jeden `front-page.html`, jeden `header.html`, jeden `footer.html`, jeden set custom blokov. Diametrálne odlišný **vzhľad** každého dizajnu vzniká **iba** cez `theme.json` style variation v `styles/*.json`.

**Čo style variation mení:**
- Farebná paleta (primary, secondary, accent, background, text)
- Typografia (font family, sizes, weight, line-height pre nadpisy aj body)
- Spacing scale (kompaktný vs vzdušný)
- Per‑block štýly (button radius, card shadow, headline weight)
- Element štýly (links, h1–h6, captions)

**Čo style variation NEmení:**
- Layout templates (`templates/*.html`) — rovnaké pre všetky varianty
- Template parts (`parts/*.html`) — jeden header, jeden footer
- Štruktúru sekcií front-page
- Počet stĺpcov, pozíciu menu, či je sidebar

**Cieľový počet:** 5–10 style variations dodaných v `farnost-theme/styles/`. Každá má vlastný náhľadový screenshot v `styles/screenshots/<slug>.webp` pre použitie v setup wizarde.

**Žiadne platené závislosti** — variants sú čistý FSE, žiadny GenerateBlocks Pro, žiadne site library pluginy.

### `theme.json` — kľúčové hodnoty (default)

- **Palety** — primárna farba ako referencia (`var:preset|color|primary`), per‑farnosť override prichádza zo settings (`farnost_settings.branding.primary_color`) cez inline `<style>` v `wp_head` — prebíja zvolenú style variation.
- **Typografia** — system stack default v base téme; jednotlivé style variations definujú vlastné self-host webfonty (napr. Inter, Lora, Manrope, Crimson, Cormorant).
- **Spacing scale** — 8/16/24/32/48/64; jednotlivé variants ho môžu škálovať.
- **Layout** — `contentSize` a `wideSize` pre center column, rovnaké pre všetky varianty.

## Bedrock konfigurácia

Per‑environment hodnoty v `config/environments/`:

| Konštanta              | development | staging | production |
| ---------------------- | ----------- | ------- | ---------- |
| `WP_DEBUG`             | `true`      | `true`  | `false`    |
| `WP_DEBUG_LOG`         | `true`      | `true`  | `true`     |
| `WP_DEBUG_DISPLAY`     | `true`      | `false` | `false`    |
| `DISALLOW_FILE_EDIT`   | `false`     | `true`  | `true`     |
| `DISALLOW_FILE_MODS`   | `false`     | `true`  | `true`     |
| `WP_CACHE`             | `false`     | `true`  | `true`     |
| `AUTOMATIC_UPDATER_DISABLED` | `true` | `true` | `true`     |

`.env` (gitignored) obsahuje:

```
DB_NAME=
DB_USER=
DB_PASSWORD=
DB_HOST=
WP_ENV=development
WP_HOME=http://farnost-v3.test
WP_SITEURL=${WP_HOME}/wp
AUTH_KEY=
AUTH_SALT=
SECURE_AUTH_KEY=
SECURE_AUTH_SALT=
LOGGED_IN_KEY=
LOGGED_IN_SALT=
NONCE_KEY=
NONCE_SALT=
```

## Externé pluginy (Composer)

Pluginy, ktoré sa **nesúčasťou nášho `farnost-plugin`**, ale potrebujeme ich:

| Plugin                          | Účel                                                       |
| ------------------------------- | ---------------------------------------------------------- |
| `wordpress-seo` (Yoast)         | SEO, OG metadata, sitemap, schema.org                       |
| `wp-mail-smtp` alebo `fluent-smtp` | Spoľahlivé odosielanie e-mailov                          |
| `limit-login-attempts-reloaded` | Bezpečnosť wp-admin                                         |
| `query-monitor` (dev only)      | Debugging                                                   |
| `wp-crontrol` (dev only)        | Kontrola cronu                                              |

Pridávanie cez `composer.json`:

```json
"require": {
  "roots/bedrock": "^1.24",
  "wpackagist-plugin/wordpress-seo": "^23",
  "wpackagist-plugin/limit-login-attempts-reloaded": "^2",
  "wpackagist-plugin/wp-mail-smtp": "^4"
}
```

## Lokálny dev environment

Odporúčaný stack: **DDEV** (alebo Lando / wp-env). Konfigurácia v repe (`.ddev/`), tím sa rozbehne príkazmi:

```bash
composer create-project roots/bedrock .   # už urobené, len pre referenciu
composer install
cd web/app/plugins/farnost-plugin && npm install && npm run build
cd ../../themes/farnost-theme && npm install && npm run build
ddev start
ddev exec wp core install --url=https://farnost-v3.ddev.site --title="Farnosť (dev)" --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
ddev exec wp plugin activate farnost-plugin
ddev exec wp theme activate farnost-theme
ddev exec wp eval-file scripts/seed.php   # voliteľné: testovacie dáta
```

## Distribučný model (otvorené — zatiaľ návrh)

> **Toto rozhodnutie ešte nie je urobené.** Tu sú možnosti, najpravdepodobnejšie odporúčanie je vyznačené.

| Možnosť                                    | Plusy                                             | Mínusy                                              |
| ------------------------------------------ | ------------------------------------------------- | --------------------------------------------------- |
| **Manuálny ZIP**                           | Jednoduché, žiadna infra                          | Neškálovateľné, závisí od človeka                   |
| **Plugin Update Checker** ⭐ návrh         | Auto‑updaty z nášho Git/servera, štandardná UX v admine | Treba vlastný update server (S3/GitHub releases)    |
| **Composer satis / private Packagist**     | Plne automatizované cez `composer update`         | Vyžaduje technicky zdatného operátora               |
| **MainWP / ManageWP**                      | Centrálny dashboard nad všetkými farnosťami       | Mesačná licencia / vlastný master node              |
| **Bedrock + Composer per farnosť**         | Konzistentné s repom                              | Vyžaduje CLI prístup na hosting                     |

**Odporúčaná kombinácia pre v3**: Plugin Update Checker (auto-updaty z GitHub releases) + MainWP pre prehľad a centrálne monitorovanie zdravia inštalácií. Konkrétne rozhodnutie sa robí v rámci [`05-roadmap.md`](05-roadmap.md) etapy.

## Hosting (otvorené — návrh)

Bedrock vie bežať na:

| Typ                            | Vhodnosť                                                 |
| ------------------------------ | -------------------------------------------------------- |
| Klasický LAMP hosting          | OK ak `web/` ako docroot je možný (väčšina SK hosterov áno) |
| VPS s PHP-FPM + Nginx          | Najflexibilnejšie, najlacnejšie pri viacerých farnostiach |
| Spravovaný WP hosting          | Niektorí (WPEngine, Kinsta) Bedrock nepodporujú dobre    |
| Kontajner (Docker)             | Najlepšie pre staging/prod paritu, vyžaduje DevOps prácu |

Pravdepodobné odporúčanie pre Digitalku: vlastné VPS, jedna inštalácia per farnosť (sub-doména alebo vlastná doména farnosti), Nginx + PHP-FPM + MariaDB + Redis.

## CI/CD (návrh)

GitHub Actions workflow:

1. **Pull request**: PHPCS, ESLint, jednotkové testy, `npm run build` dry-run.
2. **Merge do `main`**: build plugin + theme → ZIP artefakty → upload do GitHub Releases.
3. **Tag `v*.*.*`**: stable release, notifikácia Plugin Update Checkerom všetkým inštaláciám.

## Bezpečnosť — minimálny štandard

- `.env` mimo gitu, salty náhodné per inštalácia.
- `DISALLOW_FILE_EDIT = true` v prod (žiadna editácia PHP cez admin).
- `WP_DEBUG = false` v prod, logy len do súboru.
- Pravidelné updaty cez Composer + Renovate/Dependabot.
- Force HTTPS, HSTS, bezpečné cookies (`SECURE_AUTH_COOKIE`).
- Limit Login Attempts + voliteľne 2FA pre admin.
- Žiadne `wp-admin` priamo zo zahraničných IP (voliteľne, ak zákazník chce).

## Performance — minimálny štandard

- Page cache na hosting úrovni (Nginx fastcgi_cache alebo plugin WP Super Cache / WP Rocket).
- Object cache Redis (ak hosting umožňuje).
- Optimalizované obrázky (WebP, lazyload — WP 5.5+ natívne).
- HTTP/2, gzip/brotli, CDN pre statiku (Cloudflare free tier postačí).
- Lighthouse target: **≥ 90** mobile pre `/` (Domov).

## Monitoring (návrh)

- **Uptime monitor** — UptimeRobot per inštalácia.
- **Error logs** — denne agregované, týždenná kontrola.
- **Backups** — denné DB + týždenné `web/app/uploads/`.
- **Updates dashboard** — MainWP master, prehľad WP/PHP/plugin verzií všetkých farností.

## Otvorené otázky

- **Pilotná farnosť** — na ktorej budeme vyvíjať a testovať pred širším spustením?
- **Hostingový model** — Digitalka hostí všetky farnosti centrálne, alebo ich pošleme na ich preferovaný hosting?
- **Distribučný kanál** — finalizovať voľbu (návrh: PUC + MainWP).
- **Licencia** — GPL (open source na GitHube) alebo interný repo Digitalky? Odporúčanie: GPL-2.0-or-later, lebo to vyžaduje samotný WordPress; viditeľnosť repa môže byť interná.
- **Bundling externých pluginov** — distribuovať `composer.json` ako referenciu, alebo prevádzkovať vlastný Composer satis s pinovaním verzií?
- **Sage 11 namiesto block themy** — vrátiť sa k diskusii len ak narazíme na FSE limity pri konkrétnej feature.
