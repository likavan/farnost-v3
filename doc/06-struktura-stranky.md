# Štruktúra stránky

Tento dokument popisuje **vizuálnu a obsahovú štruktúru** verejného frontendu — čo návštevník vidí, aký layout zdieľajú stránky, ako sa skladá obsah a aké rozhodnutia sú za tým.

Doplňuje [`03-architektura.md`](03-architektura.md) (technická stránka) a [`01-funkcie.md`](01-funkcie.md) (čo systém vie).

## Princíp: jeden zdieľaný layout

Všetky verejné stránky (homepage, detail oznamu, detail kostola, archív kategórie, výsledky vyhľadávania, 404, „statické" stránky ako *O farnosti*) zdieľajú **jeden šablónový vzor**. Mení sa **iba obsah ľavého stĺpca** — sidebar, hlavička a päta sú konštantné.

```
┌─────────────────────────────────────────┐
│ Header (logo + názov + menu + 🔍)       │
├──────────────────────────┬──────────────┤
│                          │              │
│   Obsahová zóna          │   Sidebar    │
│   (feed / detail /       │   (omša +    │
│    archív / výsledky /   │    kontakty) │
│    statická stránka)     │              │
│                          │              │
├──────────────────────────┴──────────────┤
│ Footer (info + externé odkazy)          │
└─────────────────────────────────────────┘
```

**Prečo**: konzistentný layout znižuje kognitívnu záťaž pre veriacich (vedia, kde čo nájdu) a zužuje priestor, kde môže farár cez Site Editor niečo nechtiac rozhodiť.

## Hlavička (Header)

Obsahuje, zľava doprava:

- **Logo** farnosti.
- **Názov farnosti** (textový, čitateľný aj keď logo nesedí so štýlovou variáciou).
- **Hlavné menu** — viacúrovňové (dropdown pri parent stránkach).
- **Vyhľadávanie**.

### Správanie

- **Sticky pri scrollovaní** — áno. Pri dlhom feed-e má návštevník menu a vyhľadávanie vždy na dosah.
- **Mobil = hamburger menu** — viacúrovňové menu sa na malých obrazovkách inak rozumne nezmestí.
- **Vyhľadávanie** = ikona „lupa", ktorá sa po kliku rozbalí na input field. Šetrí horizontálny priestor v hlavičke (najmä na mobile) za cenu jedného kliku navyše — vyhľadávanie nie je primárny use-case, väčšina návštevníkov chodí cez menu / homepage.

## Hlavné menu

**Generuje sa automaticky** zo stránok, ktoré majú zapnutý príznak **„Zobraziť v menu"** (checkbox na stránke, podobný princíp ako `farnost_show_in_menu` na kategóriách v [`02-datovy-model.md`](02-datovy-model.md)).

- Viacúrovňové menu vzniká z **parent/child hierarchie stránok**.
- Aj keď farár zabudne checkbox kliknúť, stránka stále existuje a je dostupná cez URL.
- **Neriešime klasické WP nav menu** (Vzhľad → Menu) — je to zdroj „ako toto pridám" otázok a vyžaduje extra krok pri každej novej stránke.

> Zodpovedá custom bloku `farnost-menu` v [`05-roadmap.md`](05-roadmap.md#etapa-3--téma-a-frontend).

## Homepage

Obsahová zóna = **hlavný feed**. Sidebar = vždy ten istý (viď nižšie).

### Hlavný feed

Kombinovaný zoznam **udalostí + WP postov + najnovšieho oznamu** (mapuje sa na blok `aktualne-dianie`).

**Poradie**: striktne chronologicky podľa dátumu publikovania (zostupne). **Žiadne sticky pinning** — oznam sa zaradí v momente publikovania a novšie záznamy ho prirodzene odsunú nadol.

**Oznam vo feed-e je vždy maximálne jeden** — ten posledný publikovaný. Keď príde nový, **predchádzajúci z feedu úplne zmizne** a žije ďalej len v archíve „Oznamy" (viď [Životný cyklus oznamu](#životný-cyklus-oznamu)).

V **lite režime** (oznamová funkcia vypnutá v `farnost_settings`) je „oznam" len bežný WP post v kategórii „Oznamy" s blokom `farnost-pdf`. Vtedy logika „len jeden vo feed-e" neplatí — posty sa správajú štandardne. Viď [Lite režim](07-admin-ux.md#lite-režim--typický-workflow).

**Stránkovanie**: ~10 položiek, tlačidlo **„Načítať ďalšie"**. **Žiadny infinite scroll** — návštevník by inak nikdy nevidel footer.

**Udalosti sa neskrývajú** po prebehnutí — feed funguje aj ako **archív farnosti**. Žiadne pravidlá automatického skrývania, žiadny custom „skryť z feedu" prepínač. Ak farár niečo nechce mať verejne, klasicky to **zmaže** (alebo presunie do draftu) — to je dostatočné a netreba paralelný mechanizmus.

## Sidebar

Vpravo, na všetkých stránkach rovnaký. Obsah:

- **Najbližšia omša** (resp. dnešný rozpis). Skryje sa, ak farár nemá vyplnený rozpis omší (lite režim / nevyplnené `kostol`).
- **Rýchle kontakty** (telefón, e-mail).
- **Tlačidlo „Aktuálne oznamy"** — odkaz na archív oznamov.
- **IBAN / milodary** — návštevník chce občas darovať, číslo účtu má byť viditeľné bez kliku.
- **Citát dňa** — náhodný biblický verš alebo citát svätého, **fixný na celý deň** (všetci v ten deň vidia rovnaký). Plugin obsahuje **prednastavený zoznam** ~20 položiek, farár v `Nastaveniach → Citáty` môže pridať vlastné (textarea, jeden riadok = jeden citát + voliteľný autor).

### Mobilná verzia

Sidebar ide **nad feed**, ale **zostručnený** (jednoriadkové zhrnutie najbližšej omše + ikonky kontaktov). Plná verzia pod feedom by nikoho neoslovila — nikto by sa tam neposcrolloval.

## Footer

**3-stĺpcový layout**, na mobile sa stĺpce poskladajú pod seba.

```
┌────────────────────┬───────────────────┬──────────────────────┐
│ Farnosť            │ Kontakt           │ Užitočné odkazy      │
│ <logo malé>        │ Adresa            │ → Biskupský úrad     │
│ Krátky popis       │ Telefón           │ → Obec               │
│ IBAN: SK...        │ E-mail            │ → Susedné farnosti   │
│ <ikony FB/IG>      │ Otváracie hodiny  │ → Iné                │
└────────────────────┴───────────────────┴──────────────────────┘
                © 2026 Farnosť · Beží na farnost-v3
```

**Naplnenie obsahu**:

- **Stĺpec 1 (Farnosť)** sa naplní z `farnost_settings` automaticky (logo, popis, IBAN, sociálne).
- **Stĺpec 2 (Kontakt)** tiež automaticky z `farnost_settings`.
- **Stĺpec 3 (Užitočné odkazy)** farár vloží manuálne v `Nastaveniach` (zoznam vo formáte „Názov | URL", podobný repeater UI ako rozpis omší).

## Ostatné typy stránok

Všetky používajú **rovnaký layout**. Líši sa len to, čo je v obsahovej zóne.

| Typ stránky | Obsahová zóna |
|---|---|
| **Detail oznamu / udalosti** | viď nižšie |
| **Detail kostola** | poskladá farár z blokov (`kostol-info`, `rozpis-omsi`, texty) |
| **Archív kategórie** | filtrovaný feed (po kliku na farebný badge kategórie) |
| **Výsledky vyhľadávania** | zoznam zhôd |
| **Statické stránky** (O farnosti, Sviatosti, …) | poskladá farár z povolených blokov |
| **404** | špeciálna (obsah doriešime) |

**Kalendárny pohľad na udalosti** — neskôr, mimo MVP.

## Životný cyklus oznamu

> **Platí pre plný režim** (oznamová funkcia zapnutá). V lite režime je oznam bežný WP post s PDF blokom — viď [Lite režim](07-admin-ux.md#lite-režim--typický-workflow).

Oznam je týždenný (pondelok–nedeľa) a má **pevný rytmus zverejňovania**, ktorý platí naprieč farnosťou:

- **Globálne nastavenie** v `farnost_settings` — deň v týždni + čas, kedy sa nové oznamy publikujú (napr. nedeľa 08:00).
- Farár oznam píše v drafte (často **na etapy** počas týždňa), systém ho zverejní v naplánovaný moment automaticky.
- **Žiadny override** pre konkrétny oznam — pravidelný oznam je striktne v rytme. Výnimočné prípady rieši [Mimoriadny oznam (banner)](#mimoriadny-oznam-banner).
- Keď nový oznam vyjde, **predchádzajúci automaticky mizne z hlavného feed-u** a žije ďalej v archíve „Oznamy" (dostupný cez tlačidlo v sidebare alebo cez menu).
- **Snapshot model**: pri vytvorení oznamu sa rozpis omší, úmysly, výnimky a sviatky **prevezmú do oznamu ako kópia**. Po publikovaní je oznam **zamrznutý** — neskoršie zmeny v rozpise / úmysloch / výnimkách sa už na publikovanom ozname neprejavia. Farár môže ešte v drafte ktorýkoľvek údaj inline upraviť (zmeniť označenie omše, prepísať úmysel, dopísať poznámku).

Týmto si farnosť drží konzistentný rytmus — návštevník vie, že každú nedeľu ráno je nový oznam, a nemusí ho stále hľadať vo feed-e medzi udalosťami.

## Rozpis omší — vizuálny formát

Rozpis omší na týždeň sa vykresľuje **jednotne** všade, kde sa zobrazuje — v ozname, v bloku `rozpis-omsi` na stránke kostola, a v sidebare v zostručnenej podobe. Vďaka tomu má návštevník konzistentnú skúsenosť a téma sa nemusí starať o viac variantov.

**Formát: karty per deň.** Každý deň týždňa (pondelok–nedeľa) je samostatná karta. Pri jednom dni s viacerými omšami sú všetky omše zoskupené v jednej karte.

```
┌─────────────────────────────────────────────┐
│  UTOROK · 19. 5.              [ SLÁVNOSŤ ] │
│  Sv. Cyril a Metod                          │
├─────────────────────────────────────────────┤
│   18:00    Detská                           │
│            † Mária Nováková                 │
└─────────────────────────────────────────────┘
```

**Anatómia karty**:

- **Hlavička**: deň + dátum vľavo, **badge liturgického stupňa** vpravo (Slávnosť / Sviatok / Spomienka). Ak nie je sviatok, badge sa nevykresľuje.
- **Podtitul** v hlavičke: meno sviatku alebo spomienky (ak je).
- **Telo karty**: zoznam omší daného dňa — čas, voliteľné **označenie omše** (mládežnícka, detská, rorátna…), úmysel.

**Deň bez omše** — karta sa stále zobrazí (aby to nepôsobilo ako chyba) s textom „Sv. omša nie je". Často je v tej karte aspoň sviatok / spomienka, takže nikdy nie je úplne prázdna.

```
┌─────────────────────────────────────────────┐
│  PONDELOK · 18. 5.          [ SPOMIENKA ]  │
│  Sv. Pius                                   │
├─────────────────────────────────────────────┤
│   Sv. omša nie je                           │
└─────────────────────────────────────────────┘
```

## Mimoriadny oznam (banner)

Pre prípady, ktoré nepočkajú do najbližšieho riadneho oznamu (úmrtie cez týždeň, mimoriadna informácia), existuje **mimoriadny oznam ako banner**.

- **Jeden mimoriadny oznam v jednom čase** (žiadne radenie viacerých — keď farár publikuje nový, prepíše predošlý).
- **Obsah**: jeden text s možnosťou základného formátovania (rich text), žiadne bloky / obrázky / galérie. Cieľ — rýchle vytvorenie, žiadna logická záťaž navyše.
- **Voliteľná expiry** — farár môže nastaviť dátum/čas, kedy sa banner sám skryje. Ak ho nenastaví, banner žije dovtedy, kým ho farár ručne nezruší.
- **Ľahko dostupné** v admine — predpokladáme samostatnú položku v menu „Farnosť" alebo widget na dashboarde, nie zanorené v CPT.
- **Umiestnenie na webe**: pod sticky header-om, cez celú šírku. (Nie *nad* header-om — pod sticky hlavičkou sa banner pri scrollovaní prirodzene odscrolluje hore, kým header ostáva.)
- **Vzhľad**: farebný pás v kontrastnej farbe (z aktuálnej štýlovej variácie), tučný riadok textu, voliteľná malá ikona (zvonček / info). Žiadne obrázky, žiadny CTA button — banner je textový a stručný.
- **Nesticky** — banner pri scrollovaní zmizne hore, aby neprekážal čítanie. Sticky by bol omnipresent a otravný.
- **Možnosť zatvoriť „×"** — návštevník banner zatvorí, voľba sa zapamätá v cookie (per banner identifier — timestamp alebo hash obsahu). **Keď farár publikuje nový banner alebo zmení obsah existujúceho**, identifier sa zmení a banner sa zobrazí znovu aj tomu, kto ho predtým zatvoril.

**Pozor — toto nahrádza pôvodnú úvahu o sticky pinningu vo feed-e.** Mimoriadny oznam sa do hlavného feedu **nezaraďuje** — banner je samostatný mechanizmus, žije nad ním.

## Detail oznamu / udalosti

Obsahová zóna obsahuje:

- **Nadpis**.
- **Dátum publikovania** + pri udalosti aj **dátum konania**.
- **Farebný badge kategórie**.
- **Featured image** (ak je).
- **Autor** (meno toho, kto napísal — farár / katechétka / asistent).
- **Telo článku** — s blokmi, ktoré sú povolené (vrátane vlastnej **fotogalérie** ako súčasť obsahu, nie samostatné pole).
- **Tlačidlo „Späť"** na predchádzajúci zoznam.

**Vyradené** (v MVP):

- Komentáre.
- Related / podobné články.
- Breadcrumbs — pri 2–3 úrovňovej hierarchii zbytočné.
- Čas čítania.

### Zdieľanie

Pod článkom **tri minimalistické tlačidlá**: **Kopírovať odkaz** (clipboard API), **Facebook** (native sharer URL, žiadny FB SDK), **WhatsApp** (`wa.me/?text=...`). Žiadne tracking scripty, žiadne externé widgety.

**Vypínateľné** v `Nastaveniach` — farnosť, ktorá zdieľanie nechce, môže celú lištu skryť jedným prepínačom.

## Constrained editor (paleta blokov)

**Princíp**: farár dostane **obmedzenú paletu blokov**, ktoré sú **graficky predpripravené** (style variations, sensible defaults). Cieľ — menej slobody = menej spôsobov, ako to pokaziť. Frontend ostáva konzistentný naprieč farnosťami a stránkami.

### Vlastné bloky (z [`05-roadmap.md`](05-roadmap.md#etapa-3--téma-a-frontend))

- `rozpis-omsi`
- `aktualne-dianie`
- `najnovsi-oznam`
- `umysly-list`
- `farnost-menu`
- `kostol-info`
- `kontakt-farnost`
- **`farnost-galeria`** *(nový)* — vlastná fotogaléria s pripravenou grafikou a lightboxom (namiesto core `core/gallery`).

### Core WP bloky (strict 8-block režim)

**Princíp**: čím užšia paleta, tým menšie riziko, že vznikne layout mimo design systému. Aj keď bloky graficky prispôsobíme, viac blokov = viac spôsobov ako kombinovať = väčšia šanca na štýlové anarchie.

| Block | Účel |
|---|---|
| paragraph | bežný text |
| heading | nadpisy h2–h4 |
| image | inline obrázok |
| list | zoznamy |
| button | CTA tlačidlo |
| columns | viacstĺpcové layouty |
| separator | predeľovač |
| quote | citát |

**Vynechané** (a prečo):
- `spacer` — narúša rytmus, design system rieši spacing sám.
- `group` — interná Gutenberg vec, farár ju sám nepotrebuje.
- `file` — na PDF je `farnost-pdf`, iné súbory potrebuje minimum farností.
- `table` — najťažšia na konzistentné štýlovanie; tabuľkové potreby sa dajú nahradiť listom alebo stĺpcami.
- `cover`, `media-text`, `core/gallery`, `embed`, `shortcode`, `html`, `audio`, `video` — buď zbytočná zložitosť, alebo to robí vlastný blok lepšie.

Per-block nastavenia vlastných blokov (počet položiek, výber kostola, …) doriešime v Etape 3 pri samotnom kódovaní — niektoré nastavenia vyjdú najavo až pri stavbe.

## Súhrn otvorených otázok

Žiadne otvorené otázky — všetky body sú rozhodnuté v tomto dokumente alebo odložené:

- **Kalendárny pohľad udalostí** — odložený na **v4** (zaznamenané v [`05-roadmap.md`](05-roadmap.md#etapa-6--mimo-v3-možné-rozšírenia)).
- **Mimoriadny oznam — viditeľnosť v admine pre rýchlu kontrolu** — drobný UX detail, doriešime pri implementácii v Etape 2.
