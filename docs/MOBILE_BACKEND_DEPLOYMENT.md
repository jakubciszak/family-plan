# Backend aplikacji mobilnej

APK łączy się z `https://family-plan.srv1201847.hstgr.cloud`. Potrzebuje logowania
JWT i API personalizacji dostarczonych w tej zmianie. Logowanie sesyjne webu,
powiadomienia push oraz plany działania pozostają dostępne.

## Logowanie

- `POST /api/auth/token`: e-mail i hasło; odpowiedź zawiera `token`,
  `refresh_token` i `user`.
- `POST /api/auth/token/refresh`: jednorazowy refresh token; odpowiedź zawiera
  nową parę tokenów. Okres ważności refresh tokenu wynosi 30 dni.
- Chronione żądania mobilki przekazują `Authorization: Bearer <token>`.

## Wdrożenie

Nowe migracje dodają tabelę `refresh_tokens` oraz język i tryb motywu w tabeli
personalisacji. Migracje nie usuwają dotychczasowych danych.

Kontener aplikacji wymaga trwałego wolumenu `jwt_keys` zamontowanego w
`/app/config/jwt`. Przy pierwszym uruchomieniu generuje klucze. Kolejne uruchomienia
korzystają z zapisanej pary. Klucze są wykluczone z Git i obrazu Docker.
Hasło klucza, jeśli jest ustawione, musi pozostać zgodne z zapisanym kluczem.

Hostinger przechowuje własny plik Compose. Przed wdrożeniem obrazu trzeba w nim
odzwierciedlić dodany wolumen i zmienną `JWT_PASSPHRASE` z konfiguracji repozytorium.
Procesy worker i cron zachowują `RUN_MIGRATIONS=false`; klucze generuje aplikacja.

Wydanie przechodzi istniejącą bramkę testów GitHub Actions. Kontrola po wdrożeniu
sprawdza stronę główną, chronione API i obecność endpointu logowania JWT.
Końcowy test powinien sprawdzić poprawne logowanie, żądanie z tokenem i jego
odświeżenie. Odpowiedź 404 z `/api/auth/token` oznacza brak potrzebnego backendu.

Przed wdrożeniem 2026-09-18 zapisano na serwerze bazę, konfigurację Compose
i identyfikator poprzedniego obrazu w
`/root/family-plan-backups/20260918-mobile-backend/`. Kopia bazy jest w formacie
PostgreSQL custom; jej spis obiektów sprawdzono przez `pg_restore --list`.

Przy wycofaniu obrazu nowe, dodatkowe kolumny i tabela mogą pozostać w bazie.
Pełne odtworzenie kopii bazy wymaga osobnej decyzji, ponieważ usunęłoby zapisy
powstałe po wykonaniu kopii.
