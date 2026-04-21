# BookLoop — Grāmatu Apmaiņas Platforma

> Skolēna projekts | Izstrādātājs: **Eduards Megnis** | 2026. gads

---

## Par projektu

**BookLoop** ir grāmatu apmaiņas tīmekļa lietotne. Ideja vienkārša — mājās guļ izlasītas grāmatas, kuras kādam citam varētu noderēt. BookLoop ļauj lietotājiem piedāvāt savas grāmatas un apmainīt tās pret citu grāmatām bez maksas.

Rezultātā sanāca pilnvērtīga platforma ar lietotāju kontiem, grāmatu katalogu, apmaiņas sistēmu, iekšējo čatu, vērtēšanas sistēmu, administrācijas paneli un e-pasta paziņojumiem.

---

## Tehnoloģiskais steks

| Slānis | Tehnoloģija |
|--------|-------------|
| **Backend** | Laravel 13 (PHP) |
| **Frontend** | Vue 3 (SPA) |
| **Datubāze** | SQLite |
| **Autentifikācija** | Laravel Sanctum |
| **CSS** | Bootstrap 5 + pielāgota zaļā shēma |
| **Būvēšana** | Vite |
| **PWA** | Web App Manifest + Service Worker |
| **E-pasta testēšana** | Mailtrap (HTTP API sandbox) |
| **Rindas** | Laravel Queue (database driver) |

Laravel apstrādā REST API loģiku, Vue veido lietotāja saskarni un komunicē ar API caur Axios. Lietotnei ir tumšais/gaišais režīms un pilns LV/EN valodu atbalsts.

---

## Funkcionalitāte

### 1. Lietotāju sistēma
- Reģistrācija, pieteikšanās, atteikšanās
- Paroles atjaunošana pa e-pastu
- Profila rediģēšana (vārds, e-pasts, parole)
- Apmaiņu vēsture profilā
- **Stipras paroles:** jābūt lielajiem un mazajiem burtiem, ciparam un simbolam

### 2. Grāmatu bibliotēka
- Pievienot, rediģēt, dzēst grāmatas
- Vāka attēla augšupielāde (vai automātisks krāsu gradients)
- 15 žanri, 3 valodas, 4 fiziskā stāvokļa līmeņi
- Automātisks statuss: Pieejama → Aizturēta → Apmainīta
- **Validācija:** nosaukumam jāsatur vismaz viens burts; autoram — tikai burti, atstarpes un defises (latīņu un citi unicode burti)
- **Auto-formatēšana:** nosaukums un autors automātiski tiek pārformatēts PascalCase stilā (katrs vārds ar lielo burtu)

### 3. Grāmatu pārlūkošana
- Pieejama arī nereģistrētiem lietotājiem
- Meklēšana pēc nosaukuma, autora vai žanra
- Filtri pēc vairākiem žanriem un vairākām valodām vienlaicīgi
- Poga visu filtru notīrīšanai uzreiz
- Kārtošana pēc nosaukuma vai autora (A-Z / Z-A)
- Kartiņu un tabulas skats
- Aizturētās grāmatas redzamas ar sarkanu diagonālu svītru (nevar pieprasīt)
- Lapu numerācija
- URL satur aktīvos filtrus — saite ir kopīgojama

### 3a. Lietotāju meklēšana
- Atsevišķa cilne pārlūkošanas lapā
- Meklēšana pēc lietotāja vārda
- Redzams pieejamo grāmatu skaits, klikšķis atver profilu

### 4. Apmaiņas sistēma
1. Lietotājs atrod grāmatu → izvēlas savu grāmatu apmaiņai → nosūta pieprasījumu
2. Abas grāmatas kļūst "Aizturētas"
3. Īpašnieks apstiprina vai noraida — statusu maiņa notiek **datubāzes transakcijā**
4. Apstiprinot — grāmata digitāli pāriet jaunajam īpašniekam
5. Ja viena apmaiņa apstiprināta, pārējie pieprasījumi uz to pašu grāmatu automātiski noraidās

### 5. Vērtēšanas sistēma
- 1–5 zvaigžņu vērtējums + neobligāta atsauksme pēc apmaiņas
- Vidējais vērtējums redzams grāmatu kartēs

### 6. Ziņojumu sistēma
- Iekšējais čats starp lietotājiem
- Automātiska jaunu ziņojumu pārbaude ik 8 sekundes
- Nelasīto ziņojumu skaitītājs navigācijā

### 7. Paziņojumu sistēma
- Paziņojumi par apmaiņas lēmumiem un jauniem ziņojumiem
- Paziņojumu zvans ar sarkanu skaitītāju
- **E-pasta paziņojumi:** katrs paziņojums tiek nosūtīts arī uz lietotāja e-pastu (sk. sadaļu 11)

### 8. Lietotāju bloķēšana
- Lietotājs var bloķēt citu lietotāju no viņa profila vai čata
- Bloķētie lietotāji nevar sūtīt ziņojumus vai pieprasīt apmaiņu
- Filtrēšana darbojas abos virzienos — arī ja otrs bloķē tevi
- Bloķēto saraksts redzams iestatījumu lapā, var atbloķēt jebkurā brīdī

### 9. Iestatījumu lapa
- Vārda, e-pasta un paroles maiņa
- Privātuma iestatījumi (vai rādīt pievienošanās datumu un apmaiņu skaitu)
- Bloķēto lietotāju pārvaldība

### 10. Administrācijas panelis
- Pieejams tikai lietotājiem ar `is_admin = true`
- Aizsargāts ar `AdminMiddleware` — ne-admini saņem 403
- Nav tikai grāmatu dzēšana — admins var darīt **visu ko parasts lietotājs, un vairāk**

**Lietotāji:**
- Redzami visi reģistrētie lietotāji (ID, vārds, e-pasts, reģ. datums, statuss)
- Bloķēt / atbloķēt jebkuru lietotāju (bloķēts nevar pieteikties)
- Piešķirt / atņemt admin tiesības

**Grāmatas:**
- Redzamas visas grāmatas ar statusu un īpašnieku
- Dzēst jebkuru grāmatu

**Apmaiņu pieprasījumi:**
- Redzami visi pieprasījumi ar statusu
- Apstiprināt vai noraidīt jebkuru pieprasījumu
- Dzēst pieprasījumu

**Vērtējumi:**
- Redzami visi vērtējumi — zvaigznes, teksts, autors, grāmata

**Valodu pārvaldība:**
- Pievienot jaunas valodas, aktivizēt/deaktivizēt esošās
- Rediģēt jebkuru tulkojuma atslēgu no admin paneļa

**Audita žurnāls:**
- Katrs admin lēmums tiek ierakstīts `admin_logs` tabulā
- Redzams: darbība, mērķis, iemesls, laiks

Panelis ir sakārtots saliekamās/izlokāmās sadaļās. Pilns LV/EN tulkojums, tumšā režīma atbalsts.

### 11. E-pasta paziņojumi
- **Mailtrap sandbox** — e-pasti tiek pārtverti testēšanas vidē, nevis sūtīti īstiem adresātiem
- **Laravel Queue** — e-pasti tiek nosūtīti asinhroni, lai pieprasījums atgrieztos uzreiz
- Seši paziņojumu veidi:
  - `SwapAccepted` — apmaiņa apstiprināta
  - `SwapDeclined` — apmaiņa noraidīta
  - `NewMessage` — saņemts jauns ziņojums čatā
  - `BookUnderReview` — admins atzīmēja grāmatu pārskatīšanai
  - `BookDeletedByAdmin` — admins dzēsa grāmatu (ar iemeslu)
  - `LoginLocked` — 5 neveiksmīgi pieteikšanās mēģinājumi, konts bloķēts 15 min

### 12. Atbalsta biļešu sistēma
- Lietotāji var iesniegt atbalsta biļetes ar tēmu un aprakstu
- Sarunu veidols — lietotājs un admins var atbildēt uz biļeti
- Admins var slēgt biļetes
- Slēgtas biļetes var atkal atvērt atbildot uz tām

### 13. Privātuma politika
- Atsevišķa lapa pieejama no kājenes
- Aprakstīta konta dzēšanas un bloķēšanas politika
- Pilns LV/EN tulkojums

---

## Datubāzes struktūra

```
users                   — lietotāju konti (is_admin, is_blocked lauki)
books                   — grāmatu katalogs
swap_requests           — apmaiņas pieprasījumi
messages                — ziņojumi starp lietotājiem
notifications           — sistēmas paziņojumi
ratings                 — grāmatu vērtējumi pēc apmaiņas
blocks                  — bloķēto lietotāju saraksts
complaints              — atbalsta biļetes
complaint_messages      — ziņas biļešu iekšienē
admin_logs              — admina darbību audita pieraksts
languages               — valodu reģistrs (en, lv)
translation_overrides   — admin rediģējami tulkojumi datubāzē
jobs                    — Laravel Queue rinda (asinhronie uzdevumi)
failed_jobs             — neizdevušies rindas uzdevumi
personal_access_tokens  — Sanctum tokeni
```

---

## Pieejamība (WCAG 2.1 AA)

- Visi modālie logi ar `role="dialog"` un `aria-modal`
- Kļūdu paziņojumi ar `role="alert"`
- Ziņojumu saraksts ar `role="log"` un `aria-live`
- Ikonas pogas ar `aria-label` un `aria-pressed` stāvoklis pārslēgšanas pogām
- Klaviatūras navigācija uz visiem klikšķināmiem elementiem
- **Visi formu lauki saistīti ar `<label for="">` atribūtiem** — ekrānlasītāji pareizi identificē katru lauku
- **Skip-to-main navigācija** — redzama ar Tab taustiņu, ļauj ekrānlasītāju lietotājiem apiet navigāciju

---

## PWA

Lietotne ir instalējama kā progresīvā tīmekļa lietotne (PWA):
- `manifest.json` ar ikonām un tēmas krāsu
- Service Worker kešo statiskos resursus (network-first stratēģija)
- API pieprasījumi nekad netiek kešoti

---

## Drošība

- Paroles saglabātas kā bcrypt hash
- **Stipras paroles:** minimums 8 simboli, lielie + mazie burti, cipars, speciālais simbols
- API aizsargāts ar Sanctum tokeniem
- Ātruma ierobežojumi pieteikšanās un reģistrācijas maršrutiem
- Piederības pārbaudes — lietotājs var mainīt tikai savus datus
- Ievades validācija visos API galapunktos
- Transakcijas apmaiņas operācijām
- Admin maršruti aizsargāti ar `AdminMiddleware`
- Bloķēti lietotāji nevar pieteikties sistēmā
- **HTTP drošības galvenes** — `SecurityHeaders` middleware visās atbildēs: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`

---

## Projekta struktūra

```
app/Http/Controllers/   ← 11 API kontrolieri (ieskaitot AdminController)
app/Http/Middleware/    ← AdminMiddleware, SecurityHeaders
app/Models/             ← User, Book, SwapRequest, Message, Rating, Block
app/Notifications/      ← SwapAccepted, SwapDeclined, NewMessage, BookUnderReview, BookDeletedByAdmin
resources/js/
  components/           ← 18+ Vue komponentes (ieskaitot Admin.vue, PrivacyPolicy.vue)
  router/router.js      ← Vue Router (ar admin maršrutu un sardzi)
  translations.js       ← visi UI teksti LV/EN (~200+ atslēgas)
  authStore.js / langStore.js / themeStore.js
routes/api.php          ← ~45 REST API galapunkti
```

---

## Kā palaist

**Pirmo reizi:**
```bash
composer install
php artisan storage:link
npm install
```

> `.env` fails ar visiem iestatījumiem ir iekļauts repozitorijā — nekas papildus nav jākonfigurē.

**Palaist izstrādes vidi:**
```bash
php artisan ser
composer run dev
```

**Testi:**
```bash
composer run test
```

> Datubāze ar demo datiem ir iekļauta repozitorijā — migrācijas palaist nav nepieciešams.

---

## Ko es iemācījos

- REST API projektēšana ar Laravel
- Vue 3 SPA izveide ar Vue Router un reaktīvo stāvokli
- Tokenu autentifikācija ar Sanctum
- Datubāzes relāciju modelēšana un transakcijas
- Droša koda rakstīšana (validācija, autorizācija, rate limiting)
- Pilna stack izstrāde — no migrācijām līdz pogas krāsai ekrānā
- PWA izveide ar Service Worker un Web App Manifest
- Pieejamības standarti (WCAG 2.1 AA)
- **Asinhrona uzdevumu apstrāde ar Laravel Queue**
- **E-pasta sūtīšana caur Mailtrap HTTP API**
- **Admin paneļa izveide ar lomu pārvaldību**

---

*"Grāmatai pienākas jauns lasītājs, ne putekļi uz plaukta."*
