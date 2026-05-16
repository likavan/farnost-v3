# farnost-v3

WordPress riešenie pre slovenské farnosti — služba **Farnosť Online** (`farnost.online`).

Repo obsahuje **Bedrock-based WordPress projekt**, v ktorom sa vyvíja a testuje plugin `farnost-plugin` a téma `farnost-theme`. Pri release sa **iba plugin a téma** distribuujú do produkčných inštalácií jednotlivých farností (princíp *build once, deploy many*).

## Stav

**Etapa 0 (Bootstrap) hotová.** Bedrock + DDEV beží lokálne, plugin `farnost-plugin` a téma `farnost-theme` sú aktivované a frontend zobrazuje „Hello world".

## Dokumentácia

Začni v [`doc/00-prehlad.md`](doc/00-prehlad.md). Ostatné dokumenty:

- [`doc/01-funkcie.md`](doc/01-funkcie.md) — funkčné požiadavky a use casy.
- [`doc/02-datovy-model.md`](doc/02-datovy-model.md) — CPT, post meta, term meta, settings.
- [`doc/03-architektura.md`](doc/03-architektura.md) — tech stack, štruktúra repa, distribučný model.
- [`doc/04-obsah-a-roly.md`](doc/04-obsah-a-roly.md) — používateľské roly a workflow.
- [`doc/05-roadmap.md`](doc/05-roadmap.md) — etapy implementácie a otvorené otázky.
- [`doc/06-struktura-stranky.md`](doc/06-struktura-stranky.md) — frontend layout a stránky.
- [`doc/07-admin-ux.md`](doc/07-admin-ux.md) — admin UX (oznam workflow, kalendár, setup wizard).

## Lokálne rozbehnutie

### Prerekvizity

- **PHP 8.2+** lokálne (potrebuje Composer)
- **Composer** 2.x
- **Node.js 20+** + npm
- **Docker Desktop** (alebo iná Docker runtime)
- **DDEV** 1.25+ — `brew install ddev/ddev/ddev` (na Apple Silicon používať ARM64 Homebrew z `/opt/homebrew`)

### Inštalácia

```bash
git clone git@github.com:likavan/farnost-v3.git
cd farnost-v3

# PHP závislosti + WP core do web/wp/
composer install

# JS devtoolchain (lint, format)
npm install

# DDEV kontajnery (web + db)
ddev start

# WordPress samotný (jednorazovo)
ddev wp --path=web/wp core install \
  --url='http://farnost-v3.ddev.site:8080' \
  --title='Farnost Online - Dev' \
  --admin_user='admin' \
  --admin_password='admin' \
  --admin_email='dev@farnost.online' \
  --skip-email

# Slovenčina ako jazyk WP
ddev wp --path=web/wp language core install sk_SK --activate

# Aktivuj náš plugin a tému
ddev wp --path=web/wp plugin activate farnost-plugin
ddev wp --path=web/wp theme activate farnost-theme
```

Frontend: <http://farnost-v3.ddev.site:8080>  
Admin: <http://farnost-v3.ddev.site:8080/wp/wp-admin> (login: `admin` / `admin`)

### Užitočné príkazy

```bash
ddev start                   # spusti kontajnery
ddev stop                    # zastav
ddev describe                # všetky URL a porty
ddev wp --path=web/wp <cmd>  # WP-CLI
ddev ssh                     # shell vnútri web kontajnera
composer run-script <name>   # PHP skripty
npm run lint:js              # ESLint
npm run format               # Prettier
```

## Licencia

[GPL-2.0-or-later](LICENSE) — kód je slobodný. Služby (hosting, setup, support) okolo neho poskytuje [Digitalka](https://digitalka.sk).
