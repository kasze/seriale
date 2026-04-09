# Seriale

Lekka webaplikacja do sledzenia nowych odcinkow seriali dla jednego uzytkownika. Projekt jest przygotowany pod tani shared hosting z PHP 8.2+ i MariaDB/MySQL, bez Dockera, bez Node.js po stronie serwera i bez workerow.

## Co juz dziala

- logowanie single-user loginem i haslem, reset hasla oraz opcja `Zapamietaj mnie`
- wyszukiwanie seriali z autocomplete przez TVmaze
- dodawanie seriali do obserwowanych
- dashboard z chronologia ostatnich / nadchodzacych odcinkow
- linki pomocnicze do zewnetrznych wyszukiwarek dla juz wyemitowanych odcinkow
- szczegoly serialu z sezonami i odcinkami
- podobne i polecane seriale przez TMDb
- topki / rankingi przez TMDb z tygodniowym cache
- jasny / ciemny motyw z ustawien
- lazy refresh danych przy wejsciach na widoki
- endpoint cron do odswiezania zaleglych seriali
- ekran ustawien i health/about

## Stack

- PHP 8.2+
- MariaDB / MySQL
- server-rendered HTML + CSS + vanilla JS
- prosty front controller i lekka architektura MVC
- zero build-step po stronie serwera

## Struktura projektu

```text
.
├── .env.example
├── .gitignore
├── .htaccess
├── README.md
├── bin/
│   └── migrate.php
├── bootstrap/
│   ├── app.php
│   ├── autoload.php
│   └── helpers.php
├── composer.json
├── database/
│   ├── migrations/
│   │   └── 001_initial_schema.sql
│   └── seed.sql
├── index.php
├── public/
│   ├── .htaccess
│   ├── assets/
│   │   ├── app.css
│   │   └── app.js
│   └── index.php
├── routes/
│   └── web.php
├── src/
│   ├── Api/
│   ├── Controllers/
│   ├── Core/
│   ├── Repositories/
│   ├── Services/
│   └── Support/
├── storage/
│   ├── cache/
│   └── logs/
└── views/
    ├── auth/
    ├── dashboard/
    ├── errors/
    ├── health/
    ├── partials/
    ├── settings/
    ├── shows/
    └── tracked/
```

## Wymagania

- PHP 8.2 lub 8.3+
- rozszerzenia PHP: `pdo_mysql`, `curl`, `mbstring`
- MariaDB 10.4+ lub MySQL 8+
- dostep do CRON-a jest opcjonalny

## Szybki start lokalnie

1. Skopiuj `.env.example` do `.env`.
2. Ustaw poprawne dane MariaDB/MySQL oraz `APP_URL`.
3. Wykonaj migracje:

```bash
php bin/migrate.php
```

4. Opcjonalnie uruchom seed:

Seed nie jest wymagany, bo glowna migracja wpisuje startowe ustawienia do tabeli `{{prefix}}app_settings`.

5. Uruchom lokalny serwer:

```bash
php -S 127.0.0.1:8000 -t public
```

6. Wejdz na `http://127.0.0.1:8000/login`.

## Deployment na shared hosting

### Wariant preferowany

Ustaw document root na katalog `public/`.

### Wariant awaryjny dla Apache

Jesli hosting nie pozwala ustawic document root, wrzuc caly projekt do katalogu aplikacji i zostaw aktywne rootowe `.htaccess` oraz `index.php`. Rootowy rewrite przekieruje:

- `/assets/*` -> `public/assets/*`
- reszte ruchu -> `public/index.php`
- katalogi techniczne (`src`, `storage`, `database`, `bin` itd.) sa blokowane przez `.htaccess`

### Nginx

Skieruj root na katalog `public/` i ustaw fallback do `index.php`.

## Deploy tylko zmian

Repo zawiera skrypt `bin/deploy.sh`, ktory:

- wrzuca przez FTP tylko pliki zmienione od ostatniego deployu
- usuwa z hostingu tylko pliki skasowane w repo
- nie odpala `database/seed.sql`
- uruchamia tylko wersjonowane migracje z `bin/migrate.php`
- zapisuje lokalnie ostatni wdrozony commit w `.deploy/<nazwa>.last_deploy`

### Konfiguracja deployu

1. Skopiuj `.deploy.env.example` do `.deploy.env`.
2. Ustaw swoje dane FTP, `DEPLOY_REMOTE_DIR` oraz `DEPLOY_APP_URL`.
3. Przy pierwszym uzyciu:

- jesli produkcja juz dziala z aktualnego commita, wykonaj `bin/deploy.sh --mark-current`
- jesli chcesz wykonac pierwszy upload ze skryptu, uzyj `bin/deploy.sh --full`
- jesli produkcja odpowiada konkretnemu commitowi, uzyj `bin/deploy.sh --from <commit>`

### Uzycie

```bash
chmod +x bin/deploy.sh
bin/deploy.sh
```

Przydatne opcje:

```bash
bin/deploy.sh --dry-run
bin/deploy.sh --from 97afa5c
bin/deploy.sh --no-migrate
```

Skrypt wymaga lokalnie: `git`, `lftp`, `curl` i `openssl`.

`.deploy.env` jest ignorowany przez Git. Nie commituj danych FTP.

## Konfiguracja `.env`

Najwazniejsze klucze:

```dotenv
APP_NAME="Seriale"
APP_ENV=development
APP_DEBUG=true
APP_URL=https://twoja-domena.pl
APP_BASE_PATH=/seriale
APP_TIMEZONE=Europe/Warsaw
APP_SECRET=losowy-dlugi-sekret
SESSION_NAME=seriale_session
SINGLE_USER_IDENTITY=you@example.com

DB_HOST=127.0.0.1
DB_DRIVER=mysql
DB_PORT=3306
DB_NAME=seriale
DB_USER=seriale_user
DB_PASS=secret
DB_CHARSET=utf8mb4
DB_TABLE_PREFIX=seriale_

MAIL_TRANSPORT=log
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Seriale"

CACHE_TTL_HOURS=12
TVMAZE_ENABLED=true
TMDB_ENABLED=false
TMDB_API_KEY=
OMDB_ENABLED=false
OMDB_API_KEY=
CRON_SECRET=losowy-sekret-cron
```

`APP_ENV` jest przełącznikiem wdrożeniowym w pliku `.env`. Na publicznym hostingu ustaw `APP_ENV=production`; w panelu ustawień celowo nie ma tej opcji.

## Logowanie single-user

- pole logowania sprawdza zgodnosc z `SINGLE_USER_IDENTITY` albo wartoscia z `app_settings.single_user_identity`
- haslo jest przechowywane jako bezpieczny hash w tabeli `users`
- reset hasla generuje jednorazowy link wazny 20 minut
- przy `MAIL_TRANSPORT=mail` aplikacja probuje wyslac link resetu przez wbudowane `mail()`
- przy `MAIL_TRANSPORT=log` albo braku maila link resetu jest logowany / pokazywany jako fallback techniczny
- checkbox `Zapamietaj mnie` ustawia podpisane cookie remember na tym urzadzeniu

## Synchronizacja i cache

- dane serialu sa odswiezane lazy, gdy wejdziesz na dashboard lub strone serialu i TTL juz minal
- TTL ustawia `cache_ttl_hours`
- manualne odswiezenie dziala przez `POST /shows/{id}/refresh`
- prosty cron:

```text
GET /cron/sync?key=CRON_SECRET
```

## Baza danych

Glowna migracja tworzy tabele:

- `seriale_users`
- `seriale_login_tokens`
- `seriale_tracked_shows`
- `seriale_external_ids`
- `seriale_shows`
- `seriale_seasons`
- `seriale_episodes`
- `seriale_show_user_state`
- `seriale_sync_logs`
- `seriale_app_settings`

## Routing

- `/login`
- `/logout`
- `/dashboard`
- `/top`
- `/shows/search`
- `/shows/{id}`
- `/shows/{id}/refresh`
- `/tracked`
- `/settings`
- `/health`
- `/about`
- `/cron/sync`

## Providerzy

### TVmaze

Glowny provider dla seriali i odcinkow.

### OMDb

Opcjonalne wzbogacenie ratingow IMDb po `imdb_id`. Wymaga `OMDB_ENABLED=true` i klucza `OMDB_API_KEY`.

### TMDb

Opcjonalne wzbogacenie metadanych/ratingu. Wymaga `TMDB_ENABLED=true` i klucza `TMDB_API_KEY`.

### Filmweb

Brak scrapingu. Aplikacja pokazuje bezpieczny link do wyszukiwania albo moze pozniej przechowywac recznie przypisany URL.

## Bezpieczenstwo

- sesje `HttpOnly` i `SameSite=Lax`
- token CSRF dla formularzy POST
- hashowane tokeny i kody logowania
- escapowanie HTML w widokach
- podstawowy CSP przez meta tag
- logi aplikacyjne w `storage/logs/app.log`

## Ograniczenia etapu 1

- `mail()` zamiast pelnego klienta SMTP
- brak panelu do recznego przypisywania linkow zewnetrznych per serial
- brak testow automatycznych

Projekt jest jednak gotowy do uruchomienia, rozwijania i dalszego dopracowania pod konkretne API keys, SMTP albo rozszerzenia domenowe.

## Publikacja na GitHubie

Przed pierwszym push:

```bash
git status --short
git log --oneline -n 5
```

Dodanie zdalnego repo po SSH:

```bash
git remote add origin git@github.com:TWOJ_LOGIN/TWOJE_REPO.git
git branch -M main
git push -u origin main
```

Jesli remote juz istnieje:

```bash
git remote -v
git push
```

Test SSH do GitHuba:

```bash
ssh -T git@github.com
```

Pliki z sekretami, ktore maja zostac lokalne:

- `.env`
- `.deploy.env`
- `.deploy/`
- `storage/logs/*.log`
- dumpy bazy i backupy produkcyjne

## Bezpieczenstwo danych przy deployu

- deploy nie resetuje bazy i nie uruchamia seeda
- migracje sa zapisywane w `seriale_schema_migrations`, wiec kazdy plik SQL odpala sie tylko raz
- startowe wpisy w `seriale_app_settings` sa dopisywane tylko gdy brakuje klucza, bez nadpisywania istniejacych ustawien z produkcji
