# Scenariusze E2E aplikacji mobilnej

`npm run e2e` — Playwright pędzi po aplikacji zbudowanej na `react-native-web`
(`expo start --web`), na viewporcie Pixela 5, z wymuszonym locale `pl-PL`. To jest ten sam
kod, który idzie na telefon, nie atrapa. Czas przebiegu zależy od liczby workerów.

Backend jest podstawiany w pamięci (`fake-api.js`) — stan trzyma test, więc mutacje są
widoczne po przeładowaniu listy i scenariusz sprawdza skutek, nie samo wywołanie.
Nieobsłużony endpoint wraca jako 501 z nazwą ścieżki, żeby pomyłka waliła się głośno.
`app.sent(...)` pozwala sprawdzić payload, który aplikacja wysłała.

## Czego ten harness nie sprawdzi

Zostaje testem ręcznym: gesty (swipe, long press), natywne dialogi uprawnień,
`expo-image-picker`, prawdziwy `SecureStore`, zachowanie po zabiciu aplikacji,
powiadomienia systemowe, konfetti i dźwięk.

Dwie pułapki, na które trzeba uważać pisząc nowe scenariusze:

1. **Odwiedzone zakładki zostają w DOM-ie**, ułożone pod aktywną. `getByRole` i `getByLabel`
   respektują `aria-hidden`, więc widzą tylko wierzch — `getByText` i `getByTestId` nie.
   Do tych drugich jest `app.onScreen(testID)`.
2. **Etykiety nakładają się podciągami**: „Hasło" siedzi w „Pokaż hasło", „EN" w
   „ustawi**en**ia", „Dostępne" w „Dostępne zadania". Stąd `app.field()` i `exact: true`.

Legenda: ✅ pokryte testem · ⏸ tylko ręcznie · 🐞 scenariusz wykrył błąd, naprawiony

---

## 1. Logowanie — `e2e/auth.spec.js`

1. ✅ Niezalogowany trafia na logowanie, nie na zadania.
2. ✅ Przycisk nieaktywny, dopóki e-mail i hasło puste.
3. ✅ Poprawne dane wpuszczają do aplikacji.
4. ✅ Błędne dane zostawiają na ekranie z komunikatem.
5. ✅ Kłódka odsłania hasło i zmienia etykietę na „Ukryj hasło".
6. ✅ Link prowadzi na rejestrację.
7. ✅ Wejście z tokenem zaproszenia zmienia zachętę na „Zaloguj się, aby zaakceptować…".
8. Sesja wraca po przeładowaniu; test z prawdziwym API oraz test zachowania tokenów przy awarii.

## 2. Rejestracja — `e2e/auth.spec.js`

1. ✅ Zapis czeka na imię, e-mail i hasło; telefon opcjonalny.
2. ✅ Bez wymaganej aktywacji rejestracja od razu loguje.
3. ✅ Z wymaganą aktywacją zostaje komunikat i formularz się czyści.
4. ✅ Zajęty adres mówi wprost, że konto istnieje.
5. ✅ Zaproszenie dociąga adres, blokuje pole i pokazuje baner.
6. ✅ Nieznane zaproszenie nie wywala formularza.
7. ✅ Wraca na logowanie.

## 3. Zadania — `e2e/tasks.spec.js`

1. 🐞 Otwarte zadanie ma trzy akcje: Ukończ, Zaległość, Oddaj do puli.
2. 🐞 **Ukończone nie ma żadnej akcji** i nosi „Czeka na zatwierdzenie".
3. 🐞 Odrzucone pokazuje powód i „Zgłoś ponownie".
4. 🐞 Zatwierdzone znika z listy.
5. ✅ Pula pokazuje tylko typy aktywne.
6. ✅ Wzięcie przenosi z puli do moich zadań.
7. ✅ Ukończenie zabiera akcje i pokazuje oczekiwanie.
8. 🐞 Oddanie zwraca zadanie do puli.
9. 🐞 Zaległość wysyła dzień wykonania razem z ukończeniem.
10. ✅ Zaległość odrzuca dzień spoza okna siedmiu dni (w obie strony).
11. ✅ Puste listy mówią o tym wprost.
12. ✅ Błąd wczytywania pokazuje baner, który da się zamknąć.
13. Konfetti po ukończeniu jest objęte testem. Odsłuch dźwięku na fizycznym urządzeniu pozostaje kontrolą ręczną.

## 4. Widok domownika — `e2e/member.spec.js`

1. ✅ Admin zespołu wchodzi w domownika z listy członków.
2. ✅ Zwykły członek nie ma w co kliknąć.
3. ✅ Do zatwierdzenia trafiają tylko zadania ukończone.
4. ✅ Zatwierdzenie zdejmuje pozycję z sekcji.
5. ✅ Odrzucenie wymaga powodu i wysyła go.
6. ✅ Pusta sekcja mówi, że nic nie czeka.
7. 🐞 **Strzałka wraca do Zespołów**, a nie na Zadania.

## 5. Zespoły — `e2e/teams.spec.js`

1. ✅ Lista pokazuje zespół z rolą i członkami.
2. ✅ Tworzenie czeka na nazwę.
3. ✅ Edycja jest wypełniona i zapisuje zmiany.
4. ✅ Zwykły członek nie widzi edycji, zapraszania ani usuwania.
5. ✅ Zaproszenie wysyła adres i wybraną rolę.
6. ✅ Usunięcie członka da się anulować.
7. ✅ Potwierdzone usunięcie zdejmuje członka.
8. ✅ Admina nie da się usunąć.
9. 🐞 **Oczekujące zaproszenie da się przyjąć** — przycisk był martwy.
10. ✅ Błąd wczytywania pokazuje baner.

## 6. Typy zadań — `e2e/rules.spec.js`

1. ✅ Bez administrowanego zespołu nie ma czego definiować.
2. ✅ Tworzenie zapisuje nazwę, punkty i częstotliwość.
3. ✅ Limit z liczbą odsłania pole, bez limitu je chowa.
4. Edycja, wycofanie i usunięcie przez okno akcji; wszystkie trzy scenariusze są aktywne.

## 7. Zasady bonusowe — `e2e/rules.spec.js`

1. ✅ Bez administrowanego zespołu brak dostępu.
2. ✅ Zmiana typu przestawia widoczne pola konfiguracji.
3. ✅ Tworzenie serii wysyła dni, punkty dzienne i konta.
4. ✅ Edycja odsłania typ i konfigurację, wypełnione.
5. ✅ Edycja nie pozwala zmienić zespołu.
6. ✅ Zapis edycji wysyła zmieniony typ i konfigurację, nie samą nazwę.
7. ✅ Wycofanie i przywrócenie zasady.

## 8. Reguły zmian statusów — `e2e/rules.spec.js`

1. ✅ Bez roli administratora aplikacji nie ma tego w nawigacji.
2. ✅ Tworzenie przerwy wysyła pilnowane zadanie i dni.
3. ✅ Lista zadań wymaganych pomija zadanie pilnowane.
4. ✅ Edycja odsłania typ warunku i konfigurację.
5. ✅ Przełączenie na wymagane zadanie bez wyboru blokuje zapis.

## 9. Kieszonkowe — `e2e/personalise.spec.js`

1. ✅ Domownik widzi swój portfel: oczekujące, dostępne, odłożone.
2. ✅ Domownik bez celów dostaje zachętę.
3. ✅ Admin widzi zakładki Rozliczenia i Zasady.
4. ✅ Admin bez ustawionej zasady widzi, że nic nie ustawiono.
5. ✅ Błąd wczytywania pokazuje baner.
6. `allowance.spec.js`: dochód i wydatek z kwotą w groszach, cel i odkładanie, wypłata, potwierdzenie odbioru oraz zamknięcie i otwarcie tygodnia.

## 10. Mój wygląd — `e2e/personalise.spec.js`

1. ✅ Pseudonim zapisuje się po opuszczeniu pola, nie przy każdym znaku.
2. ✅ Pusty pseudonim wraca do imienia z konta.
3. ✅ Wybór koloru wiodącego zapisuje się.
4. ✅ „Zaskocz mnie" losuje nowy avatar.
5. 🐞 Schowanie pozycji ze strony głównej zdejmuje ją z układu.
6. ✅ Wyłączenie wszystkiego ostrzega, że nic nie zostało.
7. ✅ Przestawienie kolejności w pasku zmienia pasek.
8. 🐞 **Konfetti i dźwięk zapisują się** — przełączniki były martwe.
9. Wgranie własnego zdjęcia i tła oraz zapis wybranych obrazów.

## 11. Moje konto — `e2e/notifications.spec.js`

1. ✅ Pokazuje imię, adres i rolę.
2. ✅ Pseudonim wypiera imię z konta.
3. ✅ Wylogowanie wraca na ekran logowania.

## 12. Ustawienia — `e2e/settings.spec.js`

1. ✅ Zmiana motywu zapisuje wybór.
2. ✅ Zmiana języka przestawia interfejs bez przeładowania.
3. ✅ Kanały mają domyślne wartości, gdy backend ich nie ma.
4. 🐞 **Przełączenie kanału i zapis wysyłają nowy stan** — przełączniki były martwe.
5. ✅ Błąd zapisu pokazuje baner i zostawia wybór.

## 13. Powiadomienia o zdarzeniach — `e2e/notifications.spec.js`

1. ✅ Zwykły użytkownik nie ma tego w nawigacji.
2. ✅ Wejście wprost pokazuje „Brak dostępu".
3. ✅ Macierz listuje zdarzenia i kanały.
4. ✅ Zdarzenie transakcyjne ma przełączniki zablokowane.
5. 🐞 **Zapis wysyła tylko zdarzenia konfigurowalne** — przełączniki były martwe.
6. ✅ Błąd zapisu pokazuje baner.

## 14. Dymki powiadomień — `e2e/notifications.spec.js`

1. ✅ Nieprzeczytane pokazuje się z tematem i treścią.
2. 🐞 **Zamknięcie oznacza jako przeczytane raz**, nie dwa razy, i pokazuje kolejne.
3. ✅ To samo powiadomienie nie wraca.
4. ✅ Brak nieprzeczytanych to brak dymka.
5. ⏸ Odpytywanie co 30 s i wstrzymanie w tle.

## 15. Nawigacja — `e2e/notifications.spec.js`

1. ✅ Pasek trzyma kolejność z personalizacji.
2. ✅ Powyżej pięciu pozycji reszta chowa się pod „Więcej".
3. ✅ Krótka lista mieści się w całości na pasku.
4. ✅ Typy zadań i bonusy widzi tylko admin zespołu.
5. ✅ Reguły statusów i powiadomienia widzi tylko administrator aplikacji.
6. ✅ Widok domownika nie pojawia się na pasku.

## Sesja i strona główna

`session.spec.js`: współdzielone odświeżanie, odrzucenie wygasłej sesji i zachowanie tokenów przy przejściowej awarii API.
`home.spec.js`: układ sekcji, ranking, kalendarz, poprzednie tygodnie i portfel administratora prywatnej rodziny.
`real-api/mobile.spec.js`: logowanie JWT, wykonanie zadania i odtworzenie sesji z rzeczywistym backendem i osobną bazą SQLite.

## Zgodność z web i wygląd ekranów

`task-appearance.spec.js`: kalendarze domowników, układ dni, serie i oznaczenia ognia,
sekcje zadań oraz warianty widoku administratora i członka.

`web-parity.spec.js`: zmiana hasła, własny kolor i podgląd konfetti, schowek zaproszeń,
saldo i zakup z celu, daty dochodów i cele z terminem, portfel domownika, wypłata
pozostałej kwoty, historyczne rozliczenie, prywatny portfel, język przed logowaniem,
sesja bez zapamiętania, zapamiętywanie kalendarzy, przydzielanie i zaliczanie zadań.

`screen-audit.spec.js`: dziesięć głównych ekranów przy szerokości 320 px, brak błędów
renderowania i poziomego przepełnienia oraz ponawianie pobrania kanałów powiadomień.

`real-api/mobile.spec.js` obejmuje również dochód, cel, odkładanie, zakup z celu
oraz zmianę hasła i ponowne logowanie nowym hasłem.
