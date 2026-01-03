# language: pl
Funkcja: Zarządzanie regułami zmian statusów
  Jako administrator rodziny
  Chcę móc definiować reguły zmian statusów zadań
  Aby kontrolować kiedy zadania mogą być przypisywane lub wykonywane

  Scenariusz: Tworzenie reguły okresu karencji po wykonaniu
    Zakładając że istnieje reguła zmiany statusu "Karencja 2 dni" która wymaga 2 dni karencji po zakończeniu
    Wtedy reguła zmiany statusu "Karencja 2 dni" powinna być aktywna
    I powinno być 1 aktywna reguła zmiany statusu

  Scenariusz: Tworzenie reguły wymagającej wykonania innego zadania
    Zakładając że istnieje reguła zmiany statusu "Wymóg poprzedniego zadania" dla zadania "Zadanie główne" która wymaga wykonania innego zadania dzisiaj
    Wtedy reguła zmiany statusu "Wymóg poprzedniego zadania" powinna być aktywna
    I powinno być 1 aktywna reguła zmiany statusu

  Scenariusz: Dezaktywacja reguły zmiany statusu
    Zakładając że istnieje reguła zmiany statusu "Stara reguła statusu" która wymaga 3 dni karencji po zakończeniu
    Kiedy reguła zmiany statusu "Stara reguła statusu" zostaje dezaktywowana
    Wtedy reguła zmiany statusu "Stara reguła statusu" powinna być nieaktywna
    I powinno być 0 aktywnych reguł zmiany statusu

  Scenariusz: Reaktywacja reguły zmiany statusu
    Zakładając że istnieje reguła zmiany statusu "Czasowa reguła statusu" która wymaga 1 dzień karencji po zakończeniu
    I reguła zmiany statusu "Czasowa reguła statusu" zostaje dezaktywowana
    Kiedy reguła zmiany statusu "Czasowa reguła statusu" zostaje aktywowana
    Wtedy reguła zmiany statusu "Czasowa reguła statusu" powinna być aktywna
    I powinno być 1 aktywna reguła zmiany statusu

  Scenariusz: Zarządzanie wieloma regułami zmiany statusu jednocześnie
    Zakładając że istnieje reguła zmiany statusu "Reguła statusu 1" która wymaga 1 dzień karencji po zakończeniu
    I istnieje reguła zmiany statusu "Reguła statusu 2" która wymaga 2 dni karencji po zakończeniu
    I istnieje reguła zmiany statusu "Reguła statusu 3" dla zadania "Specjalne zadanie" która wymaga wykonania innego zadania dzisiaj
    Wtedy powinno być 3 aktywne reguły zmiany statusu

    Kiedy reguła zmiany statusu "Reguła statusu 2" zostaje dezaktywowana
    Wtedy powinno być 2 aktywne reguły zmiany statusu

  Scenariusz: Reguły zmiany statusu z manipulacją czasem
    Zakładając że jest "2025-01-01 08:00:00"
    I istnieje reguła zmiany statusu "Reguła z czasem" która wymaga 5 dni karencji po zakończeniu
    Wtedy reguła zmiany statusu "Reguła z czasem" powinna być aktywna

    Kiedy mija 3 dni
    Wtedy reguła zmiany statusu "Reguła z czasem" powinna być aktywna

    Kiedy mija 5 dni
    I reguła zmiany statusu "Reguła z czasem" zostaje dezaktywowana
    Wtedy reguła zmiany statusu "Reguła z czasem" powinna być nieaktywna
