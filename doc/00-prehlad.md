# farnost-v3 — Prehľad projektu

## Čo je farnost-v3

**WordPress riešenie pre slovenské farnosti** postavené na princípe „build once, deploy many":

- **Jeden plugin** (`farnost-plugin`) — všetka farská funkcionalita (CPT, logika rozpisu omší, settings, Gutenberg bloky).
- **Jedna téma** (`farnost-theme`) — block theme (FSE) so vzhľadom.
- **N samostatných WordPress inštalácií** — každá farnosť má vlastnú inštaláciu, ktorá konzumuje plugin + tému z tohto repa.

Repo `farnost-v3` je **Bedrock-based WordPress projekt**, v ktorom sa plugin a téma vyvíjajú a testujú. Pri release sa **iba plugin a téma** distribuujú do produkčných inštalácií jednotlivých farností.

## Cieľová skupina

### Koncoví používatelia (návštevníci webu)

- **Veriaci farnosti** — chcú vedieť, kedy sú sv. omše, čo sa deje v týždni, na aký úmysel bude slúžená omša.
- **Návštevníci a okoloidúci** — hľadajú kontakt, čas najbližšej omše, info pred sviatkom.

### Správcovia obsahu

- **Farár (Editor)** — primárny prispievateľ obsahu. Píše oznamy, spravuje rozpis omší a výnimky.
- **Asistent (vlastná rola)** — kostolník / sekretár, pridáva úmysly a vlastné oznamy.

### Operátori

- **Administrátor inštalácie** — technicky zdatná osoba (alebo Digitalka), inštaluje plugin a tému, robí updaty, rieši zálohy.

## Rozsah verzie 3 (MVP)

V scope:

1. **Oznamy** — týždenné farské oznamy s archívom (vlastný CPT).
2. **Sv. omše** — pravidelný týždenný rozpis + výnimky pre sviatky a mimoriadne udalosti.
3. **Úmysly sv. omší** — verejný prehľad úmyslov viazaných na konkrétny dátum/čas/kostol.
4. **Kostoly farnosti** — evidencia jedného alebo viacerých kostolov a kaplniek.
5. **Udalosti a život farnosti** — bežné WP posty triedené kategóriami (`Udalosti`, `Zo života farnosti`, `Pozvánky`); na úvodnej stránke spolu s oznamami vo feede.
6. **Automatické menu** — navigácia sa generuje z pevných položiek a publikovaných kategórií, farár ju needitúje ručne.
7. **Statické stránky** (Kontakt, O farnosti, Sviatosti) — riešené ako bežné WP stránky, bez špeciálnej logiky v plugine.
8. **Per‑farnosť konfigurácia** — názov, adresa, IBAN, sociálne siete cez settings stránku v plugine.

Mimo scope vo v3 (možné v ďalších verziách):

- Online prihlasovanie nového úmyslu cez web (vrátane platby).
- Kňazi farnosti ako vlastný CPT.
- Sviatosti ako vlastný CPT (vo v3 sú to bežné WP stránky).
- Fotogaléria, newsletter, push notifikácie.
- Multi-language (slovenčina je jediný jazyk vo v3).

## Princípy návrhu

- **Obsah na prvom mieste** — web musí byť čitateľný aj na pomalom mobile v kostole.
- **Aktuálnosť je kritická** — zastaraný rozpis omší je horší než žiadny. Editácia obsahu musí byť triviálna.
- **Žiadne zbytočné účty** — návštevník sa neprihlasuje, aby si pozrel oznam.
- **Dostupnosť** — kontrast, veľkosť písma, štruktúra pre starších používateľov.
- **Oddelenie dát a dizajnu** — dáta a logika **v plugine**, vzhľad **v téme**. Pri zmene témy oznamy a rozpis omší zostávajú nedotknuté.
- **Block-first prístup** — opakovateľné prvky (rozpis omší, najbližšie udalosti) sú custom Gutenberg bloky v plugine, ktoré si farár vie ľubovoľne umiestniť cez Site Editor.

## Štruktúra dokumentácie

- [`00-prehlad.md`](00-prehlad.md) — tento súbor, vysoká úroveň.
- [`01-funkcie.md`](01-funkcie.md) — detail jednotlivých funkcií.
- [`02-datovy-model.md`](02-datovy-model.md) — CPT, post meta, custom bloky.
- [`03-architektura.md`](03-architektura.md) — tech stack, štruktúra repa, distribučný model.
- [`04-obsah-a-roly.md`](04-obsah-a-roly.md) — kto a ako spravuje obsah, používateľské roly.
- [`05-roadmap.md`](05-roadmap.md) — etapy implementácie (0–3 hotové, 4 čaká).
- [`06-struktura-stranky.md`](06-struktura-stranky.md) — frontend layout (homepage, sidebar, header, footer, menu).
- [`07-admin-ux.md`](07-admin-ux.md) — admin UX (oznam workflow, kalendár, setup wizard, lockdown, timezone).
- [`08-feedback.md`](08-feedback.md) — open backlog z reálneho používania po Etape 3.
- [`farsky-web-plan.md`](farsky-web-plan.md) — pôvodný plán z rozhovoru v Claude Chate, ešte pod predošlým pracovným názvom „Farský web" (archív / referencia).

## Otvorené otázky

Tieto rozhodnutia ešte nie sú urobené a vrátime sa k nim:

- **Distribučný model**: ako budú farnosti dostávať updaty pluginu a témy? (manuálny ZIP / Plugin Update Checker / MainWP / Composer satis).
- **Pilotná farnosť**: na ktorej farnosti budeme vyvíjať a testovať pred širším spustením?
- **Licencia**: GPL (open source na GitHube) alebo interný projekt Digitalky?
- **Hostingový model**: bude Digitalka ponúkať hosting v balíku, alebo si farnosti riešia samé?
