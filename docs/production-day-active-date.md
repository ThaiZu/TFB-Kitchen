# Jeden aktywny dzień produkcyjny

Kitchen nie udostępnia już wyboru daty dnia produkcyjnego. Przy tytule znajduje się nieedytowalna data obsługiwanego dnia (również przy odświeżeniu po zapisie).

Dzień wskazuje istniejące API, według strefy czasowej sklepu i wybranego działu. Bez zaległości jest to dzień bieżący. Zaległy otwarty dzień uruchamia wyłącznie zamknięcie: pozostałe kroki, w tym przygotowanie na jutro, są zablokowane. Rozliczenie fizycznego magazynu, odpady i potwierdzone zamknięcie pustego magazynu pozostają dostępne, gdy wymaga tego stan rejestru. Po zamknięciu Kitchen wraca do `/production-day`, ponownie prosząc API o aktywny dzień.

Stare adresy `/production-day?date=...` przekierowują do strony bez daty. Odczyt `/production-day/data` ignoruje datę przesłaną przez starą wersję interfejsu. Użytkownik nie wybiera dnia historycznego ani przyszłego. Wskazanie konkretnej daty przez API jako dnia wymagającego domknięcia jest obsługiwane wewnętrznie.

Blokada przygotowania jest również sprawdzana w serwerowym proxy Kitchen. Zapis zamknięcia nadal korzysta z dotychczasowych kontroli API (wersja, magazyn, potwierdzenia i idempotencja). Nie zmieniono autoryzacji ani zasad rozliczania produktów.

Zmiana wyłącznie w Kitchen, bez migracji i zmian API. Testy PHP korzystają z atrap klienta API, a testy Chromium z odpowiedzi na danych testowych. Nie wykonywano testów na rzeczywistych tabletach, produkcyjnej bazie ani działającej integracji POS/API.
