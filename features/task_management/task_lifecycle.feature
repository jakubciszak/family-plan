# language: pl
Funkcja: Pełny cykl życia zadania
  Jako administrator rodziny
  Chcę móc zarządzać zadaniami domowymi
  Aby członkowie rodziny mogli wykonywać zadania i zdobywać punkty

  Scenariusz: Tworzenie, przypisywanie, wykonywanie i zatwierdzanie zadania
    Zakładając że istnieje administrator "Mama"
    I istnieje członek rodziny "Janek"
    I "Mama" tworzy zadanie "Zmywanie naczyń" warte 50 punktów
    Kiedy "Mama" przypisuje zadanie "Zmywanie naczyń" do "Janek"
    Wtedy zadanie "Zmywanie naczyń" powinno mieć status "new"
    I "Janek" powinien nie mieć jeszcze portfela

    Kiedy "Janek" kończy zadanie "Zmywanie naczyń"
    Wtedy zadanie "Zmywanie naczyń" powinno mieć status "completed"
    I "Janek" powinien nie mieć jeszcze portfela

    Kiedy "Mama" zatwierdza zadanie "Zmywanie naczyń"
    Wtedy zadanie "Zmywanie naczyń" powinno mieć status "approved"
    I "Janek" powinien mieć 50 punktów

  Scenariusz: Wielu członków rodziny wykonuje zadania niezależnie
    Zakładając że istnieje administrator "Tata"
    I istnieją następujący członkowie rodziny:
      | name   |
      | Ania   |
      | Bartek |
      | Czarek |
    I zostały utworzone następujące zadania:
      | task              | points | description                     |
      | Odkurzanie        | 40     | Odkurzyć cały dom               |
      | Wynoszenie śmieci | 30     | Wynieść śmieci do pojemników    |
      | Podlewanie kwiatów| 20     | Podlać wszystkie rośliny        |

    Kiedy "Tata" przypisuje zadanie "Odkurzanie" do "Ania"
    I "Tata" przypisuje zadanie "Wynoszenie śmieci" do "Bartek"
    I "Tata" przypisuje zadanie "Podlewanie kwiatów" do "Czarek"
    I "Ania" kończy zadanie "Odkurzanie"
    I "Bartek" kończy zadanie "Wynoszenie śmieci"
    I "Czarek" kończy zadanie "Podlewanie kwiatów"
    I "Tata" zatwierdza zadanie "Odkurzanie"
    I "Tata" zatwierdza zadanie "Wynoszenie śmieci"
    I "Tata" zatwierdza zadanie "Podlewanie kwiatów"

    Wtedy "Ania" powinien mieć 40 punktów
    I "Bartek" powinien mieć 30 punktów
    I "Czarek" powinien mieć 20 punktów

  Scenariusz: Tylko administrator może zatwierdzać zadania
    Zakładając że istnieje administrator "Mama"
    I istnieje członek rodziny "Janek"
    I istnieje członek rodziny "Kasia"
    I "Mama" tworzy zadanie "Sprzątanie pokoju" warte 60 punktów
    I "Mama" przypisuje zadanie "Sprzątanie pokoju" do "Janek"
    I "Janek" kończy zadanie "Sprzątanie pokoju"

    Kiedy "Kasia" zatwierdza zadanie "Sprzątanie pokoju"
    Wtedy operacja powinna się nie udać z "Only administrators can approve tasks"

  Scenariusz: Kumulowanie punktów z wielu zadań
    Zakładając że jest "2025-01-01 08:00:00"
    I istnieje administrator "Tata"
    I istnieje członek rodziny "Ania"
    I "Tata" tworzy zadanie "Zadanie 1" warte 100 punktów
    I "Tata" tworzy zadanie "Zadanie 2" warte 150 punktów
    I "Tata" tworzy zadanie "Zadanie 3" warte 200 punktów

    Kiedy "Tata" przypisuje zadanie "Zadanie 1" do "Ania"
    I "Ania" kończy zadanie "Zadanie 1"
    I "Tata" zatwierdza zadanie "Zadanie 1"
    Wtedy "Ania" powinien mieć 100 punktów

    Kiedy mija 1 dzień
    I "Tata" przypisuje zadanie "Zadanie 2" do "Ania"
    I "Ania" kończy zadanie "Zadanie 2"
    I "Tata" zatwierdza zadanie "Zadanie 2"
    Wtedy "Ania" powinien mieć 250 punktów

    Kiedy mijają 2 dni
    I "Tata" przypisuje zadanie "Zadanie 3" do "Ania"
    I "Ania" kończy zadanie "Zadanie 3"
    I "Tata" zatwierdza zadanie "Zadanie 3"
    Wtedy "Ania" powinien mieć 450 punktów
