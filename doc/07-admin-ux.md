# Admin UX (správa obsahu)

Tento dokument popisuje **administrátorskú stránku** projektu — ako vyzerá práca farára a asistenta v `wp-admin`. Frontend (čo vidia návštevníci) je v [`06-struktura-stranky.md`](06-struktura-stranky.md), dátový model v [`02-datovy-model.md`](02-datovy-model.md), zoznam admin obrazoviek v [`01-funkcie.md`](01-funkcie.md).

## Princípy

- **Najčastejšia úloha musí byť najpohodlnejšia.** Farár trávi najviac času **oznamami** — všetko ostatné UX je optimalizované, aby mu prácu s nimi zjednodušilo (predvyplnená šablóna, auto-dotiahnuté úmysly, jeden klik na publikáciu).
- **Žiadne nútenie** používať naše dátové entity — niektorí farári si úmysly vedú mimo systému a píšu ich rovno do oznamu. Niektorí dokonca celý oznam píšu vo Worde a chcú len nahrať PDF. Systém musí fungovať aj pre nich, štruktúrovaná verzia je voliteľná.
- **Gutenberg ako default editor** — používame čo WordPress poskytuje, nestaviame paralelné admin obrazovky bez dobrého dôvodu. Custom obrazovky (napr. kalendár úmyslov) majú byť odôvodnené tým, že Gutenberg by danú úlohu robil príliš ťažkopádnu.
- **Statické form-stránky v PHP, React tam kde naozaj treba.** Settings, Návod, jednoduché placeholder stránky sú klasické WP PHP formy (Settings API style). React build (`@wordpress/scripts`) zapíname až pri stránkach s reálnou interaktivitou — kalendár omší, setup wizard, Gutenberg sidebar panely, custom bloky. Vďaka tomu nemáme build závislosť tam, kde HTML form stačí.

## Režimy — zapínateľné funkcie

V `farnost_settings` má farnosť **nezávislé toggly** pre štrukturovanú funkcionalitu. Default po inštalácii je **plná verzia** — všetko zapnuté. Farár si nepotrebné moduly vypne.

| Modul | Default | Čo vypína |
|---|---|---|
| **Oznamy** (CPT `oznam`, štruktúrovaný editor) | ON | CPT `oznam`, predvyplnená šablóna, životný cyklus s archívom, kategória „Oznamy" miznúca z feed-u |
| **Úmysly** (CPT `umysel`, kalendárny pohľad) | ON | Kalendár úmyslov, auto-dotiahnutie do oznamu |
| **Rozpis omší** (CPT `kostol` + výnimky) | ON | Sidebar widget „Najbližšia omša", blok `rozpis-omsi`, predvyplnenie do oznamu. Ak farár nemá vyplnený rozpis, sidebar widget sa **silne skryje** (žiadne prázdne miesta). |

**Smart prepojenia medzi togglami**:
- Keď farár vypne **oznamy**, systém sa opýta: „Chcete vypnúť aj úmysly?" — lebo úmysly v systéme bez oznamu strácajú primárnu hodnotu (auto-dotiahnutie). Farár môže odmietnuť, ak ich chce ďalej viesť ako internú evidenciu.

**Funkcionality nezávislé od režimu** (vždy dostupné):
- **Mimoriadny oznam (banner)** — samostatný mechanizmus, funguje aj v lite režime.
- **Bežné WP posty + kategórie** — vždy.
- **Blok `farnost-pdf`** — vlastný blok s vstavaným PDF viewerom a fallback download linkom. Slúži primárne lite režimu (farár nahráva oznam ako PDF do bežného postu v kategórii „Oznamy"), ale je k dispozícii všade.

### Lite režim — typický workflow

Farár vypne **Oznamy** v `farnost_settings`. Potom:

1. V administrácii sa stratí položka „Oznamy" a CPT `oznam`.
2. Farár si v `wp-admin → Kategórie` vytvorí kategóriu **„Oznamy"** (sám, manuálne).
3. Týždenný oznam tvorí ako bežný **WP post** v tejto kategórii — v Gutenbergu pridá blok `farnost-pdf`, nahrá PDF z Wordu, publikuje.
4. Frontend zobrazí PDF priamo cez vstavaný viewer + tlačidlo na stiahnutie.
5. Žiadny snapshot, žiadne auto-dotiahnutie, žiadne karty per deň — len ten PDF.

## Týždenný oznam — kľúčový workflow

### Rytmus oznamu

- Oznam je vždy **týždenný (pondelok–nedeľa)**.
- Systém vie sám určiť, akého týždňa sa oznam týka — farár dátum **nezadáva**.
- **Publikácia je automatická** podľa globálneho nastavenia (`farnost_settings` — deň + čas, napr. nedeľa 08:00). Farár oznam dopíše v drafte, systém ho zverejní sám.
- **Žiadny per-oznam override** publikačného času — pre mimoriadne situácie existuje [Mimoriadny oznam (banner)](06-struktura-stranky.md#mimoriadny-oznam-banner).
- Životný cyklus po publikácii viď [`06-struktura-stranky.md`](06-struktura-stranky.md#životný-cyklus-oznamu).

### Tvorba nového oznamu — predvyplnená šablóna

Keď farár klikne **„Nový oznam"**, systém **negeneruje prázdny editor**, ale **šablónu predvyplnenú z aktuálnych dát**:

- **Rozpis omší na nasledujúci týždeň** (pondelok–nedeľa) — vygenerovaný z pravidelného rozpisu kostola, aplikovaných výnimiek (`omsa_vynimka`) a označení omší.
- **Úmysly pre jednotlivé omše** — ak ich má farár v systéme (`umysel` CPT), naplnia sa automaticky pre dané dátumy. Ak nepoužíva úmysly v systéme, slot ostane prázdny a farár si tam dopíše svoje.
- **Sviatky a liturgické spomienky** — naplnia sa z liturgického kalendára.
- **Voľný textový blok pod rozpisom** — prázdny, čaká na „bežné oznamy" od farára.

### Snapshot model — oznam je po publikácii zamrznutý

Pri vytvorení oznamu systém **prevezme snapshot** všetkých zdrojových dát do oznamu samotného. Po publikovaní je oznam **zamrznutý**:

- Neskoršie zmeny v rozpise / úmysloch / výnimkách sa **na publikovanom ozname nepremietnu**.
- Kým je oznam v drafte, farár môže ktorýkoľvek údaj **inline upraviť** (zmeniť označenie omše, prepísať úmysel, dopísať poznámku) — úprava platí len pre tento oznam, do zdrojov sa nepropaguje.

Tým je predvídateľné, čo presne sa zverejní — žiadne nečakané zmeny na publikovanom ozname kvôli editácii súvisiacej entity.

### Editor — Gutenberg

Oznam sa edituje v **Gutenberg block editore** (žiadna paralelná custom admin obrazovka):

- **Smart blok „Rozpis omší"** — pred-vyplnený, sedí navrchu. Inline editovateľné polia pre čas, označenie omše, úmysel. Pridať / odobrať omšu, presunúť poradie.
- **Voľný textový blok pod rozpisom** — jednoduchý WYSIWYG: paragraph, heading, bold/italic, list. Sem farár píše „bežné oznamy" (zbierky, akcie, pohreby, milodary…). Sem nepatrí rozpis — ten je vyššie.
- **Štandardné Gutenberg sidebar panely** vpravo (publikácia, kategórie, …).

Detailný zoznam povolených blokov vo voľnej sekcii doladíme v Etape 3.

### Vizuálne — pozri 06

Frontend rendering rozpisu omší (karty per deň, badge pre liturgický stupeň, podtitul pre meno sviatku) — pozri [`06-struktura-stranky.md`](06-struktura-stranky.md#rozpis-omší--vizuálny-formát). Admin editor zhruba kopíruje frontend layout, aby farár videl, čo bude na webe (nie 1:1, ale v duchu).

## Kalendárny pohľad — omše a úmysly

Klasická CPT edit obrazovka pre `umysel` je pre walk-up scenár („pán Kovač chce omšu za zosnulú manželku 14. júna o 18:00") príliš ťažkopádna. Preto vlastná **kalendárna obrazovka** v admine, ktorá zároveň slúži ako vstupný bod pre **výnimky** (mimoriadne omše).

### UX

- **Predvolený pohľad: mesiac** (farár vidí celý mesiac naraz, urobí si prehľad voľných slotov). Prepínač na **týždeň** pre detailnejšie doplnenie.
- **Zobrazené sú všetky omše** — aj pravidelné z rozpisu, aj výnimky (mimoriadne omše). Prázdny slot je viditeľný (krúžok / „voľné"), obsadený slot zobrazuje text úmyslu.
- **Klik na existujúcu omšu** otvorí popup s editáciou úmyslu (a pri výnimke aj času + označenia). Save zatvorí popup, kalendár sa aktualizuje.
- **Pridanie mimoriadnej omše (= výnimka)** — pri každom dni je tlačidlo „Pridať omšu v tento deň", ktoré otvorí popup s poliami `čas` + `označenie` + `úmysel`. Save vytvorí novú výnimku.
- **Úmysel je voľný text** — žiadne štruktúrované polia (žiadne „darca", „výročie", …). Ak farár chce zapísať viac úmyslov na jednu omšu, napíše ich do tej istej bunky — to je na ňom.

### Viac kostolov vo farnosti

- **Jeden zdieľaný kalendár** zobrazuje omše zo **všetkých kostolov** farnosti naraz.
- Omše jednotlivých kostolov sú **farebne rozlíšené** (legenda hore).
- Nemá zmysel filtrovať — väčšina farárov má 1–2 kostoly a chce vidieť oboje naraz, aby nezdvojili termín.

### Spätné editovanie minulých omší

**V MVP nie.** Kalendár sa správa ako „pre budúce omše". Ak vznikne reálna potreba (napr. zapísať, čo sa povedalo na minulej omši pre archív), prerobíme to neskôr — `umysel` CPT to dátovo bez problémov zvládne, ide len o UX rozšírenie.

## Pravidelný rozpis omší kostola

Rozpis modeluje **iba pravidelné týždenné omše** (pondelok–nedeľa). Všetky odchýlky (prvý piatok mesiaca, advent / rorátne, sezónne zmeny, sviatočné posuny časov, mimoriadne mládežnícke omše) sa riešia cez `omsa_vynimka` — nie cez zložité pravidlá v rozpise.

### Čo je súčasťou pravidelného rozpisu

- **Čas omše** (HH:MM).
- **Default označenie** (voliteľné, voľný text — „mládežnícka", „detská", …). Ak je každý štvrtok 19:00 detská, patrí to do rozpisu a v ozname sa to pred-vyplní. Per-occurrence označenie (jediný štvrtok výnimočne mládežnícka) ide cez výnimku alebo inline úpravu v ozname.

### UX — panel na `kostol` CPT

Editovanie rozpisu sa robí v Gutenberg sidebar paneli na CPT `kostol`. Štruktúra je **deň-v-týždni → zoznam časov**:

```
PONDELOK
  ➕ Pridať omšu
UTOROK
  • 18:00  [označenie: ___________]   🗑
  ➕ Pridať omšu
STREDA
  • 18:00  [označenie: ___________]   🗑
  ➕ Pridať omšu
...
NEDEĽA
  • 8:00   [označenie: ___________]   🗑
  • 10:30  [označenie: ___________]   🗑
  ➕ Pridať omšu
```

**Prečo deň-v-týždni štruktúra** (nie flat repeater):
- Vizuálne sedí s frontendom (karty per deň).
- Farár myslí v poradí pondelok → nedeľa, nemusí triediť.
- Prázdny deň (bez omše) je jasne viditeľný — žiadne tiché pravidlo „chýba pondelok".

**Sezónne zmeny** (letný / zimný rozpis): farár jednoducho **edituje rozpis** keď príde sezóna. Nevedie sa to ako dva paralelné rozpisy — bola by to neúmerná komplexita oproti reálnej frekvencii zmien (2× ročne).

## Výnimky (mimoriadne omše)

Výnimka modeluje **mimoriadnu omšu** pre konkrétny dátum — pohreb, sobáš, slávnosť mimo pravidelného rozpisu, advent / rorátne, mimoriadna mládežnícka.

### Princíp: čisto aditívna

Výnimka **neovplyvňuje pravidelný rozpis** — žiadna logika typu „nahrádza" / „ruší". Pri generovaní šablóny oznamu systém zoberie **pravidelný rozpis + výnimky** a obe vrstvy aditívne spojí.

Ak má pravidelná omša byť zrušená (napr. večerná omša v deň, kedy je pohreb), farár ju **inline vymaže v ozname** — snapshot model to umožňuje. Systém sa nesnaží uhádnuť „toto nahrádza tamto".

### Štruktúra výnimky

| Pole | Typ | Povinné |
|---|---|---|
| `dátum` | date | áno |
| `kostol` | reference (CPT `kostol`) | áno |
| `čas` | time (HH:MM) | áno |
| `označenie` | text | nie |
| `úmysel` | text | nie |

(Presné post meta kľúče doladíme v Etape 1 v [`02-datovy-model.md`](02-datovy-model.md).)

### Kde sa výnimka vytvára

Žiadna samostatná CPT obrazovka v admin menu. Výnimka vzniká v kontexte, kde je prirodzene potrebná — z **dvoch miest**:

1. **Z kalendárneho pohľadu** — pre konkrétny deň farár klikne „Pridať omšu v tento deň", vyplní čas + označenie + úmysel, save. Toto je typický walk-up scenár (farník príde s prosbou o pohreb).

2. **Z editora oznamu** — pri inline pridaní novej omše do karty dňa má farár checkbox „Pridať aj do verejného rozpisu" (= vytvor výnimku). Predvolené správanie je **len lokálne** (v ramci snapshotu oznamu). Toggle on znamená, že záznam sa zapíše aj ako `omsa_vynimka`, takže sa zobrazí v sidebare „najbližšia omša" a v bloku `rozpis-omsi` aj pre návštevníkov, ktorí oznam nečítajú.

## Custom „Farnosť" admin menu

Plugin pridáva **jedno top-level menu** v `wp-admin` ľavom sidebare s ikonou kríža. Zoskupuje všetky farské obrazovky, aby farár nepátral po nich po štandardných WP miestach.

```
☩ Farnosť                  ← top-level admin menu
├─ Kalendár omší           ← kalendár (omše + úmysly + výnimky)
├─ Kostoly                 ← CPT archive, sem ide pravidelný rozpis
├─ Oznamy                  ← CPT archive, len v plnom režime
├─ Mimoriadny oznam        ← rýchlo dostupný banner editor
├─ Upratovacie skupiny     ← CPT archive, drag-and-drop poradie + pointer
├─ ─────────
├─ Nastavenia              ← farnost_settings
└─ Návod                   ← onboarding checklist
```

### Logika poradia

- **Kalendár omší navrchu** — najčastejšie navštevovaná obrazovka. Farár tu pridáva úmysly a výnimky **denne / týždenne**. Kostoly nastaví raz, Oznamy tvorí raz týždenne — kalendár je rýchle akcie.
- **Mimoriadny oznam ako samostatná položka**, nie zanorená v Oznamoch. Cieľ — **jeden klik** v krízovom momente (úmrtie, mimoriadna informácia), žiadne hľadanie.
- **Výnimky a Úmysly nemajú vlastnú položku** — vznikajú z kalendára (a výnimky aj z editora oznamu). Vlastná CPT archive obrazovka by farára len mýlila.

### Skrývanie podľa režimu

Menu reaguje na toggly v `farnost_settings`:

| Vypnutý modul | Skryté položky |
|---|---|
| Oznamy | „Oznamy" |
| Úmysly | (žiadne — kalendár ostáva, ak je zapnutý aspoň rozpis omší) |
| Rozpis omší | „Kalendár omší", „Kostoly" |

Ak farár vypne **všetko**, ostanú len `Nastavenia`, `Návod` a `Mimoriadny oznam`. Vtedy už nemá veľký zmysel mať vlastné top-level menu — možno v budúcnosti zvážiť, či ho v takom prípade celkom skryť (ale to riešime, ak na to reálne narazíme).

### Bežné WP položky ostávajú

Posty, Stránky, Médiá, Komentáre, Kategórie sa **neskrývajú**. Farár ich potrebuje:
- **Stránky** — statické obsahy (O farnosti, Sviatosti, História…).
- **Posty** — v lite režime sem ide oznam s PDF blokom.
- **Kategórie** — farebné kategórie pre frontend feed.
- **Médiá** — uploady (fotky do galérií, PDF, hudobné súbory).

**Komentáre** sa v MVP nepoužívajú (frontend ich nezobrazuje, viď [`06-struktura-stranky.md`](06-struktura-stranky.md#detail-oznamu--udalosti)) — položku v admin sidebare, admin bare aj „At a Glance" widget na dashboarde **skrývame** (`src/Admin/CommentsHide.php`). Diskusia je default `closed` na všetkých post types, post type support pre `comments` je odhlásený. Existujúce komentárové dáta v DB nemažeme — defenzívne pre prípad budúcej reaktivácie.

## Upratovacie skupiny

Farníci, ktorí sa starajú o poriadok v kostole, sú organizovaní do **skupín v cyklickej rotácii**. Systém riadi rotáciu sám a vkladá meno aktuálnej skupiny do týždenného oznamu.

### Admin obrazovka

Nová položka v menu „Farnosť → Upratovacie skupiny". Vyzerá ako jednoduchý CPT archive so špeciálnymi prvkami:

```
Upratovacie skupiny                                [ Pridať skupinu ]

┌─────────────────────────────────────────────────────────────────┐
│ ☰  Skupina sv. Jozefa             • Aktuálne na rade            │
│    Mária N., Anna K., Helena P.                                  │
│    Vedie: Anna K., 0905 ...                       [Upraviť] [🗑]│
├─────────────────────────────────────────────────────────────────┤
│ ☰  Skupina č. 2                                                  │
│    Eva S., Jana L.                                               │
│                                                   [Upraviť] [🗑]│
│                                  [ Nastaviť ako ďalšiu na rade ] │
├─────────────────────────────────────────────────────────────────┤
│ ☰  Skupina č. 3                                                  │
│    Peter D., Marta Ž.                                            │
│                                                   [Upraviť] [🗑]│
│                                  [ Nastaviť ako ďalšiu na rade ] │
└─────────────────────────────────────────────────────────────────┘
```

- **Drag-and-drop** (☰) — farár si poradie rotácie zoradí pretiahnutím. Poradie sa ukladá do `menu_order`.
- **Indikátor „• Aktuálne na rade"** je pri skupine, na ktorú smeruje pointer (`farnost_settings.upratovanie.dalsia_skupina`).
- **Tlačidlo „Nastaviť ako ďalšiu na rade"** na každej (inej) skupine — klik prepíše pointer. Použitie: po Vianočnom kombinovanom upratovaní farár klikne na skupinu, ktorá má reálne nasledovať.
- **Editovanie skupiny** = klasický Gutenberg edit screen, polia: názov, kontakt (telefón / e-mail vedúcej), členovia (voľný text).

### Integrácia s oznamom

- Pri tvorbe oznamu systém **auto-vloží riadok** do voľnej sekcie: „Tento týždeň upratuje: <názov skupiny>".
- Pri publikovaní oznamu sa pointer **automaticky posunie** na ďalšiu skupinu v poradí (modulo počet skupín).
- Farár môže riadok v ozname **inline editovať** (snapshot model) — napr. pred Vianocami napíše „Tento týždeň upratujú spoločne skupiny 3, 4 a 5". Toto pointer **nemení**; pre korekciu cyklu treba zájsť do admin obrazovky a manuálne posunúť.

## Setup wizard

Sprievodca prvotným nastavením, ktorý sa **otvorí hneď po aktivácii pluginu**. Cieľ — z prázdneho WordPressu spraviť fungujúci farský web bez toho, aby farár pátral po dvadsiatich miestach v admine.

### Princíp

- **Plnoobrazovkový režim** — žiadny WP admin chrome okolo (sidebar / header skryté), aby nič farára nerozptyľovalo.
- **Postupné kroky** s veľkými poľami a jasnými inštrukciami. Tlačidlá „Pokračovať" / „Späť" / „Dokončiť neskôr".
- **Žiadne nútenie vyplniť všetko** — väčšina polí je voliteľná, dôraz na to, aby farár prešiel celý wizard za 2 minúty, ak chce.

### Kroky

1. **Identita farnosti** — názov *(povinné)*, diecéza, patrocínium, krátky popis.
2. **Kontakt** — adresa, telefón, e-mail, web, IBAN. Všetko voliteľné (dá sa dodať neskôr).
3. **Prvý kostol** — názov *(povinné, aspoň jeden kostol)*, adresa, mapa, **pravidelný rozpis omší** (viď [Pravidelný rozpis omší kostola](#pravidelný-rozpis-omší-kostola)). Rozpis je voliteľný — môže sa dodať neskôr.
4. **Logo a farby** — výber štýlovej variácie z náhľadov ([`05-roadmap.md`](05-roadmap.md#etapa-3--téma-a-frontend) — 5–10 variácií) + upload loga. Obe voliteľné.
5. **Režim** — plný (CPT `oznam`, kalendár úmyslov) vs. lite (PDF do bežného postu). Default plný. Viď [Režimy](#režimy--zapínateľné-funkcie).

### Označenie polí

Každé pole má vizuálne odlíšené, či je **Povinné** alebo **Voliteľné**. Voliteľné polia farár môže preskočiť bez varovaní — žiadne „Naozaj chcete preskočiť IBAN?". Web bude fungovať aj bez nich.

### Pauza a návrat

- **„Dokončiť neskôr"** v ľubovoľnom kroku zatvorí wizard a zapamätá, kde farár skončil.
- V `wp-admin` sa potom objavuje admin notice „Setup farnosti nie je dokončený — pokračovať?" s odkazom späť do wizardu.
- Notice mizne, keď farár wizard dokončí alebo manuálne zatvorí („Už to nepripomínať").

### Po dokončení wizard automaticky vytvorí

- **Statické stránky** (O farnosti, Sviatosti, Kontakt) s pripravenými šablónami zloženými z povolených blokov.
- **3 default kategórie** (Udalosti, Zo života farnosti, Pozvánky) s farbami a `farnost_show_in_menu = true`.
- **Prvý oznam ako prázdny draft** (v plnom režime) — farár ho otvorí, vidí predvyplnenú šablónu, naberá kontakt s editorom.

### Vzťah k Nastaveniam

`Farnosť → Nastavenia` zrkadlí kroky wizardu — **rovnaké sekcie** (Identita, Kontakt, Kostol, Logo a farby, Režim). Vďaka tomu si farár nemusí pamätať, „v ktorom kroku som dal IBAN" — proste ide do Nastavení a doplní tam.

### Pripomienka nedokončených vecí

Na **WP dashboarde** je widget „Doplniť do nastavení", ktorý zobrazí konkrétne chýbajúce položky (napr. „IBAN", „Logo farnosti", „Mapa kostola") s **priamym odkazom na konkrétne pole** v Nastaveniach. Widget mizne, keď je všetko vyplnené, alebo keď farár klikne „Skryť — nie je to potrebné".

**Nie checklist v Návode** — bolo by to dvojenie. Návod je čistá dokumentácia / knowledge base, nie state tracker.

## Pomoc a dokumentácia

### V3 MVP — kontextová pomoc + statická dokumentácia

Pre MVP žiadne AI — vystačíme si s konvenčnými prostriedkami, ktoré sú lacné a spoľahlivé:

- **Kontextová pomoc**: ikona „?" pri každom dôležitom poli, hover → krátka vysvetlivka. Inline tipy v editore oznamu („Tu napíšeš bežné oznamy — zbierky, akcie, pohreby…").
- **Stránka Návod** v admin menu — knowledge base s návodmi typu „Ako napísať oznam", „Ako pridať pohreb", „Ako vymeniť hero obrázok". Vyhľadávanie. Žiadny progress tracker (state je v dashboard widgete).
- **Onboarding text** v setup wizarde — pri každom kroku krátky úvod „Prečo to tu vyplniť".

### V4 vízia — offline LLM asistent

Plánovaná na **v4, nie v3 MVP**. Vízia: **lokálne bežiaci jazykový model** (žiadne cloud API, žiadne posielanie dát mimo farský server) s úzkym scope **len na tento projekt** — vie do bodky, ako funguje plugin, dátový model, jednotlivé obrazovky. Otázku mimo scope nezodpovie („nie som na to vyškolený").

Otvorené otázky pre v4 — kvalita malých lokálnych modelov, hosting na farských serveroch bez GPU, distribúcia modelu so ZIP releasom. Riešime, keď príde čas.

## Náhľad oznamu

Pre náhľad oznamu pred publikovaním sa **používa štandardný Gutenberg „Náhľad" gombík** — žiadny custom render. Preview otvorí oznam v novom tabe s tým istým frontend renderom (karty per deň, badge, podtitul, voľný text), ktorý bude použitý po publikovaní. Stačí to — neideme stavať vlastný náhľadový systém.

## Práva a roly v admin menu

Položky v „Farnosť" menu sa zobrazujú podľa role:

| Položka | Farár (Editor) | Asistent (`farnost_asistent`) |
|---|---|---|
| Kalendár omší | plný (úmysly + výnimky) | **úmysly áno**, „Pridať omšu" *(výnimka)* skryté |
| Kostoly | plný | skryté |
| Oznamy | plný | skryté |
| Mimoriadny oznam | plný | skryté |
| Nastavenia | plný | skryté |
| Návod | plný | plný |

Plus štandardné WP **Príspevky** (na vlastné udalosti) a **Médiá** ostávajú obom rolám. Detaily capabilities viď [`04-obsah-a-roly.md`](04-obsah-a-roly.md).

### V4 vízia — per-user práva namiesto rolí

Idea pre budúcnosť: vlastné UI v users edit screen, kde farár môže **per konkrétneho užívateľa** zaškrtnúť dodatočné práva nad rámec jeho role (napr. „tento asistent môže pripraviť draft oznamu, ale nepublikovať"). Implementačne cez `user_has_cap` filter, ktorý prepíše role-based caps. **Nie v3 MVP** — pre väčšinu farností stačí binárny model Farár / Asistent.

## Inline edit rozpisu v ozname

V editore oznamu sa rozpis omší upravuje **klikom priamo do textu** (Notion-style). Klik na „18:00" alebo na text úmyslu zmení daný kúsok na input, prepíše sa, klik mimo uloží. Žiadne pencil ikony, žiadny sidebar mirror.

Akceptujeme miernu cenu v discoverability — farár sa to za prvé 1–2 oznamy naučí a potom je to najrýchlejší možný workflow.
