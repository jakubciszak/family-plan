# Family Plan — aplikacja mobilna

Expo SDK 57, React Native 0.86, React 19 i TypeScript. Android, iOS oraz wersja
przeglądarkowa korzystają ze wspólnego interfejsu i API aplikacji Family Plan.

## Uruchomienie

Wymagany Node 22.13 lub nowszy z linii 22. W katalogu `mobile`:

```sh
npm ci
cp .env.example .env
npm start -- --port 19082
```

W `EXPO_PUBLIC_API_URL` ustaw adres API dostępny z urządzenia:

- emulator Androida: `http://10.0.2.2:8080`;
- przeglądarka i symulator iOS: `http://localhost:8080`;
- telefon: adres komputera w sieci lokalnej lub adres HTTPS wdrożonej aplikacji.

Jawnie ustawiony adres ma pierwszeństwo. Bez niego aplikacja w trybie developerskim
korzysta z hosta Metro i portu API 8080. W wydaniu produkcyjnym adres trzeba podać
podczas budowania, ponieważ Expo włącza publiczne zmienne środowiskowe do aplikacji.
Dla przeglądarki backend musi dodatkowo dopuszczać jej origin w konfiguracji CORS.

Szczegóły narzędzi Androida: [MOBILE_SETUP.md](../docs/MOBILE_SETUP.md).
Identyfikator Android/iOS: `pl.familyplan.app`. Katalogi natywne generuje Expo prebuild.

## Dostępne funkcje

- Rejestracja, logowanie JWT, przyjmowanie zaproszeń i odtwarzanie sesji.
- Zadania: przyjmowanie, oddawanie, wykonanie i zgłaszanie zaległych dni.
- Domownicy: zatwierdzanie, cofanie z powodem i kalendarz punktów.
- Korekty zatwierdzonych wykonań w otwartym tygodniu.
- Tworzenie i edycja zespołów, zapraszanie i usuwanie członków.
- Typy zadań, limity, bonusy i reguły zmian statusów.
- Portfel, cele oszczędnościowe, wypłaty, stawki oraz wybór tygodnia do rozliczenia.
- Wspólne z wersją web ustawienia języka, motywu, awatara, tła i układu strony.
- Powiadomienia o zmianach zadania, prośba o uprawnienia Androida i lokalne alerty.
- Plany działania, etapy, minutnik, przypomnienia i odtwarzanie postępu.
- Konfetti z opcjonalnym dźwiękiem.

Zakres dostarczania alertów: [TASK_NOTIFICATIONS.md](../docs/TASK_NOTIFICATIONS.md).

Tokeny na urządzeniu zapisuje Expo SecureStore. W przeglądarce wykorzystywany jest
localStorage. Odświeżenie jest współdzielone między równoległymi żądaniami; przejściowe
problemy sieciowe nie usuwają zapisanej sesji. Wylogowanie usuwa oba tokeny.

## Weryfikacja

```sh
npm run typecheck
npm run lint
npm run e2e
npm run e2e:real
npx expo export --platform all
```

Playwright uruchamia wersję przeglądarkową w rozmiarze telefonu na porcie 19082.
`EXPO_WEB_PORT` pozwala wybrać inny port. Testy zawsze uruchamiają własny serwer,
żeby nie pomylić aplikacji z inną usługą działającą pod tym samym adresem.
Scenariusze ekranów wykorzystują kontrolowane odpowiedzi API. `e2e:real` uruchamia
własny backend z tymczasową bazą SQLite (wymaga PHP i zainstalowanych zależności
Composer) oraz Expo na portach 19080 i 19083. Sprawdza logowanie, wykonanie zadania
i odtworzenie sesji. Testy backendu dodatkowo sprawdzają rotację tokenów,
uprawnienia i zapis danych.
Eksport sprawdza bundlowanie Androida, iOS i web; nie zastępuje testu na urządzeniu.

## Backend JWT

Po zainstalowaniu zależności PHP uruchom migracje i wygeneruj klucze:

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

`POST /api/auth/token` przyjmuje `email` i `password`. Zwraca `token`,
`refresh_token` i `user`. `POST /api/auth/token/refresh` wymienia `refresh_token`
na nową parę. Refresh token jest jednorazowy i ważny przez 30 dni.
Dotychczasowe logowanie sesyjne wersji web nadal działa.

Konfiguracja Hostinger przechowuje klucze w trwałym wolumenie `jwt_keys`.
Klucze prywatne są wykluczone z Git i kontekstu budowania obrazu Docker.
