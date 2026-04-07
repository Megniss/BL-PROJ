# BookLoop — Grāmatu Apmaiņas Platforma

> Skolēna projekts | Izstrādātājs: **Eduards Megnis** | 2026. gads

---

## Par projektu

**BookLoop** ir grāmatu apmaiņas tīmekļa lietotne. Ideja vienkārša — mājās guļ izlasītas grāmatas, kuras kādam citam varētu noderēt. BookLoop ļauj lietotājiem piedāvāt savas grāmatas un apmainīt tās pret citu grāmatām bez maksas.

Rezultātā sanāca pilnvērtīga platforma ar lietotāju kontiem, grāmatu katalogu, apmaiņas sistēmu, iekšējo čatu un vērtēšanas sistēmu.

---

## Tehnoloģiskais steks

| Slānis | Tehnoloģija |
|--------|-------------|
| **Backend** | Laravel 11 (PHP) |
| **Frontend** | Vue 3 (SPA) |
| **Datubāze** | SQLite |
| **Autentifikācija** | Laravel Sanctum |
| **CSS** | Bootstrap 5 + pielāgota zaļā shēma |
| **Būvēšana** | Vite |
| **PWA** | Web App Manifest + Service Worker |

Laravel apstrādā REST API loģiku, Vue veido lietotāja saskarni un komunicē ar API caur Axios. Lietotnei ir tumšais/gaišais režīms un pilns LV/EN valodu atbalsts.

---

## Funkcionalitāte

### 1. Lietotāju sistēma
- Reģistrācija, pieteikšanās, atteikšanās
- Paroles atjaunošana pa e-pastu
- Profila rediģēšana (vārds, e-pasts, parole)
- Apmaiņu vēsture profilā

### 2. Grāmatu bibliotēka
- Pievienot, rediģēt, dzēst grāmatas
- Vāka attēla augšupielāde (vai automātisks krāsu gradients)
- 15 žanri, 3 valodas, 4 fiziskā stāvokļa līmeņi
- Automātisks statuss: Pieejama → Aizturēta → Apmainīta

### 3. Grāmatu pārlūkošana
- Pieejama arī nereģistrētiem lietotājiem
- Meklēšana pēc nosaukuma, autora vai žanra
- Filtri pēc vairākiem žanriem un vairākām valodām vienlaicīgi
- Poga visu filtru notīrīšanai uzreiz
- Kārtošana pēc nosaukuma vai autora (A-Z / Z-A)
- Kartiņu un tabulas skats
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

---

## Datubāzes struktūra

```
users                   — lietotāju konti
books                   — grāmatu katalogs
swap_requests           — apmaiņas pieprasījumi
messages                — ziņojumi starp lietotājiem
notifications           — sistēmas paziņojumi
ratings                 — grāmatu vērtējumi pēc apmaiņas
personal_access_tokens  — Sanctum tokeni
```

---

## Pieejamība (WCAG 2.1 AA)

- Visi modālie logi ar `role="dialog"` un `aria-modal`
- Kļūdu paziņojumi ar `role="alert"`
- Ziņojumu saraksts ar `role="log"` un `aria-live`
- Ikonas pogas ar `aria-label`
- Klaviatūras navigācija uz visiem klikšķināmiem elementiem

---

## PWA

Lietotne ir instalējama kā progresīvā tīmekļa lietotne (PWA):
- `manifest.json` ar ikonām un tēmas krāsu
- Service Worker kešo statiskos resursus (network-first stratēģija)
- API pieprasījumi nekad netiek kešoti

---

## Drošība

- Paroles saglabātas kā bcrypt hash
- API aizsargāts ar Sanctum tokeniem
- Ātruma ierobežojumi pieteikšanās un reģistrācijas maršrutiem
- Piederības pārbaudes — lietotājs var mainīt tikai savus datus
- Ievades validācija visos API galapunktos
- Transakcijas apmaiņas operācijām

---

## Projekta struktūra

```
app/Http/Controllers/   ← 8 API kontrolieri
app/Models/             ← User, Book, SwapRequest, Message, Rating
resources/js/
  components/           ← 15 Vue komponentes
  router/router.js      ← Vue Router
  translations.js       ← visi UI teksti LV/EN
  authStore.js / langStore.js / themeStore.js
routes/api.php          ← ~27 REST API galapunkti
```

---

## Kā palaist

**Pirmo reizi:**
```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
npm install
```

**Palaist izstrādes vidi:**
```bash
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

---

*"Grāmatai pienākas jauns lasītājs, ne putekļi uz plaukta."*
