# Kieszonkowe

Kontekst `Allowance` prowadzi pieniądze dzieci osobno od punktów. Punkty zostają w
`PointsManagement` — kieszonkowe tylko je czyta i przelicza na złotówki.

## Księga

Model jest tym, co Fowler opisuje jako Account / Entry / Transaction. Każda operacja to
transakcja, która sumuje się do zera: ile z jednego konta zeszło, tyle na drugie weszło.
Nieuzgodniona transakcja nie ma prawa się zapisać — `MoneyTransaction::seal()` to sprawdza.

Konta użytkownika:

| Konto        | Co trzyma                                        | Może zejść poniżej zera |
|--------------|--------------------------------------------------|-------------------------|
| `pending`    | zarobione, czeka na wypłatę                      | nie                     |
| `available`  | pieniądze na ręku, do wydania                    | nie                     |
| `goal`       | odłożone na konkretny cel (po koncie na cel)     | nie                     |
| `earnings`   | źródło kieszonkowego (przeciwwaga rozliczeń)     | tak                     |
| `income`     | źródło dochodów spoza systemu                    | tak                     |
| `expenses`   | to, co zostało wydane                            | tak                     |

Przepływy:

```
zamknięcie tygodnia   earnings  → pending
wypłata (potwierdzona) pending  → available
własny dochód          income   → available
wydatek                available → expenses
odłożenie na cel       available → goal
zdjęcie z celu         goal      → available
zakup celu             goal      → expenses
```

Kwoty trzymamy w groszach (`bigint`), nigdy w liczbach zmiennoprzecinkowych. Walutę ustawia
`ALLOWANCE_CURRENCY`.

Transakcja, którą zakłada system, nie trzyma gotowego zdania — `description` zostaje puste,
a to, czego dotyczyła (tydzień, nazwa celu), ląduje w `context`. Zdanie składa front z klucza
tłumaczenia, więc księga mówi w języku, który czyta dziecko. `description` wypełnia się tylko
tym, co wpisał człowiek: opisem wydatku, dochodu albo notatką przy wypłacie.

## Przelicznik

`AllowanceRule` jest jedna na zespół i typ konta punktowego (`tasks`, `bonuses`). Składa się
z progu (`minimumPoints`) i kursu zapisanego jako para liczb całkowitych: kwota za N punktów.
Para zamiast ułamka dziesiętnego daje dokładne przeliczenie bez zaokrągleń po drodze —
„5 zł za 3 punkty" to dokładnie 500/3, a zaokrąglenie (w górę od połowy) następuje raz, na
końcu. Punkty poniżej progu nie dają nic; próg jest bramką, nie kwotą wolną.

## Zamknięcie tygodnia i wypłata

To dwie osobne decyzje, bo w domu wyglądają inaczej. Zamknąć da się wyłącznie tydzień,
który się już skończył — zamknięcie odcina dopisywanie zadań wstecz, więc trwający tydzień
nie może zostać zamknięty. Zamknięcie (`WeekClosure`) liczy kwotę, zapisuje rozbicie na typy
kont i księguje ją na `pending`; od tej chwili w tym tygodniu nie da się już dopisać zadania
wstecz (`ClosedWeeksInterface`). Wypłatę
(`Payout`) proponuje dorosły — całość albo część, bo nie zawsze ma całą gotówkę — a dziecko
ją potwierdza. Dopiero potwierdzenie przesuwa pieniądze na `available`.

Tydzień da się otworzyć z powrotem, dopóki pieniądze nie zdążyły wyjść z `pending`.
