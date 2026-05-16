# Obsah a roly

Tento dokument popisuje **kto bude s aplikáciou pracovať**, **aké práva má** a **ako vzniká a publikuje sa obsah**.

> Roly a workflow sú identické **pre každú inštaláciu farnosti** — plugin ich registruje rovnako. Per-farnosť sa líšia len konkrétne osoby v týchto rolách.

## Roly

Plugin pri aktivácii nakonfiguruje natívne WP role + jednu vlastnú.

| Rola              | WP rola            | Kto                          | Čo môže                                                                          |
| ----------------- | ------------------ | ---------------------------- | -------------------------------------------------------------------------------- |
| **Administrator** | `administrator`    | Vývojár / Digitalka          | Všetko — inštalácia/update pluginov a témy, používatelia, settings, štruktúra.   |
| **Editor**        | `editor`           | Farár, kaplán                | Vytvárať/upravovať/mazať všetky CPT (`oznam`, `kostol`, `omsa_vynimka`, `umysel`), WP posty (udalosti), stránky, kategórie. |
| **Asistent**      | `farnost_asistent`  | Kostolník, sekretár, dobrovoľník | Vytvárať/upravovať len **`umysel`** a vlastné **`post`** (udalosti). Nemení rozpis omší, kostoly, settings ani statické stránky. |
| **Subscriber**    | `subscriber`       | Nepoužívame                  | —                                                                                |

> **Pozn.**: Vo v3 nemáme verejnú registráciu. Účty zakladá Administrator manuálne. `users_can_register` = `false`.

### Capabilities rolly Asistent

Rolu `farnost_asistent` vytvára plugin pri aktivácii cez `add_role()`. Jej capabilities sú:

```
read                          (default)
upload_files
edit_posts                    (vlastné posty — udalosti)
edit_published_posts
publish_posts
delete_posts
edit_umysly
edit_published_umysly
publish_umysly
delete_umysly
```

`umysly` capability sú vlastné, registrované cez `capability_type` v `register_post_type` pre CPT `umysel`. Editor a Administrator ich dostávajú cez `map_meta_cap` filter.

## Onboarding novej farnosti

Po nasadení Bedrock inštalácie a aktivácii pluginu + témy:

1. **Plugin spustí setup wizard** (admin notice → klik → wizard) s 5 krokmi:
   1. **Identita farnosti** — názov, patrocínium, dekanát, diecéza.
   2. **Kontakt** — adresa, telefón, e‑mail, úradné hodiny.
   3. **Prvý kostol** — názov, adresa, prvý draft pravidelného rozpisu (môže byť prázdny, doplní sa neskôr).
   4. **Vyber dizajn** — výber z 5–10 vizuálnych variantov (style variations). Wizard ukáže náhľadové screenshoty, farár klikne na preferovaný a wizard aktivuje zvolenú variant v Site Editore. Voľba sa dá kedykoľvek zmeniť cez `Vzhľad → Editor → Štýly`.
   5. **Logo a farby** — logo, vlastný primary color (override style variation, voliteľné).
2. Wizard vytvorí **statické stránky** s predvyplneným obsahom: Domov, Kontakt, O farnosti, Sviatosti.
3. Wizard vytvorí **default kategórie**: `Udalosti`, `Zo života farnosti`, `Pozvánky` (každá s vlastnou farbou).
4. Wizard nastaví **homepage** ako statickú stránku „Domov" (`reading → A static page`).
5. Wizard vytvorí **prvý účet farára** (rola Editor) — administrátor mu pošle login email cez wizard.

Po dokončení wizardu je inštalácia použiteľná. Farár sa môže prihlásiť a začať publikovať.

## Workflow publikovania

### Týždenný oznam

1. Farár (Editor) v admine `Oznamy → Pridať nový`.
2. Napíše obsah v Gutenberg editore. V sidebare „Detail oznamu":
   - `Platnosť od` — typicky nadchádzajúca nedeľa.
   - `Platnosť do` — nasledujúca sobota.
   - `Pripnutý` — len pre mimoriadne oznamy.
3. Pridá featured image (odporúča sa kvôli OG náhľadu pri zdieľaní).
4. **Publikovať** (alebo naplánovať cez „Publish later").
5. Najnovší publikovaný oznam sa automaticky objaví v hlavnom feede na úvodnej stránke a v sekcii „Najnovší oznam".

### Mimoriadny oznam (pripnutý)

Identicky ako týždenný, ale zaškrtne `Pripnutý`. Pripnutý oznam sa vo feede zobrazuje navrchu, kým sa flag nezruší alebo neuplynie `Platnosť do`.

### Udalosť / pozvánka (WP post)

1. Farár (alebo Asistent) `Príspevky → Pridať nový`.
2. Napíše obsah v Gutenberg editore. V sidebare „Detail udalosti":
   - **Kedy** — dátum a čas (voliteľné).
   - **Kde** — voľný text miesta (voliteľné).
3. V sidebare „Kategórie" zvolí jednu alebo viac kategórií (default: `Udalosti`, `Zo života farnosti`, `Pozvánky`).
4. Pridá featured image (odporúča sa).
5. **Publikovať**.
6. Post sa zobrazí v archíve kategórie aj v kombinovanom feede na úvodnej stránke.

### Pravidelný rozpis sv. omší

Jednorázová akcia pri spustení (a potom občasné úpravy):

1. Administrator alebo Editor `Kostoly → Pridať nový` pre každý kostol farnosti.
2. Vyplní názov, adresu, GPS (voliteľne), nastaví `Hlavný kostol` pre farský kostol.
3. V sidebare „Pravidelný rozpis omší" pridá po jednom riadku pre každú pravidelnú omšu: deň, čas, typ, prípadne `platnost_od/do` pre sezónne omše (krížová cesta v Pôste).
4. **Aktualizovať**.

Sezónne zmeny (napr. letný rozpis) sa nerobia výnimkami pre každý deň — robia sa `platnost_od/do` priamo v riadkoch rozpisu.

### Výnimka rozpisu (sviatok, pohreb, prikázaný sviatok)

1. Farár (Editor) `Sv. omše → Výnimky → Pridať novú`.
2. Vyplní:
   - **Dátum** — kedy.
   - **Kostol** — ktorý.
   - **Režim**:
     - `Náhrada` — mení čas / typ existujúcej omše. Vyplň „Nahrádza čas" + nový čas.
     - `Pridaná` — omša navyše, mimo pravidelný rozpis. Vyplň „Čas".
     - `Zrušená` — omša odpadá. Vyplň „Nahrádza čas" (ktorá z pravidelných odpadá).
   - **Poznámka** — voľný text (napr. „pohreb †Jana M.").
3. **Publikovať**.

### Úmysel sv. omše

1. Asistent / Editor `Úmysly → Pridať nový`.
2. Vyplní:
   - **Dátum**, **Čas**, **Kostol** — slot omše.
   - **Text úmyslu** — krátko (1–3 riadky).
   - **Anonymný** — ak farník nechce zverejniť mená; frontend zobrazí „súkromný úmysel".
3. **Publikovať**.

Úmysel sa automaticky zobrazí v rozpise omší pri správnom slote a v zozname úmyslov.

## Spravovanie kategórií

`Príspevky → Kategórie`:

- Štandardné WP polia (názov, slug, popis, parent).
- **Farba** (`farnost_color`) — color picker; použije sa na badge vo feede a accent v archíve.
- **Zobraziť v menu** (`farnost_show_in_menu`) — checkbox, default zapnutý. Ak vypnutý, kategória sa nezobrazí v automatickom hlavnom menu (ale stále funguje pre triedenie a archív).

## Spravovanie settings farnosti

`Farnosť → Nastavenia`:

Sekcie (zodpovedajú [`02-datovy-model.md` § Per-farnosť settings](02-datovy-model.md#per-farnosť-settings)):

- **Identita** — názov, patrocínium, dekanát, diecéza, rok založenia.
- **Kontakt** — adresa, telefón, e‑mail, web, úradné hodiny.
- **Financie** — IBAN, 2 %, IČO.
- **Sociálne siete** — Facebook, YouTube, Instagram.
- **Logo a farby** — logo (media library), primárna farba.

Editovať môže len Administrator. Po uložení sa zmeny premietnu do päty, hlavičky, OG metadata a blokov, ktoré settings konzumujú.

## Pravidlá obsahu

### Citlivé údaje

**Ochrana osobných údajov** — texty na verejnom webe sú indexované Googlom a archivované na internete. Platí:

- **Mená v úmysloch**: nepublikovať plné mená a adresy zosnulých/živých bez súhlasu rodiny. Bezpečné formy:
  - „†Jana M. a rodičia" (iniciály priezviska)
  - „za zdravie členov rodiny"
  - „súkromný úmysel" (zaškrtnutý `anonymny`)
- **Fotografie z farských akcií**: pri deťoch len so súhlasom rodičov. Pri verejných akciách (procesia, omša) postačuje všeobecné upovedomenie.
- **Mená v oznamoch a udalostiach**: pri pohreboch — iniciály alebo aspoň „rodina X". Pri svadbách — iba so súhlasom snúbencov.

### Štýl

- Krátke, jasné vety. Bez cirkevného žargónu, kde sa dá.
- Dátumy v slovenskom formáte: „21. nedeľa v cezročnom období", „streda 14. mája 2026".
- Časy v 24-hodinovom formáte `HH:MM` (napr. `18:30`).
- Pri zdieľaní textu z PDF / oznamoch z minulého roka — vyčistiť od starých dátumov a interných poznámok.

## Workflow technický

- **Drafty**: oznam môže byť `draft`, kým nie je hotový. Asistent vidí len svoje drafty, Editor všetky.
- **Plánované publikovanie**: `Publish on` v sidebari editora. WP cron sa o publikovanie postará. Pre istotu odporúčame **server cron** namiesto `wp-cron.php` (`DISABLE_WP_CRON = true` v `config/application.php` + cron job na `/wp-cron.php`).
- **Revisions**: WP natívne uchováva revízie. Pri citlivých textoch (úmysly) je možné vrátiť sa k predchádzajúcej verzii.
- **Médiá**: Featured image ideálne 1200×630 px alebo väčšie (kvôli OG). Súbory > 2 MB WP automaticky preškáluje, ale **nahrávať čo najmenšiu rozumnú veľkosť**.
- **Auto-save**: Gutenberg auto-save každých 60 s; pri zatvorení browseru sa draft uloží.

## Onboarding nového Editora — checklist

Pre farára pri prvom prihlásení (samostatná stránka v admine `Pomoc → Návod`, vytvorená setup wizardom):

1. Ako sa prihlásiť na `/wp/wp-admin` (alebo skrátený `/login`).
2. **Pridať prvý oznam** — 5 krokov so screenshotmi.
3. **Pridať úmysel** — 3 kroky.
4. **Pridať výnimku omše** pri sviatku — 4 kroky.
5. **Pridať udalosť / pozvánku** — 3 kroky.
6. **Zmeniť settings farnosti** — kedy a ako.
7. Komu volať, keď niečo nefunguje (kontakt na Digitalku).

Tento návod nie je súčasťou kódu pluginu — je to jednoduchá WP stránka. Šablónu zaznamenáme do dokumentácie (`doc/onboarding-template.md` — vytvorí sa v rámci [Etapy 3](05-roadmap.md)).
