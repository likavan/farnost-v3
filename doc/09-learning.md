# Learning — review codebase

Od 2026-05-19 prechádzame kód s cieľom porozumieť **prečo** je to tak naprogramované,
nie len **čo** robí. User si vytvára mental model; Claude vysvetľuje rozhodnutia,
trade-offs a alternatívy ktoré boli zvážené.

## Pravidlá review mode

- **Žiadna nová implementácia bez explicitného súhlasu.** Default mode = diskusia.
- **Pýtame sa "prečo"**, nie "čo". Čo kód robí, vidno v editore — chýba kontext.
- **Stručne ale nie povrchne.** Pri netrivalnom trade-off-e spomenúť alternatívy.
- **Úprimnosť pred ex-post racionalizáciou.** Ak bola voľba suboptimal, povedať to.
- **Linky `file:line`** aby user mohol skočiť k zdroju.

## Oblasti na prejdenie

Žiadne poradie nie je správne — postupujeme podľa toho čo nesedí. Hodne otázok
spadne do viacerých oblastí naraz — pri každom topiku poznačíme aj cross-link.

### 1. Top-down architektúra
- `Plugin.php` boot sequence — čo sa registruje na `init`, čo na `admin_init`,
  čo na `rest_api_init` a prečo to poradie záleží
- PSR-4 autoload + namespace layout (`Farnost\Plugin\…`)
- `Activator` — beží len pri zapnutí, čo robí (timezone, role, default kategórie,
  rewrite flush + version-bumping mechanizmus)
- Singleton pattern v `Plugin::instance()` — prečo

### 2. Životný cyklus oznamu (najkomplexnejšia časť)
- `Oznam` CPT registrácia + capability lockdown (`do_not_allow` + map_meta_cap):
  prečo nemôže farár ručne vytvárať / publikovať / mazať
- `BufferManager` cron flow — týždenný buffer 3 oznamov vopred, `wp_publish_post`
  obíde cap check, takže auto-publish funguje aj pri lockdown
- `Oznam::preserveStatusOnUpdate` filter — prečo edit oznamu nesmie prepnúť
  status na `pending` (Submit for Review label override)
- `AutoTemplate` — pre-fill rozpis-snapshot pri vytvorení oznamu
- WP-CLI fallback `wp post delete <id>` keď admin potrebuje zasiahnuť

### 3. Block API (Gutenberg)
- Dynamic blocks (`render_callback`) vs static (save returns markup) — prečo
  všetky naše bloky sú dynamic
- Editor JS Edit komponent + ServerSideRender / vlastný preview — kedy ktoré
- `block-rozpis-snapshot` — najkomplexnejší blok, normalizeDay legacy/new shape,
  inline edit, REST `/farnost/v1/snapshot/build`
- `farnost/gallery` — MediaPlaceholder, shared lightbox JS, force-lightbox na
  core/image cez `render_block` filter
- Build pipeline: `@wordpress/scripts` webpack entries → `build/*.asset.php`
  closure dependencies → PHP enqueue
- `BlockRestrictions` — hide site blocks z post editora cez `allowed_block_types_all`

### 4. Schedule / Resolver (pure logic)
- `Resolver::resolve` — merge rozpisu + výnimiek pre konkrétny dátum
- `Resolver::timeKey` — minúty od polnoci pre numerický sort (prečo nie strcmp)
- `WeeklyResolver::forWeek` — N+1 fix: 2 queries pre celý týždeň × všetky kostoly,
  in-memory dispatch, plus napárovanie úmyslov z CPT `umysel`
- `SnapshotBuilder` — buildér dát pre rozpis-snapshot block, kostoly[] shape

### 5. Settings & admin UX
- `Settings` schema + register_setting — prečo PHP-side schema namiesto čisto
  options API
- `SettingsPage` sanitize logic — preserve `setup`, `moduly`, `upratovanie`,
  `citaty` pri partial save
- `Menu::reorderSubmenu` — prečo ručná zmena poradia
- `WizardPage` setup flow + `setup.completed` flag
- `EditorAssets::hideTemplatePartsInPostEditor` — filter `block_editor_settings_all`

### 6. Theme (block theme)
- `theme.json` v3 — design tokens (paleta, fonts, sizes, spacing)
- Templates a parts hierarchia (index → single-oznam → page atď.)
- `add_editor_style('style.css')` — iframed canvas dostane frontend CSS
- Custom CSS organizácia (paleta krémová + burgundská, Cormorant + Source Serif)
- Sidebar sticky + inside-scroll + mobile drawer JS portal pattern
- Shared lightbox JS (gallery + core/image)

## Otázky ktoré padli a kam ich uložím

(Doplňovať pri review. Záznamy slúžia ako index aby sa nemusíme vracať k tým
istým témam.)

| Dátum | Otázka | Kam vedie / odpoveď |
|---|---|---|
| | | |

## Súvisiace dokumenty

- `00-prehlad.md` — high-level prehľad projektu
- `02-datovy-model.md` — CPT-čka, meta, options
- `03-architektura.md` — vrstvy + interakcie
- `08-feedback.md` — open backlog
