# Laenutus

Laenutussüsteem PHP monoliidina. Proovi teha mikroteenusteks.

## Käivitamine

```bash
cp .env.example .env
docker compose up --build
```

Ava brauseris: http://localhost:8080

## Testkontod

| E-post | Parool | Roll |
|---|---|---|
| student@kool.ee | student123 | user |
| admin@kool.ee | admin123 | admin |

## Rollipõhine vaade

Sama rakendus, erinev UI vastavalt rollile:

| Vaade | Õpilane | Admin |
|---|---|---|
| Vahendid | laenutuse vorm + nimekiri | vahendite haldus (staatuse muutmine) |
| Laenutused | ainult enda laenutused | kõigi kasutajate laenutused |
| Detail | tühistamine | laenutuse staatuse muutmine |

Päises on adminil lilla **Admin** märk.

## API demo (curl)

```bash
# Tervisekontroll
curl -i http://localhost:8080/health

# Sisselogimine
curl -s -X POST http://localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"student@kool.ee","password":"student123"}'

# Token muutujasse (PowerShell)
$token = (curl -s -X POST http://localhost:8080/auth/login -H 'Content-Type: application/json' -d '{"email":"student@kool.ee","password":"student123"}' | ConvertFrom-Json).token

# Laenutuste loend
curl -i http://localhost:8080/loans -H "Authorization: Bearer $token"

# Üks laenutus
curl -i http://localhost:8080/loans/l-100 -H "Authorization: Bearer $token"

# Uus laenutus
curl -i -X POST http://localhost:8080/loans \
  -H "Authorization: Bearer $token" \
  -H 'Content-Type: application/json' \
  -d '{"userId":"u-7","itemId":"i-1","startDate":"2026-10-01","endDate":"2026-10-03"}'

# Vigane kuupäev (peab andma 400)
curl -i -X POST http://localhost:8080/loans \
  -H "Authorization: Bearer $token" \
  -H 'Content-Type: application/json' \
  -d '{"userId":"u-7","itemId":"i-1","startDate":"2026-10-03","endDate":"2026-09-30"}'

# Puuduv laenutus (peab andma 404)
curl -i http://localhost:8080/loans/l-puudub -H "Authorization: Bearer $token"

# Laenutuse vaade (koostaja)
curl -i http://localhost:8080/loan-view/l-100 -H "Authorization: Bearer $token"
```

## Projekti struktuur

```
src/Auth/           → tulevikus auth teenus
src/Items/          → tulevikus items teenus
src/Loans/          → tulevikus loans teenus
src/Notifications/  → tulevikus notifications teenus
src/LoanView/       → tulevikus API gateway / BFF
sql/                → ühine andmebaas (monoliit)
```

## Mikroteenusteks lõhkumine

Jagada monoliit järgmisteks sammudeks:

1. **Items teenus** — eraldada `src/Items/` + `items` tabel oma andmebaasi
2. **Loans teenus** — eraldada `src/Loans/` + `loans` tabel; suhtlus items-iga HTTP kaudu
3. **Notifications teenus** — eraldada `src/Notifications/` + `notifications` tabel
4. **HTTP suhtlus** — asendada otsekutsed (`ItemsService`) HTTP päringutega
5. **Eraldi andmebaasid** — iga teenus omab oma skeemi (andmebaas teenuse kohta)
6. **Docker Compose** — iga teenus oma konteineris

### Monoliit vs mikroteenused

| Aspekt | Monoliit (praegu) | Mikroteenused (siht) |
|---|---|---|
| Andmebaas | üks MySQL, 4 tabelit | 3–4 eraldi andmebaasi |
| Suhtlus | otsekutsed PHP-s | HTTP REST |
| Juurutamine | üks konteiner | mitu konteinerit |
| Tehingud | üks DB transaktsioon | ajutine lahknevus + olekumasin |

### Moodulite kaardistus

| Moodul | Tabel | Tulevane teenus | Port |
|---|---|---|---|
| Auth | users | auth (valikuline) | 8083 |
| Items | items | items | 8081 |
| Loans | loans | loans | 8080 |
| Notifications | notifications | notifications | 8082 |

## Otspunktid

- `GET /health`
- `POST /auth/login`, `GET /auth/me`
- `GET /items`, `GET /items/{id}`, `PATCH /items/{id}`
- `GET /loans`, `GET /loans/{id}`, `POST /loans`, `PATCH /loans/{id}`, `DELETE /loans/{id}`
- `GET /loan-view/{id}`

## Arendus-keskkonnas

```bash
composer install
cp .env.example .env
# Muuda .env: DB_HOST=localhost
php -S localhost:8080 -t public
```

## Õpetaja valmislahendus

Mikroteenuste variant (3 teenust, 6 Docker konteinerit): vaata [`lahendus/README.md`](lahendus/README.md).
