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
DATABASE_URL='postgresql://app:password@127.0.0.1:5432/mobile_api?serverVersion=16&charset=utf8' npm run e2e:real
npx expo export --platform all
```

Playwright uruchamia wersję przeglądarkową w rozmiarze telefonu na porcie 19082.
`EXPO_WEB_PORT` pozwala wybrać inny port. Testy zawsze uruchamiają własny serwer,
żeby nie pomylić aplikacji z inną usługą działającą pod tym samym adresem.
Scenariusze ekranów wykorzystują kontrolowane odpowiedzi API. `e2e:real` wymaga PHP,
zależności Composer oraz PostgreSQL 16. Podaj `DATABASE_URL` wskazujący osobną bazę
testową; konfiguracja Symfony dopisuje do nazwy bazy sufiks `_test`. Użytkownik bazy
musi móc ją utworzyć. Skrypt uruchamia migracje, własny backend oraz Expo na portach
19080 i 19083. Dane pozostają w bazie po zakończeniu testów. GitHub Actions zapewnia
własną instancję PostgreSQL dla każdego uruchomienia.

Testy sprawdzają logowanie, wykonanie zadania, odtworzenie sesji oraz Plan dnia:
prywatną zajętość, zaproszenia, cykle, wyjątki i wspólne terminy. Testy backendu
dodatkowo sprawdzają rotację tokenów, uprawnienia i zapis danych.
Eksport sprawdza bundlowanie Androida, iOS i web; nie zastępuje testu na urządzeniu.

## Wydanie APK

Każde opublikowane wydanie na GitHubie, poza pre-release, dostaje w załącznikach
`FamilyPlan-<wersja>.apk`. Buduje go job `Build Android APK` w
`.github/workflows/deploy-hostinger.yml`, równolegle z obrazem serwera i po tej samej bramce
testów. Wersję i kod wersji bierze z `app.json`, a `EXPO_PUBLIC_API_URL`
ustawia na produkcję. Zanim dołączy plik, sprawdza jego podpis i adres API w kodzie aplikacji.

Telefon przyjmie nowy APK jako aktualizację tylko z podpisem zainstalowanej aplikacji. Klucz
trafia do sekretów repozytorium (Settings → Secrets and variables → Actions). Bez nich job kończy
się błędem.

| Sekret | Zawartość |
|--------|-----------|
| `ANDROID_KEYSTORE_BASE64` | plik keystore zakodowany w base64 |
| `ANDROID_KEYSTORE_PASSWORD` | hasło keystore |
| `ANDROID_KEY_ALIAS` | alias klucza |
| `ANDROID_KEY_PASSWORD` | hasło klucza; w keystore PKCS12 to samo co hasło keystore |

```sh
base64 -i family-plan.keystore | gh secret set ANDROID_KEYSTORE_BASE64
gh secret set ANDROID_KEYSTORE_PASSWORD
gh secret set ANDROID_KEY_ALIAS
gh secret set ANDROID_KEY_PASSWORD
```

Właściwy jest klucz, którym podpisano APK zainstalowane na telefonach. Certyfikat pliku pokazuje
`apksigner verify --print-certs FamilyPlan-1.0.9.apk` z Android SDK (`build-tools`). Skrót
`fac61745dc0903786fb9ede62a962b399f7348f0bb6f899b8332667591033b9c` i `CN=Android Debug`
oznaczają publiczny klucz debug z szablonu Expo: tak podpisuje `./gradlew assembleRelease` bez
własnego klucza. Wtedy sekrety wskazują ten klucz: `android/app/debug.keystore` po
`npx expo prebuild`, alias `androiddebugkey`, oba hasła `android`. Ten klucz zna każdy, więc każdy
może też podpisać nim aplikację, którą telefon przyjmie jako aktualizację Family Plan. Przejście
na własny klucz to jednorazowe odinstalowanie aplikacji i ponowne logowanie; dane zostają na
serwerze.

```sh
keytool -genkeypair -v -storetype PKCS12 -keystore family-plan.keystore \
  -alias family-plan -keyalg RSA -keysize 4096 -validity 10000
```

Keystore i hasła wymagają kopii zapasowej: bez nich kolejne wersje nie zainstalują się jako
aktualizacja.

Zmienna repozytorium `ANDROID_CERT_SHA256` (zakładka Variables) przyjmuje skrót SHA-256
certyfikatu, także z dwukropkami, jak w `keytool -list -v`. Gdy jest ustawiona, job nie dołączy
APK z innym podpisem. Skrót każdego builda jest w podsumowaniu runu.

`Actions → Deploy to Hostinger → Run workflow` z tagiem i zaznaczonym `apk_only` buduje APK dla
istniejącego wydania bez wdrażania serwera. Wydania sprzed `plugins/with-release-signing.js`
mają w kodzie tylko klucz debug, więc przy innym kluczu job je odrzuci.

Lokalnie tym samym kluczem podpisuje `plugins/with-release-signing.js`. Wystarczą właściwości
w `~/.gradle/gradle.properties`:

```properties
FAMILY_PLAN_RELEASE_STORE_FILE=/pełna/ścieżka/family-plan.keystore
FAMILY_PLAN_RELEASE_STORE_PASSWORD=…
FAMILY_PLAN_RELEASE_KEY_ALIAS=family-plan
FAMILY_PLAN_RELEASE_KEY_PASSWORD=…
```

```sh
npx expo prebuild --platform android
cd android && EXPO_PUBLIC_API_URL=https://family-plan.srv1201847.hstgr.cloud ./gradlew assembleRelease
```

Bez tych właściwości wydanie nadal podpisuje klucz debug z szablonu.

## Powiadomienia przy zamkniętej aplikacji

Gdy aplikacja jest zamknięta, powiadomienia przychodzą przez Firebase Cloud Messaging (FCM). Wymaga to
jednego projektu Firebase i dwóch kluczy z niego: jednego dla aplikacji, drugiego dla serwera. Bez nich
wszystko działa jak dotąd, tylko alerty pojawiają się wyłącznie w otwartej aplikacji, a ustawienia
pokazują „Ta wersja aplikacji nie dostaje jeszcze powiadomień przy zamkniętej aplikacji”.

1. [console.firebase.google.com](https://console.firebase.google.com) → Dodaj projekt (Google Analytics
   niepotrzebny).
2. W projekcie: Dodaj aplikację → Android, nazwa pakietu `pl.familyplan.app`. Pobierz
   `google-services.json`. SHA-1 nie jest potrzebne.
3. Plik trafia do sekretu repozytorium `GOOGLE_SERVICES_JSON` (Settings → Secrets and variables →
   Actions): cała treść albo jej base64. Job `Build Android APK` zapisuje go jako
   `mobile/google-services.json` przed `expo prebuild` i sprawdza, że zawiera pakiet aplikacji. Plik jest
   w `.gitignore`. Lokalnie wystarczy go położyć w `mobile/`, a `app.config.js` sam dopisze
   `android.googleServicesFile`.
4. Ustawienia projektu → Konta usługi → Wygeneruj nowy klucz prywatny. Pobrany JSON (albo jego base64)
   trafia do zmiennej `FCM_SERVICE_ACCOUNT` projektu `family-plan-project` w Docker Managerze. Compose
   przekazuje ją do kontenerów `app` i `worker` (`docker-compose.hostinger.yml`). To klucz z prawem
   wysyłki w imieniu projektu, więc nie trafia do repozytorium ani do aplikacji.
5. Wydanie z nowym APK. Po jego instalacji i zalogowaniu telefon rejestruje się sam
   (`POST /api/push/devices`). Ustawienia → Powiadomienia na telefonie pokazują stan, a przycisk
   „Włącz powiadomienia na telefonie” prosi o zgodę, jeśli jej brakuje.

`GET /api/push/key` zwraca `native: true`, gdy serwer ma konto serwisowe. Próbne powiadomienie
z ustawień weba dociera też do telefonów tego użytkownika.

Powiadomienia idą kanałem Androida `family-plan` z wysoką ważnością, więc pojawiają się na górze ekranu.
Zamiast pierwszego kanału `tasks`, który miał tylko ważność listy, aplikacja zakłada nowy, bo ważności
istniejącego kanału nie da się podnieść. Kiedy aplikacja jest otwarta, systemowe powiadomienie się nie
pokazuje; zamiast niego jest dymek. Powiadomienie, które ktoś załatwił albo które przeczytano w innym
miejscu, znika z traya od razu: serwer wysyła cichą wiadomość, a zadanie w tle (`src/notifications/background.native.ts`,
wczytywane przez `index.ts` przed routerem) zamyka je także przy zamkniętej aplikacji. Wygasłe znika przy
następnym otwarciu aplikacji.

Część telefonów (Xiaomi, Huawei, Oppo, niektóre Samsungi) po zamknięciu aplikacji z listy ostatnich
wstrzymuje też jej powiadomienia. Jeśli mimo działającego FCM przychodzą dopiero po otwarciu aplikacji,
w ustawieniach systemu trzeba zezwolić Family Plan na autostart albo wyłączyć dla niej optymalizację
baterii.

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
