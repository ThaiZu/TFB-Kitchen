> Aktualizacja: [jeden aktywny dzień produkcyjny](production-day-active-date.md) zastępuje opisany niżej picker nieedytowalną datą.

# Uproszczony interfejs Kitchen

- Pulpit zawiera cztery odnośniki: dzień produkcyjny, karty produktów, checklisty i zamówienia. Nie pokazuje nagłówka ani podsumowań zadań.
- Menu nie zawiera ogólnej bazy wiedzy; karty produktów pozostają dostępne.
- Kitchen zawsze używa lokalnej konfiguracji Production, z pełnym zestawem funkcji tego trybu. Dawne ciasteczka trybu i zdalnej konfiguracji nie zmieniają nawigacji. Aplikacja nie pobiera `/devices/me/config` i nie pokazuje banera brakujących API.
- Ustawienia zachowują dźwięki i dane urządzenia; usunięto wybór trybu, adres WebShop i token. Stary POST `/me/settings` nie zapisuje tych pól. Autoryzacja urządzenia i pracownika pozostaje bez zmian.
- Checklisty nie pokazują nagłówka strony ani zbiorczego panelu wybranej checklisty. Zadania i ich obsługa pozostają dostępne. `/checklist` jest aliasem `/checklists`.
- Data dnia produkcyjnego znajduje się przy tytule. Zmiana daty automatycznie ładuje stronę, także przy niedostępnym API. Niezapisane zmiany wymagają potwierdzenia porzucenia.
- Usunięto zwykłe przyciski „Pokaż” i „Odśwież stany” oraz normalne komunikaty `pd-notice`. Błędy i bezpieczne ponowienie są dostępne w osobnym, domyślnie ukrytym `pd-feedback`. Po potwierdzonym zapisie i nieudanym odczycie ponowienie wykonuje wyłącznie GET, bez powtórnego zapisu.

Zmiana dotyczy wyłącznie Kitchen. Nie wymaga migracji ani zmiany API.
