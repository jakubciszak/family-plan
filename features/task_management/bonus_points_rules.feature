# language: pl
Funkcja: Zarządzanie regułami punktów bonusowych
  Jako administrator rodziny
  Chcę móc tworzyć reguły punktów bonusowych
  Aby zachęcić członków rodziny do regularnego wykonywania zadań

  Scenariusz: Tworzenie i aktywowanie reguły miesięcznej liczby zadań
    Zakładając że istnieje reguła bonusowa "Mistrz miesiąca" która przyznaje 100 punktów bonusowych za wykonanie 20 zadań w miesiącu
    Wtedy reguła bonusowa "Mistrz miesiąca" powinna być aktywna
    I powinno być 1 aktywna reguła bonusowa

  Scenariusz: Tworzenie reguły kolejnych dni wykonywania zadań
    Zakładając że istnieje reguła bonusowa "Seria 5 dni" która przyznaje 50 punktów bonusowych za 5 kolejnych dni wykonywania zadań
    Wtedy reguła bonusowa "Seria 5 dni" powinna być aktywna
    I powinno być 1 aktywna reguła bonusowa

  Scenariusz: Dezaktywacja reguły bonusowej
    Zakładając że istnieje reguła bonusowa "Stara reguła" która przyznaje 30 punktów bonusowych za wykonanie 10 zadań w miesiącu
    Kiedy reguła bonusowa "Stara reguła" zostaje dezaktywowana
    Wtedy reguła bonusowa "Stara reguła" powinna być nieaktywna
    I powinno być 0 aktywnych reguł bonusowych

  Scenariusz: Reaktywacja reguły bonusowej
    Zakładając że istnieje reguła bonusowa "Czasowa reguła" która przyznaje 40 punktów bonusowych za wykonanie 15 zadań w miesiącu
    I reguła bonusowa "Czasowa reguła" zostaje dezaktywowana
    Kiedy reguła bonusowa "Czasowa reguła" zostaje aktywowana
    Wtedy reguła bonusowa "Czasowa reguła" powinna być aktywna
    I powinno być 1 aktywna reguła bonusowa

  Scenariusz: Zarządzanie wieloma regułami bonusowymi jednocześnie
    Zakładając że istnieje reguła bonusowa "Reguła 1" która przyznaje 50 punktów bonusowych za wykonanie 10 zadań w miesiącu
    I istnieje reguła bonusowa "Reguła 2" która przyznaje 75 punktów bonusowych za wykonanie 15 zadań w miesiącu
    I istnieje reguła bonusowa "Reguła 3" która przyznaje 100 punktów bonusowych za 7 kolejnych dni wykonywania zadań
    Wtedy powinno być 3 aktywne reguły bonusowe

    Kiedy reguła bonusowa "Reguła 2" zostaje dezaktywowana
    Wtedy powinno być 2 aktywne reguły bonusowe
    I wszystkie reguły bonusowe powinny być wylistowane

  Scenariusz: Reguły bonusowe z manipulacją czasem
    Zakładając że jest "2025-01-01 08:00:00"
    I istnieje reguła bonusowa "Noworoczny bonus" która przyznaje 200 punktów bonusowych za wykonanie 25 zadań w miesiącu
    Wtedy reguła bonusowa "Noworoczny bonus" powinna być aktywna

    Kiedy mija 15 dni
    Wtedy reguła bonusowa "Noworoczny bonus" powinna być aktywna

    Kiedy mija 20 dni
    I reguła bonusowa "Noworoczny bonus" zostaje dezaktywowana
    Wtedy reguła bonusowa "Noworoczny bonus" powinna być nieaktywna
