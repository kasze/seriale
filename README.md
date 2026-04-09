# Seriale

Lekka aplikacja do pilnowania nowych odcinków seriali. Jest przeznaczona dla jednej osoby i zwykłego hostingu z PHP oraz MariaDB/MySQL. Nie wymaga Dockera, Node.js po stronie serwera, Redis, kolejek ani procesów działających w tle.

## Co Potrafi

- Pokazuje odcinki wyemitowane w ostatnim tygodniu i zapowiedziane na kolejny tydzień.
- Przechowuje listę obserwowanych seriali.
- Wyszukuje seriale w TVmaze i pozwala szybko dodać tytuł do obserwowanych.
- Zapisuje sezony, odcinki, daty emisji, plakaty, opisy, statusy i linki zewnętrzne.
- Pokazuje szczegóły serialu z listą sezonów i odcinków od najnowszych.
- Pokazuje podobne seriale, polecane seriale i rankingi z TMDb.
- Uzupełnia oceny z OMDb, jeżeli podasz klucz.
- Ma jasny i ciemny motyw.
- Loguje jednego użytkownika loginem i hasłem.
- Ma opcję zapamiętania sesji na urządzeniu.
- Ma reset hasła przez mail albo przez dziennik aplikacji.
- Odświeża dane przy wejściu na stronę oraz przez opcjonalny adres dla automatu hostingu.
- Ma skrypt wdrożeniowy, który wysyła przez FTP tylko zmienione pliki.

## Wymagania

- PHP 8.2 lub nowszy.
- Rozszerzenia PHP: `pdo_mysql`, `curl`, `mbstring`.
- MariaDB 10.4 lub nowsza, ewentualnie MySQL 8 lub nowszy.
- Serwer WWW: Apache albo Nginx.
- Opcjonalnie na komputerze wdrożeniowym: `git`, `lftp`, `curl`, `openssl`.

## Struktura

```text
.
├── .deploy.env.example
├── .env.example
├── .htaccess
├── bin/
│   ├── deploy.sh
│   └── migrate.php
├── bootstrap/
├── database/
│   ├── migrations/
│   └── seed.sql
├── public/
│   ├── assets/
│   └── index.php
├── routes/
├── src/
│   ├── Api/
│   ├── Controllers/
│   ├── Core/
│   ├── Repositories/
│   ├── Services/
│   └── Support/
├── storage/
└── views/
```

## Uruchomienie Lokalnie

1. Przygotuj plik środowiska:

```bash
cp .env.example .env
```

2. Uzupełnij połączenie z bazą, adres aplikacji i sekret:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_SECRET=wpisz-dlugi-losowy-sekret

DB_HOST=127.0.0.1
DB_DRIVER=mysql
DB_PORT=3306
DB_NAME=seriale
DB_USER=seriale_user
DB_PASS=wpisz-haslo
DB_TABLE_PREFIX=seriale_
```

3. Wykonaj migracje:

```bash
php bin/migrate.php
```

4. Uruchom lokalny serwer PHP:

```bash
php -S 127.0.0.1:8000 -t public
```

5. Otwórz:

```text
http://127.0.0.1:8000/login
```

## Pierwsze Logowanie

Login jedynego użytkownika ustawiasz przez `SINGLE_USER_IDENTITY` w `.env` albo później w ustawieniach aplikacji.

Hasło możesz ustawić przez reset hasła. Jeżeli nie masz skonfigurowanej wysyłki maili, ustaw w `.env`:

```dotenv
MAIL_TRANSPORT=log
```

W trybie deweloperskim link resetu może być pokazany na ekranie logowania. Na hostingu produkcyjnym sprawdź dziennik w `storage/logs/app.log` albo skonfiguruj wysyłkę przez funkcję PHP.

## Ważne Ustawienia

Najważniejsze klucze w `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://twoja-domena.pl
APP_BASE_PATH=
APP_TIMEZONE=Europe/Warsaw
APP_SECRET=wpisz-dlugi-losowy-sekret
SINGLE_USER_IDENTITY=you@example.com

CACHE_TTL_HOURS=12
EPISODE_AVAILABILITY_OFFSET_DAYS=1

TVMAZE_ENABLED=true
TMDB_ENABLED=false
TMDB_API_KEY=
OMDB_ENABLED=false
OMDB_API_KEY=

CRON_SECRET=wpisz-dlugi-losowy-sekret
```

`APP_ENV` jest ustawieniem plikowym. Na publicznym hostingu użyj `APP_ENV=production`. W panelu ustawień nie ma przełącznika trybu aplikacji.

`EPISODE_AVAILABILITY_OFFSET_DAYS` mówi, ile dni dodać do dat z TVmaze przed zapisaniem odcinka. Przy serialach emitowanych w USA i oglądanych w polskich serwisach często praktyczne jest `1`.

## Klucze Zewnętrzne

TVmaze działa bez klucza.

TMDb jest potrzebne do topek, podobnych seriali i polecanych tytułów:

```dotenv
TMDB_ENABLED=true
TMDB_API_KEY=wklej-klucz
```

OMDb jest potrzebne do ocen IMDb, Rotten Tomatoes i Metacritic, jeżeli OMDb ma je dla danego tytułu:

```dotenv
OMDB_ENABLED=true
OMDB_API_KEY=wklej-klucz
```

Aplikacja nie pobiera ocen z Filmwebu, bo Filmweb nie udostępnia stabilnego publicznego API dla tej funkcji.

## Wdrożenie Na Hosting

Najczystszy wariant: ustaw katalog publiczny strony na `public/`.

Jeżeli hosting nie pozwala wybrać katalogu publicznego, wgraj cały projekt do katalogu aplikacji. Główny `.htaccess` przekieruje ruch do `public/index.php` i zablokuje dostęp do katalogów technicznych.

Po wgraniu plików wykonaj migracje:

```bash
php bin/migrate.php
```

Na publicznym hostingu ustaw:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

## Wdrożenie Przez FTP

Skrypt `bin/deploy.sh` wysyła tylko pliki zmienione od poprzedniego wdrożenia. Nie uruchamia `database/seed.sql`. Migracje SQL wykonuje przez `bin/migrate.php` i zapisuje, które pliki migracji zostały już wykonane.

1. Przygotuj lokalny plik z danymi FTP:

```bash
cp .deploy.env.example .deploy.env
```

2. Uzupełnij `.deploy.env`:

```dotenv
DEPLOY_NAME=production
DEPLOY_FTP_HOST=ftp.example.com
DEPLOY_FTP_PORT=21
DEPLOY_FTP_USER=ftp-user@example.com
DEPLOY_FTP_PASS=wpisz-haslo
DEPLOY_REMOTE_DIR=public_html
DEPLOY_APP_URL=https://twoja-domena.pl
```

3. Pierwsze wdrożenie:

```bash
bin/deploy.sh --full
```

4. Kolejne wdrożenia:

```bash
bin/deploy.sh
```

Przydatne polecenia:

```bash
bin/deploy.sh --dry-run
bin/deploy.sh --from <commit>
bin/deploy.sh --no-migrate
bin/deploy.sh --mark-current
```

## Automatyczne Odświeżanie

Aplikacja nie wymaga zadań w tle. Sama sprawdza ważność zapisanych danych przy wejściu na pulpit albo szczegóły serialu.

Jeżeli hosting ma harmonogram zadań, możesz okresowo wywoływać:

```text
GET /cron/sync?key=CRON_SECRET
```

Wartość `CRON_SECRET` ustaw w `.env` albo w panelu ustawień.

## Baza Danych

Domyślny przedrostek tabel to `seriale_`.

Główne tabele:

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
- `seriale_schema_migrations`

Migracje są bezpieczne dla istniejących danych użytkownika. Skrypt wdrożeniowy nie czyści bazy i nie uruchamia seeda.

## Ścieżki Aplikacji

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

## Bezpieczeństwo

- `.env`, `.deploy.env`, `.deploy/` i logi są ignorowane przez Git.
- Hasła są zapisywane jako hashe z `password_hash()`.
- Formularze POST są chronione tokenem CSRF.
- Sesja używa ciastek `HttpOnly` i `SameSite=Lax`.
- Opcja zapamiętania sesji używa podpisanego tokenu.
- HTML w widokach jest escapowany.
- Logi aplikacji trafiają do `storage/logs/app.log`.
- Nie commituj zrzutów bazy, plików `.env`, prywatnych kluczy ani haseł FTP.

## Publikacja Na GitHubie

Sprawdź stan:

```bash
git status --short
git log --oneline -n 5
```

Dodaj zdalne repo przez SSH:

```bash
git remote add origin git@github.com:TWOJ_LOGIN/TWOJE_REPO.git
git branch -M main
git push -u origin main
```

Jeżeli zdalne repo już istnieje:

```bash
git remote -v
git push
```

Test połączenia z GitHubem:

```bash
ssh -T git@github.com
```

Hasło pytane przez `Enter passphrase for key ...` to hasło do lokalnego klucza SSH, a nie hasło do konta GitHub.

## Zasady Rozwoju

- Migracje dopisuj w `database/migrations/`.
- Nie zmieniaj już wykonanej migracji, jeżeli aplikacja działa na produkcji. Dodaj następną.
- Kod PHP sprawdzisz poleceniem `php -l <plik>`.
- Wdrożenie zmian sprawdzisz najpierw przez `bin/deploy.sh --dry-run`.
- Po zmianie korekty dat emisji odśwież obserwowane seriale w panelu ustawień.
