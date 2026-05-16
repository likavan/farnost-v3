# farnost-v3

WordPress riešenie pre slovenské farnosti — služba **Farnosť Online** (`farnost.online`).

Repo obsahuje **Bedrock-based WordPress projekt**, v ktorom sa vyvíja a testuje plugin `farnost-plugin` a téma `farnost-theme`. Pri release sa **iba plugin a téma** distribuujú do produkčných inštalácií jednotlivých farností (princíp *build once, deploy many*).

## Stav

Pred-bootstrap. Aktuálne v repe je len projektová dokumentácia v [`doc/`](doc/). Etapa 0 (Bedrock scaffold) je rozpracovaná.

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

> **TODO**: doplníme po dokončení Etapy 0 (Bedrock + DDEV scaffold).

Plánovaný flow:

```bash
git clone git@github.com:likavan/farnost-v3.git
cd farnost-v3
composer install
npm install
ddev start
```

## Licencia

[GPL-2.0-or-later](LICENSE) — kód je slobodný. Služby (hosting, setup, support) okolo neho poskytuje [Digitalka](https://digitalka.sk).
