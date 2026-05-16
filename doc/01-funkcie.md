# Funkcie

Tento dokument popisuje **čo má aplikácia robiť**, nie ako. Implementačné detaily sú v [`02-datovy-model.md`](02-datovy-model.md) a [`03-architektura.md`](03-architektura.md).

## Kontext

`farnost-v3` je multi-tenant riešenie — funkcie popísané nižšie sa správajú **rovnako naprieč všetkými farnosťami**. Per‑farnosť sa líši iba konfigurácia (názov, adresa, kostoly, kontakty) a obsah (oznamy, úmysly, výnimky), ktoré spravuje farár svojej inštalácie.

Frontend prezentácia je postavená **block‑first** — všetky dynamické prvky (rozpis omší, najbližšie úmysly, najnovšie oznamy) sú **custom Gutenberg bloky** z pluginu, ktoré si farár môže umiestniť kdekoľvek cez Site Editor.

## 1. Oznamy

### Use case

Farár (alebo poverená osoba) každú nedeľu večer / pondelok pripraví týždenné oznamy. Veriaci si ich chce prečítať na webe počas týždňa, často z mobilu.

### Požiadavky

- **Zoznam oznamov** zoradený od najnovších, s viditeľným dátumom platnosti (napr. „Oznamy na 21. nedeľu v cezročnom období").
- **Detail jedného oznamu** s formátovaným textom (nadpisy, zoznamy, odkazy, tučné/kurzíva, prípadne vložené médiá — všetko cez Gutenberg).
- **Aktuálny oznam na úvodnej stránke** — najnovší publikovaný oznam je vidieť hneď, súčasť kombinovaného feedu s udalosťami (viď [§ 5](#5-udalosti-a-život-farnosti)).
- **Archív** — staršie oznamy zostávajú dostupné cez archívnu šablónu (`/oznamy/`), minimálne 1 rok dozadu.
- **Pripnuté oznamy** (pin) — možnosť trvalého zvýraznenia dôležitého oznamu (napr. púť, hody).
- **Plánované publikovanie** — pripraviť oznam vopred a publikovať k dátumu (natívna WP funkcia).

### Mimo scope

- Komentáre, lajky, zdieľanie cez prihlásenie.
- Notifikácie push / e‑mail.
- Newsletter (oznam e‑mailom).

## 2. Sv. omše

### Use case

Veriaci sa pýta: „Kedy je dnes / zajtra / v sobotu omša?" Odpoveď musí byť na webe na jeden klik z úvodnej stránky.

### Požiadavky

- **Pravidelný týždenný rozpis** podľa dní v týždni a kostola / kaplnky. Farnosť môže spravovať viac kostolov (viď [§ 4 Kostoly](#4-kostoly-farnosti)).
- **Výnimky** — sviatky, prikázané sviatky, dovolenka kňaza, pohreby — menia pravidelný rozpis pre konkrétny dátum.
- **Zobrazenie najbližších 7 dní** ako primárny pohľad — blok „Rozpis omší na týždeň".
- **Mesačný / týždenný kalendár** ako sekundárny pohľad (samostatná stránka).
- Pri každej omši: čas, miesto, typ (sv. omša, krížová cesta, pobožnosť, adorácia), prípadný úmysel (viď [§ 3](#3-úmysly-sv-omší)).

### Pravidlá

- Výnimka vždy prebíja pravidelný rozpis pre daný dátum a kostol.
- Ak je výnimka označená ako „zrušené", v daný deň v danom kostole nie je omša.
- Detaily párovania výnimiek s pravidelným rozpisom — viď [`02-datovy-model.md`](02-datovy-model.md#pravidlá-vyhodnotenia-rozpisu).

## 3. Úmysly sv. omší

### Use case

Veriaci si chce overiť, na aký úmysel je omša, na ktorú prinesie obetný úmysel zosnulého príbuzného, alebo si chce naplánovať účasť.

### Požiadavky

- **Úmysel sa viaže na konkrétny dátum + čas + kostol** — t.j. na konkrétny inštanciovaný slot omše (nie na pravidelný rozpis).
- **Verejný zoznam úmyslov** pre najbližšie obdobie (default 4 týždne dopredu) — blok „Úmysly omší".
- **Zobrazenie pri jednotlivej omši** v rozpise — návštevník vidí úmysel priamo pri čase a mieste.
- **Anonymizácia** — administrátor môže označiť úmysel ako anonymný, vtedy sa zobrazí len text typu „súkromný úmysel".
- **Voľný text** — úmysel je krátky text (typicky 1–3 riadky), bez štruktúry.

### Mimo scope vo v3

- Online prihlasovanie nového úmyslu cez web (vrátane platby alebo darovania).
- Notifikácia kňazovi pri novej požiadavke.
- Limity (napr. „len 1 úmysel na omšu").

## 4. Kostoly farnosti

### Use case

Veľa slovenských farností spravuje viac kostolov a kaplniek — farský kostol, filiálne kostoly, kaplnky v domovoch dôchodcov. Každý má vlastný rozpis omší a vlastnú adresu. Veriaci musí jednoznačne vedieť, **kde** je omša.

### Požiadavky

- Evidencia ľubovoľného počtu kostolov v jednej inštalácii.
- Každý kostol má: názov, adresu, voliteľne GPS pre mapku, krátky popis, voliteľný náhľadový obrázok.
- **Pravidelný týždenný rozpis omší** je atribút kostola — každý kostol má svoj.
- Označenie hlavného (farského) kostola — práve jeden naprieč inštaláciou.
- Verejná stránka kostola s rozpisom, adresou a kontextom.

### Mimo scope

- Verejné rezervácie priestorov kostola.
- Hodiny otvorenia mimo bohoslužieb.

## 5. Udalosti a život farnosti

### Use case

Farnosť pravidelne pozýva na púte, koncerty, dni rodiny, prvé sv. prijímania, birmovky, prednášky. Tieto informácie si veriaci chcú prečítať priamo z úvodnej stránky, podobne ako oznamy — bez nutnosti klikať do samostatnej sekcie.

### Požiadavky

- **Bežné WP posty** (`post`), nie samostatný CPT. Farár pozná tento typ obsahu z bežného WP.
- **Triedené kategóriami** (`category`). Plugin pri aktivácii vytvorí default kategórie:
  - `Udalosti` — púte, akcie, koncerty.
  - `Zo života farnosti` — fotky a krátke správy z prebehnutých akcií.
  - `Pozvánky` — pozvánky na nadchádzajúce udalosti, prednášky, večery chvál.
- Farár môže pridávať ďalšie kategórie (napr. `Birmovanci 2027`).
- **Kombinovaný feed na úvodnej stránke** — blok „Aktuálne dianie" zobrazuje najnovšie **oznamy** (z CPT) aj **udalosti** (WP posty) zoradené chronologicky podľa dátumu publikovania.
- **Archív kategórie** — `/category/{slug}/`, štandardná WP funkcia.

### Vlastnosti kategórie

Plugin rozširuje WP kategórie o tieto term meta polia (editovateľné v admine `Príspevky → Kategórie`):

- **Farba** (`farnost_color`) — hex hodnota pre vizuálne odlíšenie kategórie. Použije sa v karte feedu (farebný badge / okraj), v archíve kategórie a v menu.
- **Zobraziť v menu** (`farnost_show_in_menu`) — checkbox, viď [§ 8](#8-automatická-navigácia).

### Vlastnosti postu (udalosti)

Plugin pridáva do editora postu (sidebar panel „Detail udalosti") tieto polia (post meta):

- **Kedy** (`farnost_event_when`) — dátum a čas konania udalosti (datetime picker). Voliteľné — ak je prázdne, frontend pole nezobrazí.
- **Kde** (`farnost_event_where`) — voľný text s miestom konania (napr. „Farský kostol", „Pastoračné centrum", „Lurdská jaskyňa pri kostole"). Voliteľné.

Tieto polia sú dostupné na **každom poste** bez ohľadu na kategóriu — farár ich vyplní podľa relevancie (typicky pri `Udalosti` a `Pozvánky`, prázdne pri retrospektívach v `Zo života farnosti`).

### Pravidlá zobrazenia

- Pripnuté oznamy (CPT) majú vždy prednosť pred bežným chronologickým zoradením vo feede.
- Featured image je odporúčaný pre každý post — používa sa v karte feedu aj v OG metadata.
- V karte feedu sa zobrazuje: featured image, farebný badge kategórie, titulok, dátum publikovania, **„Kedy" + „Kde"** (ak sú vyplnené), krátky úryvok.
- V archíve kategórie sa `farnost_color` použije ako accent farba hlavičky archívu.

### Mimo scope

- Plnohodnotný kalendár udalostí (s mesačným pohľadom, opakovaním, prihlasovaním).
- Notifikácie pripomienok pred udalosťou.
- Viacero termínov pre jednu udalosť (napr. seriál prednášok) — vo v3 jeden post = jeden termín.
- Lokácia ako relácia na `kostol` (vo v3 je „kde" voľný text; v budúcnosti môže byť dropdown).

## 6. Per‑farnosť konfigurácia

### Use case

Pri spustení novej inštalácie farnosti musí farár / administrátor vyplniť **identitu farnosti** — názov, dekanát, diecéza, kontakty, čísla účtov. Tieto údaje sa potom automaticky používajú v päte, hlavičke, OG metadata a v rôznych blokoch.

### Požiadavky (settings stránka v plugine)

- **Identita**: názov farnosti, dekanát, diecéza, patrón / patrocínium, rok založenia (voliteľne).
- **Kontakt**: poštová adresa farského úradu, telefón, e‑mail, web (sám seba), úradné hodiny.
- **Financie**: IBAN pre milodary, číslo na 2 % z dane, identifikačné údaje (IČO ak farnosť má, prípadne občianske združenie).
- **Sociálne siete**: Facebook, YouTube, Instagram (linky).
- **Dizajn**: výber z 5–10 vizuálnych variantov (style variations) — odlišné farby, typografia a spacing, identická štruktúra layoutu. Voľbu robí farár v setup wizarde a vie ju kedykoľvek zmeniť v Site Editore (`Vzhľad → Editor → Štýly`).
- **Logo a farby (override)**: logo farnosti a voliteľný vlastný primary color, ktorý prebíja farbu zo zvolenej style variation.

### Mimo scope

- Online platobná brána na milodary (možná samostatná etapa).
- Per‑rola obmedzenie na úpravu settings (zatiaľ len Administrator).

## 7. Statické stránky

WordPress natívne stránky (`page`), nie samostatný CPT. Vytvorí ich farár pri spustení a ďalej už typicky nemení.

- **Kontakt** — adresa, kontaktný formulár, mapa, IBAN.
- **O farnosti** — história, patrón, súčasnosť.
- **Sviatosti** — info pre žiadateľov o krst, sobáš, prvé sv. prijímanie, birmovku, pohreb (kontakty, podmienky, dokumenty).
- **Aktivity / Spoločenstvá** — voliteľne, podľa farnosti.

Plugin pri aktivácii môže ponúknuť **setup wizard**, ktorý tieto stránky predvyplní šablónami a farár ich len doladí.

## 8. Automatická navigácia

### Use case

Farár nemá editovať menu ručne (chyby, neporiadok, zastarané položky). Menu vzniká **samo** z toho, čo na webe reálne existuje — stránok, CPT a kategórií.

### Požiadavky

- Plugin poskytne custom Gutenberg blok `<Farské menu />`, ktorý sa vloží do header template themy.
- Blok pri každom načítaní zostaví navigáciu z troch zdrojov:
  1. **Pevné položky** — odkazy na: Domov, Oznamy (archív CPT `oznam`), Sv. omše (statická stránka), Kontakt (statická stránka).
  2. **Kategórie WP postov** označené príznakom „Zobraziť v menu" (term meta) — výchozí kategórie `Udalosti`, `Zo života farnosti`, `Pozvánky` ho majú zapnutý.
  3. **Voliteľné statické stránky** označené príznakom „Zobraziť v menu" — pre prípady ako `O farnosti`, `Sviatosti`, `Spoločenstvá`.
- Zoradenie je dané pevným poradím pevných položiek + abecedne alebo cez `term_order` pre kategórie.

### Term meta pre kategórie

Pri každej kategórii pribudne v admine checkbox **„Zobraziť v menu"** (term meta `farnost_show_in_menu`). Default je `true` pre novovytvorené kategórie.

### Mimo scope

- Drag-and-drop reorder položiek v menu z admin UI (vo v3 stačí ich poradie naprogramované).
- Hierarchické sub-menu (drop-down) — vo v3 plochý zoznam.
- Mobile menu pattern (hamburger) — rieši téma cez natívny block pattern.

## 9. Upratovacie skupiny

### Use case

Farníci sa cyklicky striedajú v upratovaní kostola. Farár chce, aby v každom týždennom ozname pribudol riadok „Tento týždeň upratuje: …" automaticky, podľa pevnej rotácie. Po Vianočnom kombinovanom upratovaní vie cyklus manuálne posunúť.

### Požiadavky

- CPT `upratovacia_skupina` (názov, kontakt na vedúcu, voľný text členovia).
- Poradie rotácie cez `menu_order` (drag-and-drop v admine).
- Globálny pointer `farnost_settings.upratovanie.dalsia_skupina` — post ID skupiny, ktorá je nasledujúca.
- Pri publikácii oznamu sa pointer **automaticky posunie** o jedno (modulo počet skupín).
- **Manuálny override** — farár v admine klikne na ľubovoľnú skupinu → akcia „Nastaviť ako ďalšiu na rade".
- **Inline override v ozname** — pre kombinované týždne farár upraví riadok v ozname (snapshot model). Cyklus tým nezmení; korekciu si urobí v admine.

### Mimo scope vo v3

- Verejná stránka „Upratovacie skupiny" so zoznamom a aktuálne na rade (skupiny žijú len v ozname).
- Notifikácia skupiny e-mailom / SMS deň pred ich týždňom.
- Plánovacie kombinácie skupín cez vlastnú entitu výnimky (rieši sa inline v ozname).

## Prierezové požiadavky

- **Responzívny dizajn** — mobil-first.
- **Performance** — Lighthouse Performance > 90 na mobile pre úvodnú stránku.
- **Prístupnosť** — WCAG 2.1 AA aspoň pre kľúčové stránky (Domov, Rozpis omší, Oznamy).
- **SEO** — všetky stránky indexovateľné, zmysluplné meta-popisy, structured data (JSON-LD) pre kostol (Place + Church) a pre oznam (Article).
- **Open Graph náhľady** — pri zdieľaní cez Messenger / WhatsApp / Facebook sa zobrazí náhľadový obrázok a titulok.
- **Časové pásmo** — všetky časy v `Europe/Bratislava`, vrátane prechodov letný/zimný čas.
- **Slovenčina** — jediný jazyk obsahu aj UI vo v3. Plugin aj téma majú vlastný `.pot` súbor pre prípadný preklad v budúcnosti.
- **GDPR** — žiadne cookies bez súhlasu okrem nevyhnutných; žiadne tracking skripty by default.
