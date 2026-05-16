# Farský web — projektový plán

WordPress riešenie pre slovenské farnosti. Každá farnosť má vlastnú samostatnú WP inštaláciu, ale zdieľajú spoločnú šablónu a plugin, ktoré vyvíjame raz a distribuujeme.

---

## Architektúra

**Princíp:** *Build once, deploy many.*

- **Samostatná WP inštalácia per farnosť** (nie Multisite)
- **Spoločná šablóna (theme)** — vzhľad, layouty, block patterns
- **Spoločný plugin** — všetka funkcionalita, custom post types, dáta
- **Pravidlo oddelenia:** dáta a logika do pluginu, dizajn do šablóny. Keď sa zmení téma, oznamy a rozpis omší zostávajú.

```
┌─────────────────────────────────────┐
│  farnost-bratislava.sk  (WP inst.)  │
│  farnost-martin.sk      (WP inst.)  │
│  farnost-zilina.sk      (WP inst.)  │
└─────────────────────────────────────┘
              ▲          ▲
              │          │
       ┌──────┴──────────┴──────┐
       │  spoločná téma + plugin │
       │   (vyvíjané raz)        │
       └─────────────────────────┘
```

---

## Tech stack (návrh, otvorené k diskusii)

- **WordPress** najnovší stable
- **PHP** 8.2+
- **Block theme** (FSE, `theme.json`) — moderný prístup, farár má slušnú slobodu v editore
- **Composer** pre dependencies
- **Node + npm** pre build (Sass, JS bundling)
- **`@wordpress/scripts`** pre block development
- **PHPCS** s WordPress Coding Standards
- **Git** + GitHub/GitLab pre verziovanie

---

## Štruktúra projektu

Navrhujem **monorepo** — téma aj plugin v jednom Git repe, ľahšia synchronizácia verzií.

```
farsky-web/
├── theme/
│   └── farsky-theme/
│       ├── style.css
│       ├── theme.json
│       ├── templates/
│       ├── parts/
│       └── patterns/
├── plugin/
│   └── farsky-plugin/
│       ├── farsky-plugin.php
│       ├── includes/
│       │   ├── post-types/
│       │   ├── blocks/
│       │   └── admin/
│       └── assets/
├── docs/
├── .github/workflows/
└── README.md
```

---

## Funkčné požiadavky (MVP)

### Custom Post Types
- **Sv. omše** — pravidelný rozpis + sviatočné výnimky
- **Oznamy** — týždenné farské oznamy, archív
- **Udalosti** — kalendár (púte, akcie, prípravy)
- **Sviatosti** — statické info stránky (krst, sobáš, prvé sv. prijímanie, pohreb…)
- **Kňazi** — duchovní pôsobiaci vo farnosti
- **Kostoly / filiálky** — ak farnosť má viacero kostolov

### Bloky (Gutenberg)
- Blok rozpisu omší na dnešok / týždeň
- Blok najbližších udalostí
- Blok aktuálnych oznamov (najnovší PDF / text)
- Blok kontaktu na farský úrad
- Blok úradných hodín

### Settings stránka pluginu
- Názov farnosti, dekanát, diecéza
- Adresa, GPS súradnice, kontakty
- Úradné hodiny
- Sociálne siete
- 2% / IBAN na dary

---

## Distribúcia a updaty (kľúčové rozhodnutie!)

Treba sa rozhodnúť **ako budú farnosti dostávať updaty** témy a pluginu:

1. **Manuálne nahranie ZIP-u** — najjednoduchšie, ale neškálovateľné
2. **Vlastný update server** s `plugin-update-checker` (YahnisElsts) — automatické updaty z nášho Git/servera
3. **MainWP / ManageWP** — centrálna správa všetkých inštalácií z jedného dashboardu
4. **Composer + wp-cli** — pre technicky zdatnejších

> **Odporúčam** kombinovať: `plugin-update-checker` pre auto-updaty + MainWP pre prehľad a monitoring.

---

## Onboarding novej farnosti (cieľová UX)

1. Klon čistej WP inštalácie (alebo cez Digitalka hosting wizard)
2. Aktivácia témy a pluginu
3. **Setup wizard** v admine — krok za krokom:
   - Údaje farnosti
   - Logo a farby
   - Úvodný rozpis omší
   - Kontakty
4. Hotovo, web je live

---

## Otvorené otázky (pred štartom kódu)

- [ ] Block theme (FSE) **alebo** klasická téma s ACF? *(odporúčam FSE)*
- [ ] Open-source na GitHube alebo interný projekt Digitalky?
- [ ] Aký bude distribučný model updatov? *(viď vyššie)*
- [ ] Bude pilotná / referenčná farnosť, na ktorej to vyvíjame?
- [ ] Viacjazyčnosť? *(99% nie, ale dobré sa spýtať)*
- [ ] Integrácia s niečím externým? (Google Calendar pre udalosti, farská kartotéka, …)
- [ ] Licencia (GPL ak verejné)

---

## Prvé kroky v Claude Code

1. Inicializácia repa, `.gitignore`, README, licencia
2. Lokálne dev prostredie — Local WP / wp-env / Docker
3. Scaffold pluginu `farsky-plugin` (hlavný súbor, autoloader, štruktúra zložiek)
4. Scaffold block themy `farsky-theme` (`style.css`, `theme.json`, základné templates)
5. Prvý CPT: **Sv. omše** + admin UI + jednoduchý block na zobrazenie
6. Druhý CPT: **Oznamy**
7. Iterácia podľa MVP listu vyššie

---

## Pracovný workflow

- Krátke iteratívne kroky, commit po commite
- Najprv dátový model (CPT, fields), potom admin UX, nakoniec frontend bloky
- Každú novú vec rovno otestovať na lokálnej inštancii
- Slovenská lokalizácia od začiatku (`.pot` súbor pre plugin aj tému)
