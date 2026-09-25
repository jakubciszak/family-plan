# Cofanie zadań i powiadomienia

Administrator zespołu może cofnąć ukończone zadanie oczekujące na zatwierdzenie.
Przycisk „Cofnij” jest dostępny obok „Zatwierdź” w głównej kolejce i na ekranie
domownika, zarówno w webie, jak i aplikacji mobilnej. Powód jest wymagany i może
mieć do 500 znaków. Błąd zapisu pozostawia dialog z wpisanym powodem.

Zadanie pozostaje przypisane do wykonawcy, ma oznaczenie „Cofnięte” i pokazuje
powód. Po poprawce wykonawca wybiera „Zgłoś ponownie”. Stary powód jest wtedy
usuwany. Cofnięcie nie przyznaje punktów. Zatwierdzonego zadania nie można
cofnąć tym przyciskiem; jego korekta nadal odbywa się w kalendarzu.

Powiadomienia obejmują przypisanie, zgłoszenie do akceptacji, cofnięcie,
zatwierdzenie, oddanie do puli, zmianę daty i usunięcie wykonania. Zgłoszenie
trafia do administratorów odpowiedzialnego zespołu, oddanie do puli także do
wykonawcy, a pozostałe zdarzenia do wykonawcy. Obowiązują ustawienia kanałów
użytkownika i polityki zdarzeń administratora aplikacji.

Migracja `Version20260918180000` dodaje kanały `in_app` i `push` do istniejących
polityk zgłoszenia i zatwierdzenia. Nie zmienia osobistych ustawień użytkowników.
Historia powiadomień pozostaje dostępna przez `/api/notifications` i listę pod dzwonkiem; oba
klienty pobierają nieprzeczytane wiadomości co 10 sekund oraz po powrocie do aplikacji.
Prośba o akceptację znika u wszystkich adminów, gdy ktokolwiek zadanie zatwierdzi, cofnie,
usunie albo gdy wróci ono do puli. Szczegóły w `docs/PUSH_NOTIFICATIONS.md`.

Android prosi o zgodę na alerty po zalogowaniu. W ustawieniach jest przycisk
ponownego włączenia zgody; po trwałej odmowie otwiera ustawienia systemowe.
Odmowa nie blokuje powiadomień wewnątrz aplikacji. Przy zamkniętej aplikacji
powiadomienia przychodzą przez Firebase Cloud Messaging, gdy wydanie ma
konfigurację Firebase; web korzysta z Web Push.
