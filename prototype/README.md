# Family Plan - Web App Prototype

## Opis

To jest w pełni funkcjonalny prototyp aplikacji webowej Family Plan z wbudowanymi mockami danych. Prototyp działa całkowicie samodzielnie - bez żadnych zależności zewnętrznych, serwerów czy baz danych.

## Jak uruchomić

1. **Otwórz plik bezpośrednio w przeglądarce:**
   - Otwórz plik `family-plan-prototype.html` w dowolnej nowoczesnej przeglądarce (Chrome, Firefox, Safari, Edge)
   - Alternatywnie: przeciągnij i upuść plik do okna przeglądarki

2. **Gotowe!** Aplikacja jest w pełni funkcjonalna i gotowa do użycia.

## Konta demo

Prototyp zawiera gotowe konta testowe:

### Konto administratora:
- **Email:** `admin@family.com`
- **Hasło:** `admin`
- **Uprawnienia:** Pełny dostęp - tworzenie zadań, zespołów, zarządzanie bonusami

### Konto użytkownika (dziecko):
- **Email:** `child1@family.com`
- **Hasło:** `child1`
- **Uprawnienia:** Wykonywanie przypisanych zadań, zdobywanie punktów

### Inne konta:
- `parent@family.com` / `parent` (Admin)
- `child2@family.com` / `child2` (User)
- `user@family.com` / `user` (User)

## Funkcjonalności

### ✅ Dla wszystkich użytkowników:
- **Logowanie/Rejestracja** - system autentykacji
- **Lista zadań** - przeglądanie przypisanych zadań
- **Filtrowanie zadań** - według statusu (wszystkie, oczekujące, ukończone, zatwierdzone)
- **Wykonywanie zadań** - użytkownicy mogą oznaczać zadania jako ukończone
- **System punktów** - automatyczne naliczanie punktów za zatwierdzone zadania
- **Zarządzanie zespołami** - przeglądanie członków zespołu i ich punktów
- **Ustawienia profilu** - zmiana imienia i hasła
- **Wielojęzyczność** - przełączanie między polskim i angielskim

### 👑 Dodatkowo dla administratorów:
- **Tworzenie zadań** - z konfigurowalnymi punktami (0-1000) i częstotliwością
- **Przypisywanie zadań** - do członków zespołu
- **Zatwierdzanie zadań** - weryfikacja ukończonych zadań
- **Zarządzanie zespołami** - tworzenie nowych zespołów
- **Zasady bonusów** - konfiguracja bonusów za serie zadań lub miesięczne osiągnięcia
- **Zasady statusów** - przeglądanie reguł zmiany statusów zadań

## Częstotliwość zadań

Aplikacja wspiera różne częstotliwości zadań:
- **Jednorazowo** (once) - zadanie do wykonania raz
- **Codziennie** (daily) - zadania codzienne
- **Tygodniowo** (weekly) - zadania tygodniowe
- **Miesięcznie** (monthly) - zadania miesięczne

## Statusy zadań

Zadania przechodzą przez następujące statusy:
1. **Pending** (Oczekujące) - nowo utworzone zadanie
2. **Completed** (Ukończone) - użytkownik oznaczył jako ukończone
3. **Approved** (Zatwierdzone) - admin zatwierdził, punkty zostały przyznane

## Przechowywanie danych

- Wszystkie dane są przechowywane w **localStorage** przeglądarki
- Dane pozostają zapisane nawet po zamknięciu przeglądarki
- Aby zresetować dane do stanu początkowego: otwórz konsolę przeglądarki i wykonaj `localStorage.clear()`, następnie odśwież stronę

## Funkcje responsywne

Prototyp jest w pełni responsywny:
- **Desktop** - pełny widok z nawigacją horyzontalną
- **Tablet/Mobile** - menu hamburgerowe, układy kolumnowe

## Technologie

Prototyp jest zbudowany przy użyciu:
- **Vanilla JavaScript** - bez zewnętrznych bibliotek
- **CSS3** - nowoczesne style z CSS Variables
- **HTML5** - semantyczny markup
- **LocalStorage API** - trwałe przechowywanie danych

## Mockowane dane

Prototyp zawiera przykładowe dane:
- 5 użytkowników (2 adminów, 3 użytkowników)
- 7 zadań w różnych statusach
- 2 zespoły rodzinne
- 3 zasady bonusów
- Zasady zmiany statusów

## Możliwości rozbudowy

Ten prototyp może być podstawą dla:
- Integracji z prawdziwym API backend
- Dodania powiadomień push
- Implementacji zaawansowanych statystyk
- Dodania kalendarza zadań
- Systemu osiągnięć (achievements)

## Uwagi techniczne

- Plik jest całkowicie samodzielny - zawiera wszystko w jednym HTML
- Nie wymaga serwera - działa jako plik lokalny
- Kompatybilny z nowoczesnymi przeglądarkami (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- Wielkość pliku: ~61KB

## Wsparcie

Jeśli masz pytania lub sugestie dotyczące prototypu, skontaktuj się z zespołem deweloperskim Family Plan.

---

**Wersja prototypu:** 1.0.0
**Data utworzenia:** 2026-01-17
**Licencja:** Proprietary
