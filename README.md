# BookLoop - Grāmatu Apmaiņas Platforma

> Skolēna projekts | Izstrādātājs: **Eduards Megnis** | 2026. gads

---

## Par projektu

**BookLoop** ir grāmatu apmaiņas tīmekļa lietotne. Ideja vienkārša - mājās guļ izlasītas grāmatas, kuras kādam citam varētu noderēt. BookLoop ļauj lietotājiem piedāvāt savas grāmatas un apmainīt tās pret citu grāmatām bez maksas.

Rezultātā sanāca pilnvērtīga platforma ar lietotāju kontiem, grāmatu katalogu, apmaiņas sistēmu, iekšējo čatu, vērtēšanas sistēmu, karti, divpakāpju pieteikšanos, administrācijas paneli un e-pasta paziņojumiem.

---

## Tehnoloģiskais steks

| Slānis | Tehnoloģija |
|--------|-------------|
| **Backend** | Laravel 13 (PHP 8.4+) |
| **Frontend** | Vue 3 (SPA) + Vue Router |
| **Datubāze** | SQLite |
| **Autentifikācija** | Laravel Sanctum (tokeni) |
| **CSS** | Bootstrap 5 + pielāgota zaļā shēma |
| **Karte** | Leaflet (MapTiler vai OpenStreetMap) |
| **Būvēšana** | Vite |
| **PWA** | Web App Manifest + Service Worker |
| **E-pasts** | Mailtrap (HTTP API sandbox) |
| **Rindas** | Laravel Queue |
| **Testi** | PHPUnit + Playwright |

Laravel apstrādā REST API loģiku, Vue veido lietotāja saskarni un komunicē ar API caur Axios. Lietotnei ir tumšais/gaišais režīms un pilns LV/EN valodu atbalsts (admins var pievienot arī citas valodas).

---

## Funkcionalitāte

### 1. Lietotāju sistēma
- Reģistrācija, pieteikšanās, atteikšanās
- Paroles atjaunošana: ievadot e-pastu, atjaunošanas saite tiek parādīta uzreiz uz ekrāna (e-pasts netiek sūtīts)
- Profila rediģēšana (vārds, e-pasts, parole)
- Apmaiņu vēsture profilā (ar iespēju novērtēt saņemto grāmatu)
- **Stipras paroles:** vismaz 8 simboli, lielie un mazie burti, cipars un simbols
- **Konta dzēšana:** jāievada parole, lietotājs tiek anonimizēts ("Deleted user"), viņa grāmatas paslēptas, gaidošās apmaiņas noraidītas un otrs lietotājs saņem paziņojumu. Apmaiņu vēsture un vērtējumi paliek. Vienīgais admins kontu dzēst nevar, adminam jānorāda iemesls

### 2. Divpakāpju pieteikšanās (2FA)
- Ieslēdzama iestatījumos, pēc reģistrācijas vai pirmā login lietotājam to piedāvā
- Pēc paroles uz e-pastu tiek nosūtīts 6 ciparu kods, derīgs 10 minūtes
- Pēc 5 nepareiziem kodiem jāsāk no jauna, kodu var nosūtīt atkārtoti
- "Atcerēties ierīci" - šajā pārlūkā kods 10 minūtes netiek prasīts
- Izslēgšanai jāievada parole

### 3. Grāmatu bibliotēka
- Pievienot, rediģēt, dzēst grāmatas (grāmatu, kas ir gaidošā apmaiņā, dzēst nevar)
- Vāka attēla augšupielāde (vai automātisks krāsu gradients). Bilde pārlūkā tiek samazināta un pārveidota par JPEG, tāpēc pazūd arī GPS dati
- Ieteikumi rakstot - piedāvā jau esošos nosaukumus un autorus, grāmatu, kas pašam jau ir, izvēlēties nevar
- 15 žanri, 3 valodas (latviešu, angļu, krievu), 4 fiziskā stāvokļa līmeņi
- Statusi: Pieejama → Aizturēta (gaida apmaiņu) → pēc apmaiņas atkal Pieejama jaunajam īpašniekam. Admins var atzīmēt grāmatu "Pārskatīšanā"
- **Validācija:** nosaukumam vismaz 2 simboli un jāsatur vismaz viens burts; autoram tikai burti, atstarpes, defises, apostrofi un punkti
- **Auto-formatēšana:** nosaukumam un autoram katrs vārds ar lielo burtu un atstarpes tiek izņemtas (piem. "harry potter" → "HarryPotter"), lai vienādas grāmatas sakristu
- Vienam lietotājam nevar būt divas grāmatas ar vienādu nosaukumu un autoru

### 4. Grāmatu pārlūkošana
- Pieejama arī nereģistrētiem lietotājiem
- Vienāda grāmata (nosaukums + autors) pie vairākiem lietotājiem ir viena kartīte ar eksemplāru skaitu. Atverot to, redzami visi īpašnieki un vērtējumi kopā
- Redzamas tikai pieejamās grāmatas, bez savām un bez tām, kas pašam jau ir
- Meklēšana pēc nosaukuma, autora vai žanra
- Filtri pēc vairākiem žanriem un vairākām valodām vienlaicīgi, filtru tagi un poga visu notīrīšanai
- Kārtošana: nosaukums vai autors (A-Z / Z-A), jaunākās, populārākās, labāk novērtētās, tuvākās
- Filtrs pēc attāluma (ja lietotājam ir norādīta atrašanās vieta)
- Kartiņu, tabulas un kartes skats
- Grāmatas logā ir līdzīgu grāmatu ieteikumi (tas pats žanrs vai valoda)
- Lapu numerācija
- URL satur aktīvos filtrus - saite ir kopīgojama

### 4a. Lietotāju meklēšana
- Atsevišķa cilne pārlūkošanas lapā
- Meklēšana pēc lietotāja vārda, kārtošana pēc tuvuma un filtrs pēc attāluma
- Redzams pieejamo grāmatu skaits un dažu grāmatu priekšskatījums, klikšķis atver profilu

### 5. Atrašanās vieta un karte
- Iestatījumos lietotājs var atzīmēt savu aptuveno vietu kartē, tā tiek noapaļota līdz ~1 km (precīza adrese netiek glabāta)
- Pie grāmatām un lietotājiem redzams aptuvens attālums
- Kopējā karte sākumlapā un pārlūkošanā rāda pieejamās grāmatas pa ~5 km reģioniem, bez lietotāju vārdiem
- Lietotājs var savas grāmatas kartē paslēpt
- Ja `.env` ir `VITE_MAPTILER_KEY`, karte izmanto MapTiler, citādi OpenStreetMap

### 6. Apmaiņas sistēma
1. Lietotājs atrod grāmatu → izvēlas savu grāmatu apmaiņai → nosūta pieprasījumu
2. Abas grāmatas kļūst "Aizturētas"
3. Īpašnieks apstiprina vai noraida - statusu maiņa notiek **datubāzes transakcijā**
4. Apstiprinot grāmatas digitāli pāriet otram īpašniekam
5. Ja viena apmaiņa apstiprināta, pārējie gaidošie pieprasījumi uz tām pašām grāmatām automātiski noraidās un citas iesaistītās grāmatas atkal kļūst pieejamas
6. Pieprasītājs var savu pieprasījumu atcelt

### 7. Vērtēšanas sistēma
- 1-5 zvaigžņu vērtējums + neobligāta atsauksme par saņemto grāmatu pēc apmaiņas
- Vidējais vērtējums redzams grāmatu kartītēs (visu eksemplāru vērtējumi kopā)

### 8. Ziņojumu sistēma
- Iekšējais čats starp lietotājiem
- Var sūtīt bildes (JPG, PNG, WEBP līdz 5 MB, arī ar Ctrl+V). Bildes glabājas privāti un atveras tikai ar īslaicīgu parakstītu saiti
- Nelasītu ziņu var rediģēt vai dzēst (labais klikšķis vai garš pieskāriens)
- Jaunu ziņojumu pārbaude ik 3 sekundes
- Nelasīto ziņojumu skaitītājs paneļa navigācijā
- Atbalsta saruna vienmēr redzama sarakstā pirmā

### 9. Paziņojumu sistēma
- Paziņojumi par apmaiņas lēmumiem, jauniem ziņojumiem un admina darbībām ar grāmatām
- Paziņojumu zvans ar sarkanu skaitītāju, var atzīmēt kā izlasītus
- **E-pasta paziņojumi:** lielākā daļa paziņojumu tiek nosūtīti arī uz e-pastu (sk. sadaļu 15)

### 10. Lietotāju bloķēšana
- Lietotājs var bloķēt citu lietotāju no viņa profila vai čata
- Bloķētie lietotāji nevar sūtīt ziņojumus vai pieprasīt apmaiņu
- Filtrēšana darbojas abos virzienos - arī ja otrs bloķē tevi
- Bloķēto saraksts redzams iestatījumu lapā, var atbloķēt jebkurā brīdī

### 11. Iestatījumu lapa
- Vārda, e-pasta un paroles maiņa
- Privātuma iestatījumi: vai rādīt pievienošanās datumu, apmaiņu skaitu, apmaiņu vēsturi un grāmatas kartē
- Divpakāpju pieteikšanās ieslēgšana/izslēgšana
- Atrašanās vietas izvēle kartē
- Bloķēto lietotāju pārvaldība
- Konta dzēšana

### 12. Administrācijas panelis
- Pieejams tikai lietotājiem ar `is_admin = true`, aizsargāts ar `AdminMiddleware`
- **Atsevišķa admin parole:** pēc parastā login admins ievada vēl admin paroli (vismaz 12 simboli). Panelis paliek atvērts 8 stundas konkrētajā sesijā, pārejot uz profilu tas aizveras. 5 nepareizi mēģinājumi = bloķēts uz 15 min
- Pirmo admin paroli uzstāda ar komandu `php artisan admin:password <e-pasts>`
- Katrā cilnē ir meklēšana, filtri, kārtošana un lapošana. Telefonā tabulas rindā redzams tikai galvenais, pārējais pēc "Skatīt vairāk"

**Lietotāji:**
- Redzami visi lietotāji (ID, vārds, e-pasts, grāmatu skaits, reģ. datums, statuss)
- Bloķēt / atbloķēt lietotāju (bloķēts nevar pieteikties, viņa grāmatas pazūd no kataloga). Adminus un sevi bloķēt nevar
- Piešķirt admin tiesības (ar pagaidu paroli, kas jaunajam adminam jānomaina pirmajā ieejā) un atņemt tās
- Lietotāja aptuvenā vieta kartē

**Grāmatas:**
- Redzamas visas grāmatas ar statusu un īpašnieku
- Atzīmēt grāmatu pārskatīšanai (ar iemeslu) un noņemt atzīmi
- Dzēst jebkuru grāmatu (ar iemeslu, īpašnieks saņem paziņojumu)

**Apmaiņu pieprasījumi:**
- Redzami visi pieprasījumi ar statusu
- Apstiprināt, noraidīt vai dzēst jebkuru pieprasījumu

**Vērtējumi:**
- Redzami visi vērtējumi - zvaigznes, teksts, autors, grāmata

**Karte:**
- Visi lietotāji ar norādītu atrašanās vietu vienā kartē

**Atbalsts:**
- Visas lietotāju atbalsta biļetes, atbildēšana un slēgšana

**Valodu pārvaldība:**
- Pievienot jaunas valodas, rediģēt nosaukumu un karogu, deaktivizēt/aktivizēt (angļu valodu noņemt nevar)
- Rediģēt jebkuru tulkojuma atslēgu no admin paneļa (labojumi glabājas datubāzē)

**Audita žurnāls:**
- Katrs admin lēmums tiek ierakstīts `admin_logs` tabulā, arī neveiksmīgi admin paroles mēģinājumi
- Redzams: admins, darbība, mērķis, iemesls, laiks

Pilns LV/EN tulkojums, tumšā režīma atbalsts.

### 13. Atbalsta biļešu sistēma
- Lietotāji var iesniegt atbalsta biļetes ar tēmu un aprakstu
- Sarunu veidols - lietotājs un admins var atbildēt uz biļeti, jaunas ziņas pārbauda ik 4 sekundes
- Admins var slēgt biļetes, lietotājs slēgtai biļetei atbildēt nevar
- Admina atbilde slēgtu biļeti atver atpakaļ

### 14. Privātuma politika
- Atsevišķa lapa pieejama no kājenes
- Aprakstīta konta dzēšanas un bloķēšanas politika
- Pilns LV/EN tulkojums

### 15. E-pasta paziņojumi
- **Mailtrap sandbox** - e-pasti tiek pārtverti testēšanas vidē, nevis sūtīti īstiem adresātiem
- **Laravel Queue** - `NewMessage`, `SwapAccepted` un `SwapDeclined` ir rindā liekami. Pašlaik `.env` ir `QUEUE_CONNECTION=sync`, tāpēc tie tiek nosūtīti uzreiz
- Paziņojumu veidi:
  - `SwapAccepted` - apmaiņa apstiprināta (paziņojums + e-pasts)
  - `SwapDeclined` - apmaiņa noraidīta (paziņojums + e-pasts)
  - `NewMessage` - jauns ziņojums čatā, sūta tikai vienreiz, kamēr iepriekšējā ziņa nav izlasīta (paziņojums + e-pasts)
  - `BookUnderReview` - admins atzīmēja grāmatu pārskatīšanai (paziņojums + e-pasts)
  - `BookDeletedByAdmin` - admins dzēsa grāmatu, ar iemeslu (paziņojums + e-pasts)
  - `SwapUnavailable` - otrs lietotājs izdzēsa kontu, apmaiņa vairs nav iespējama (tikai paziņojums)
  - `LoginLocked` - 5 neveiksmīgi pieteikšanās mēģinājumi, konts bloķēts 15 min (tikai e-pasts)
  - `TwoFactorCode` - 2FA kods (tikai e-pasts)

---

## Datubāzes struktūra

```
users                   - lietotāju konti (is_admin, is_blocked, admin_password, atrašanās vieta, 2FA)
books                   - grāmatu katalogs
swap_requests           - apmaiņas pieprasījumi
messages                - ziņojumi starp lietotājiem (arī bildes)
notifications           - sistēmas paziņojumi
ratings                 - grāmatu vērtējumi pēc apmaiņas
blocks                  - bloķēto lietotāju saraksts
complaints              - atbalsta biļetes
complaint_messages      - ziņas biļešu iekšienē
admin_logs              - admina darbību audita pieraksts
languages               - valodu reģistrs (sākumā en, lv)
translation_overrides   - admin rediģējami tulkojumi datubāzē
two_factor_challenges   - nosūtītie 2FA kodi (glabāti kā hash)
trusted_devices         - atcerētās ierīces 2FA
personal_access_tokens  - Sanctum tokeni (arī admin_until)
password_reset_tokens   - paroles atjaunošanas tokeni
sessions                - sesijas
cache, cache_locks      - kešs (arī login mēģinājumu skaitītāji)
jobs, job_batches       - Laravel Queue rinda
failed_jobs             - neizdevušies rindas uzdevumi
```

---

## Pieejamība (WCAG 2.1 AA)

- Galvenie modālie logi (grāmatas detaļas, grāmatas pievienošana/rediģēšana, dzēšana, apmaiņa) ar `role="dialog"` un `aria-modal`
- Kļūdu paziņojumi ar `role="alert"`
- Ziņojumu saraksts ar `role="log"` un `aria-live`
- Ikonas pogas ar `aria-label` un `aria-pressed` stāvoklis pārslēgšanas pogām
- Klaviatūras navigācija uz klikšķināmiem elementiem
- Pieteikšanās, reģistrācijas, paroles un iestatījumu formās lauki saistīti ar `<label for="">`
- **Skip-to-main navigācija** - redzama ar Tab taustiņu, ļauj ekrānlasītāju lietotājiem apiet navigāciju

---

## PWA

Lietotne ir instalējama kā progresīvā tīmekļa lietotne (PWA):
- `manifest.json` ar ikonām un tēmas krāsu
- Service Worker kešo savus failus (network-first stratēģija), bez tīkla atver sākumlapu no keša
- API pieprasījumi un parakstītās bilžu saites nekad netiek kešotas

---

## Drošība

- Paroles saglabātas kā bcrypt hash (arī admin parole)
- **Stipras paroles:** minimums 8 simboli, lielie + mazie burti, cipars, speciālais simbols
- API aizsargāts ar Sanctum tokeniem
- Divpakāpju pieteikšanās ar e-pasta kodu
- Pēc 5 nepareizām parolēm pieteikšanās bloķēta uz 15 min un lietotājs saņem e-pastu
- Ātruma ierobežojumi pieteikšanās, reģistrācijas, ziņu, apmaiņu un citiem maršrutiem
- Piederības pārbaudes - lietotājs var mainīt tikai savus datus
- Ievades validācija visos API galapunktos
- Transakcijas apmaiņas operācijām
- Admin maršruti aizsargāti ar `AdminMiddleware` un atsevišķu admin paroli
- Bloķēti lietotāji nevar pieteikties sistēmā
- Atrašanās vieta noapaļota, čata bildes privātas
- **HTTP drošības galvenes** - `SecurityHeaders` middleware visās atbildēs: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`

---

## Projekta struktūra

```
app/Http/Controllers/   ← 14 API kontrolieri (ieskaitot AdminController, AdminAuthController, TwoFactorController)
app/Http/Middleware/    ← AdminMiddleware, SecurityHeaders
app/Models/             ← 11 modeļi (User, Book, SwapRequest, Message, Rating, Block, Complaint, ComplaintMessage, AdminLog, Language, TranslationOverride)
app/Notifications/      ← 8 paziņojumi (sk. sadaļu 15)
app/Support/            ← TwoFactor (2FA loģika), Distance (attāluma aprēķins)
resources/js/
  components/           ← 31 Vue komponente (ieskaitot Admin.vue, BooksMap.vue, Messages.vue)
  router/router.js      ← Vue Router (ar admin maršrutu un sardzi)
  translations.js       ← visi UI teksti LV/EN (~590 atslēgas katrai valodai)
  authStore.js / langStore.js / themeStore.js
routes/api.php          ← ~90 REST API galapunkti
tests/Feature/          ← PHPUnit testi
tests/e2e/              ← Playwright testi
```

---

## Kā palaist

Vajag **PHP 8.4+** (ar `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `curl`, `zip`), **Composer** un **Node.js**.

**Pirmo reizi:**
```bash
composer install
npm install
```

> `.env` fails ir iekļauts repozitorijā un jau norāda uz datubāzi `bookloop` projekta mapē. Ja vajag īstus e-pastus, `.env` jāieliek savs `MAILTRAP_API_KEY` un `MAILTRAP_INBOX_ID`.

**Palaist izstrādes vidi:**
```bash
composer run dev
```
Tas palaiž reizē Laravel serveri, rindu un Vite. Lapa atveras **http://localhost:8000**.

**Testi:**
```bash
composer run test       # PHPUnit
npm run test:e2e        # Playwright (izmanto atsevišķu datubāzi database/e2e.sqlite)
```

Ja Playwright chromium neveras (piem. darba datorā bloķē group policy), var izmantot Edge:
```powershell
$env:PW_CHANNEL="msedge"; npm run test:e2e
```

**Veiktspēja (NFP-01, NFP-02):**
- `PerformanceTest` (iekš `composer run test`) ieliek 10 000 grāmatas un pārbauda, ka meklēšana atbild ātrāk par 3 s.
- Slodzes tests ar 100 vienlaicīgiem pieprasījumiem jālaiž pret Docker, jo `php artisan serve` apstrādā tikai vienu pieprasījumu vienlaicīgi:
```bash
docker compose exec app php artisan db:seed --class=PerformanceSeeder --force
docker compose exec app php artisan bookloop:load-test http://localhost
```
`PerformanceSeeder` pievieno 200 lietotājus (`perf1..perf200@bookloop.test`) un 10 000 grāmatas, tāpēc to nelaid uz īstās datubāzes.

> Datubāze ar demo datiem ir iekļauta repozitorijā (fails `bookloop`) - migrācijas palaist nav nepieciešams.

**Admin parole:**
```bash
php artisan admin:password epasts@piemers.lv
```

### Palaist ar Docker (uz jebkura datora)

Vajag tikai **Docker Desktop** (Windows/Mac) vai **Docker Engine** (Linux) - PHP, Node un Composer nav jāuzstāda.

```bash
git clone https://github.com/Megniss/BL-PROJ.git
cd BL-PROJ
docker compose up -d --build
```

Lapa atveras **http://localhost:8080**. Pirmā būve aizņem dažas minūtes.

- Datubāze pirmajā palaišanā ir kopija no faila `bookloop`, tāpēc lietotāji un grāmatas ir tie paši.
- Datubāze un augšupielādētās bildes glabājas Docker "volume" un saglabājas arī pēc restarta.
- E-pasti (arī 2FA kodi) netiek sūtīti, tie redzami žurnālā:
  ```bash
  docker compose logs app | grep "Your code"
  ```
- Ja `.env` ir `VITE_MAPTILER_KEY`, karte izmanto MapTiler, citādi OpenStreetMap.

```bash
docker compose down      # apturēt
docker compose down -v   # apturēt un izdzēst datus (nākamreiz sāks no jauna)
```

---

## Ko es iemācījos

- REST API projektēšana ar Laravel
- Vue 3 SPA izveide ar Vue Router un reaktīvo stāvokli
- Tokenu autentifikācija ar Sanctum
- Datubāzes relāciju modelēšana un transakcijas
- Droša koda rakstīšana (validācija, autorizācija, rate limiting)
- Pilna stack izstrāde - no migrācijām līdz pogas krāsai ekrānā
- PWA izveide ar Service Worker un Web App Manifest
- Pieejamības standarti (WCAG 2.1 AA)
- **Asinhrona uzdevumu apstrāde ar Laravel Queue**
- **E-pasta sūtīšana caur Mailtrap HTTP API**
- **Admin paneļa izveide ar lomu pārvaldību**
- **Divpakāpju pieteikšanās un kartes ar Leaflet**
- **Automātiskie testi ar PHPUnit un Playwright**
- **Docker konteineri**

---

*"Grāmatai pienākas jauns lasītājs, ne putekļi uz plaukta."*
