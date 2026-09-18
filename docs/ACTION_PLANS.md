# Plany działania

Funkcja webowa dostępna w nawigacji jako „Plany działania”. Każdy użytkownik może tworzyć plany prywatne, niezależnie od członkostwa w zespołach. W edytorze wybiera „Tylko mój” albo jeden ze swoich zespołów. Plan prywatny widzi i zmienia wyłącznie autor. Plan zespołowy mogą uruchamiać wszyscy członkowie zespołu, a zmieniać i usuwać autor oraz administrator tego zespołu. Tylko autor może zmienić zakres udostępniania. Po opuszczeniu zespołu traci dostęp do jego planów.

Plan zawiera uporządkowane kroki. Każdy krok może mieć etapy. Podczas wykonywania krok z etapami jest zastępowany jego etapami, a jego nazwa pozostaje widoczna jako kontekst. Kroki i etapy można dodawać, usuwać i przestawiać.

Opcjonalny czas całego planu jest dzielony równo między wykonywane czynności. Przykładowo dwa samodzielne kroki i krok z trzema etapami oznaczają pięć czynności. Plan na 20 minut daje po 4 minuty na czynność. Liczniki całego planu i bieżącej czynności można przełączyć na czas upływający. Osiągnięcie zera nie kończy czynności ani nie przenosi użytkownika dalej.

Przypomnienie pojawia się po upływie czasu przypisanego do czynności, co najmniej co 30 sekund. Bez czasu planu domyślny odstęp to 10 minut, zmieniany w edytorze. Po pierwszym przypomnieniu dźwięk powtarza się co 15 sekund, aż użytkownik zareaguje. „Jeszcze pracuję nad tym” odracza następne przypomnienie, a „Dalej” zeruje zegar czynności i odstęp przypomnienia. Pauza zatrzymuje oba zegary i przypomnienia. Można wyłączyć sam dźwięk.

Plany są przechowywane w bazie. Bieżące wykonanie jest zapisywane w localStorage osobno dla każdego użytkownika, razem z kopią planu z momentu uruchomienia. Zmiana lub usunięcie planu nie zmienia rozpoczętego wykonania. Można zachować jedno wykonanie na użytkownika w danej przeglądarce. Rozpoczęcie innego wymaga potwierdzenia zastąpienia postępu.

Po odświeżeniu wykonanie wraca jako wstrzymane. Jeśli przed odświeżeniem było uruchomione, czas do momentu ponownego otwarcia strony wlicza się do wykonania. Przycisk „Zatrzymaj i wróć do planów” pozwala wcześniej zatrzymać czas. Postęp wykonania nie jest synchronizowany między urządzeniami. Po zakończeniu wykonania jego lokalny zapis jest usuwany; zapisany plan można uruchomić ponownie.

Dźwięk jest inicjowany po kliknięciu przycisku przez użytkownika. Przypomnienia wymagają otwartej strony. Przeglądarka może opóźniać je w tle, a uśpione urządzenie nie odtwarza dźwięku. Brak obsługi dźwięku nie blokuje przypomnienia na ekranie.

Edytor pozwala wybrać i odsłuchać delikatny ton, dzwonek, podwójny sygnał lub krótką melodię. Wybór jest zapisywany w planie i jego kopii przy zadaniu. Podczas wykonywania można zmienić dźwięk tylko dla bieżącego uruchomienia; ta zmiana jest zachowywana razem z postępem. Starsze plany i wykonania używają delikatnego tonu.

## Plany przy zadaniach

Administrator zespołu wybiera plan w formularzu tworzenia lub edycji typu zadania. Dostępne są tylko plany tego samego zespołu. Prywatny plan trzeba najpierw świadomie udostępnić zespołowi. Plan podpięty do typu zadania można edytować, ale przed usunięciem lub zmianą jego zakresu trzeba go odpiąć od wszystkich typów zadań.

Pobranie zadania lub przypisanie go przez administratora zapisuje kopię podpiętego planu w wykonaniu zadania. Późniejsze zmiany planu i jego odpięcie nie zmieniają tej kopii. Własne otwarte i odrzucone zadania z planem pokazują przycisk „Pokaż plan działania”. Po podglądzie można wybrać „Do dzieła”.

Ostatnia czynność wysyła zwykłe żądanie ukończenia powiązanego wykonania zadania. Zatwierdzenie i przyznawanie punktów pozostają w dotychczasowym przebiegu. Błąd zapisu jest widoczny, a zakończony plan pozostaje w pamięci przeglądarki do ponowienia zapisu. Odświeżenie strony nie gubi tego powiązania. Ponowne żądanie ukończenia nie zmienia daty już ukończonego zadania ani nie dubluje skutków. Serwer nadal sprawdza przypisanie zadania i uprawnienia wykonującej je osoby.

## API

Wszystkie endpointy wymagają zalogowania z rolą `ROLE_USER`.

| Metoda | Ścieżka | Wynik |
| --- | --- | --- |
| GET | `/api/action-plans` | `{"plans": [...]}` z prywatnymi planami użytkownika i planami jego zespołów |
| POST | `/api/action-plans` | Zapisany plan, status 201 |
| PUT | `/api/action-plans/{id}` | Zmieniony plan, status 200 |
| DELETE | `/api/action-plans/{id}` | Status 204 |

POST i PUT przyjmują `name`, `steps` (lista obiektów `name` i `stages`, gdzie `stages` to lista nazw), opcjonalne `estimatedMinutes` oraz `reminderMinutes` z domyślną wartością 10. Opcjonalne `teamId` wybiera zespół; `null` oznacza plan prywatny. Odpowiedź zawiera `canManage` i `canChangeScope`. Nie można podać identyfikatora właściciela. Próba zmiany planu bez uprawnienia zwraca 404. Typy zadań przyjmują `actionPlanId` (albo `null`, aby odpiąć plan), a wykonania zadań zwracają kopię w `actionPlan`.

Nazwa planu ma do 160 znaków, nazwa kroku lub etapu do 240. Dopuszczalne jest 1–100 kroków, do 100 etapów w kroku i do 500 wykonywanych czynności łącznie. Czas planu wynosi 1–1440 minut albo `null`, odstęp przypomnień 1–120 minut.

## Uruchomienie

Pole API `reminderSound` przyjmuje `soft`, `bell`, `double` lub `melody`. Pominięcie pola podczas edycji zachowuje dotychczasowy wybór. Migracja `Version20260918160000` dodaje to ustawienie z domyślną wartością `soft`.

Migracja `Version20260918090000` tworzy tabelę `action_plans` i dodaje nową pozycję do zapisanych układów nawigacji. Migracja `Version20260918140000` dodaje zakres zespołowy, powiązanie typu zadania i kopię planu w wykonaniu. W środowisku docelowym uruchom standardowe `make db-migrate` i zbuduj frontend. Wersja mobilna nie jest zmieniana.

Testy funkcji: `tests/ActionPlanning/ActionPlanTest.php`, `tests/Api/ActionPlanApiTest.php`, `tests/Api/TeamActionPlanApiTest.php` i `frontend/tests/e2e/action-plans.spec.js`. Testy API uruchamiają migracje w bazie testowej; testy przeglądarkowe używają atrap API i kontrolowanego zegara.
