# Plan dnia: implementacja

Implementacja jest na gałęzi `feature/day-planning`, opartej na wydaniu z PR #94. Zakres wynika z zaakceptowanej [propozycji](DAY_PLANNING_PROPOSAL.md), a szczegóły komunikacji opisuje [kontrakt API](DAY_PLANNING_API.md).

## Działanie

Web udostępnia kalendarz dnia i tygodnia, porównanie wybranych osób oraz wspólne terminy. Mobile używa agendy dnia i tygodnia z tymi samymi regułami dostępu i edycji. Oba klienty obsługują tagi osobiste i zespołowe, serie dzienne i tygodniowe, wyjątki, anulowanie i przywracanie wystąpień, uczestnictwo, strefy czasowe, DST oraz jawne potwierdzanie kolizji.

Prywatne szczegóły zna autor i osoby zaproszone do danego wystąpienia. Pozostali członkowie wspólnego zespołu dostają tylko przedział „Zajęty”. Administrator nie czyta więcej niż zwykły członek; jedyne, co może dodatkowo, to wpisać wydarzenie w kalendarz osoby ze swojego zespołu, nie uczestnicząc w nim. Serwer stosuje te zasady także do bezpośrednich adresów wydarzeń, filtrów, planera i powiadomień.

`DayPlanning` przechowuje definicje, statusy udziału i wyjątki. Zajętość jest wyliczaną projekcją tych danych dla wskazanego zakresu, bez osobnej trwałej tabeli blokad. Zapewnia to aktualność po zmianie udziału, wyjątku lub członkostwa. Repozytorium korzysta z PostgreSQL JSONB, a zapisy używają transakcji, blokady doradczej, wersjonowania oraz potwierdzeń związanych z konkretną treścią zmiany.

Wyjście z zespołu i odwołanie zaproszeń są atomowe. Push sprawdza uprawnienia ponownie przed dostarczeniem. Błąd powiadomienia po zatwierdzonym zapisie jest logowany i nie zamienia poprawnego zapisu wydarzenia w błąd klienta.

## Weryfikacja

- Pełny PHPUnit: 824 testy / 3177 asercji.
- Testy API: 18 scenariuszy dotyczących prywatności, cykli, zaproszeń, kolizji, idempotencji, nawigacji i powiadomień.
- Behat: 24 scenariusze / 148 kroków.
- Web: 16 nowych testów przeglądarkowych i 21 testów regresji; produkcyjny build.
- Mobile: 9 nowych testów przeglądarkowych i 19 testów regresji; TypeScript i lint.
- Oba interfejsy sprawdzone z prawdziwym API i oddzielną bazą PostgreSQL: utworzenie cyklu, prywatne zaproszenie, edycja pojedynczego dnia, zajętość innej osoby oraz planer.
- Android: poprawny eksport pakietu Hermes. Testy interfejsu mobile uruchomiono przez Expo web; nie zastępują testu zainstalowanego APK na urządzeniu.
- Nowe mapowania tabel odpowiadają migracjom; kontrola kontenera, YAML i `git diff --check` przechodzą.

## Uruchomienie

Wymagany jest PostgreSQL 16 zgodnie z konfiguracją projektu. Przed startem nowej wersji należy zastosować migracje `Version20260921120000` i `Version20260921140100` przez standardowe `make db-migrate`. Druga migracja dodaje Plan dnia do zapisanej nawigacji; użytkownik nadal może później ukryć ten widok.

Zapis cyklu sprawdza ograniczony horyzont kolizji, opisany w kontrakcie API. Kalendarz i planer zawsze zwracają pełne pokrycie żądanego zakresu, maksymalnie 90 dni. Integracja z Google Calendar, RSVP z akceptacją i edycja „to i kolejne” pozostają poza uzgodnioną pierwszą wersją.

Wdrożenie webu, instalacyjny APK i publikacja są oddzielnym krokiem wydania.
