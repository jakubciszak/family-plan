# Mój wygląd

Personalizacja mieszka w kontekście `UserSettings`, obok ustawień powiadomień. Jedna encja
`Personalisation` na użytkownika trzyma wszystko, co ktoś o sobie ustawił, i powstaje przy
pierwszym odczycie — nikt nie musi niczego zakładać ręcznie.

## Kolor

Motyw to jeden kolor (`#rrggbb`). Całą paletę Material 3 — jasną i ciemną, razem z rolami
`success` i `streak` — generuje przeglądarka z `@material/material-color-utilities`,
dokładnie tak samo jak robi to skrypt `npm run theme` przy budowaniu `color.css`. Wynik
ląduje w `<style id="own-theme">` jako nadpisanie zmiennych CSS, więc żaden komponent nie
musi wiedzieć, że kolor jest wybierany. Wybór trafia też do `localStorage`, żeby aplikacja
nie mrugała domyślną zielenią, zanim odpowie API.

## Avatar

Rysowane avatary robi `@dicebear/collection` lokalnie, w przeglądarce — czternaście stylów
razy dowolny seed. Nie idzie z tego żaden ruch na zewnątrz i nie ma czego trzymać: w bazie
siedzi tylko para `style` + `seed`.

Zdjęcie własne to osobna encja `OwnPicture`. Przeglądarka skaluje plik na canvasie przed
wysłaniem (avatar do 512 px, tło do 1600 px) i przekodowuje na JPEG, więc na serwer
trafia coś małego. Serwer nie wierzy na słowo: `getimagesizefromstring()` musi rozpoznać
obrazek i zwrócić jeden z trzech typów, a rozmiar ma się zmieścić w limicie. Bajty lądują
w bazie (base64 w kolumnie `text`), nie na dysku — dzięki temu wdrożenie zostaje
bezstanowe, a kopia zapasowa bazy obejmuje też obrazki. Podgląd wydaje kontroler, tylko
osobom z tego samego zespołu.

## Układ

`Layout` to uporządkowany wybór z listy znanych miejsc — pilnuje, żeby nie dało się
ustawić czegoś, czego nie ma, a to, co pominięto, zwraca jako ukryte. Tym samym typem
opisane są widgety strony głównej (`HOME_PLACES`) i zakładki paska (`NAV_PLACES`).
Domyślny układ strony głównej (`HOME_START`) celowo nie zawiera wszystkiego — tygodnie
domowników trzeba sobie dodać.

Dopisanie nowego miejsca do stałej jest bezpieczne: zapisane układy zostają ważne, nowe
miejsce po prostu nie jest w nich wybrane.

## Wejście

Personalizację otwiera się własną twarzą w pasku górnym. Dzięki temu nie zależy od tego,
czy ktoś zostawił sobie zakładkę na pasku dolnym — a zakładkę też można tam mieć.
