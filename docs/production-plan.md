# Plan produkcji z Panelu

Kitchen udostępnia `/production-plan` w menu i jako zakładkę obok dnia produkcyjnego. Widok jest tylko do odczytu. Trzy przyciski wybierają dzisiaj, jutro lub pojutrze (`offset=0..2`); szerszego zakresu ani dowolnej daty nie można wybrać.

Źródłem jest powtarzalny, siedmiodniowy plan Panelu `/production/weekly-template`. Aktywny wyjątek administratora dla danej daty ma pierwszeństwo przed dniem tygodnia, również gdy ustawia zero. Brak planu i zero nie tworzą pozycji. Kitchen pokazuje tylko dodatnie ilości oraz produkty dostępne w wybranym dniu według istniejącej reguły Availability Period (włącznie z okresami cyklicznymi przez przełom roku). Bez przypisanych okresów produkt jest całoroczny. Wybrany na tablecie dział ogranicza listę do niego i produktów nieprzypisanych.

Katalog jest ten sam co w planie Panelu: aktywne produkty sztukowe, włączone do planowania i przypisane do sklepu. Produkcja wagowa Kitchen pozostaje dostępna ręcznie; ten plan nie dodaje obsługi wag do Panelu.

## Dzisiejsza produkcja

Przy pierwszym wczytaniu nieotwartego, bieżącego dnia Kitchen pobiera plan dzisiejszy i uzupełnia nim wyłącznie `production_quantity` w kroku „Dzisiejsza produkcja”. Produkty z dodatnim planem pojawiają się także przy zerowym stanie i bez obowiązkowego asortymentu. Zapasy z wczoraj i ilości produkcji nocnej nie są zwiększane przez plan.

Wartości są edytowalne. Przejście między krokami i filtrowanie nie przywracają wartości planowanych. Plan oznacza pełną ilość do produkcji; nie pomniejsza się jej automatycznie o zapas z wczoraj. Sam podgląd i wypełnienie formularza nie zapisują stanów. Do magazynu trafiają dopiero ilości potwierdzone przy otwarciu dnia, poprzez istniejący zapis Kitchen.

Zaległy dzień nie dostaje planu dzisiejszego. Otwartego dnia nie uzupełnia się ponownie planem. Awaria planu nie blokuje ręcznego otwarcia dnia: krok dzisiejszej produkcji pokazuje komunikat o konieczności ręcznego wpisania lub ponownego wczytania strony. Odróżnia się to od poprawnego, pustego planu.

## API i kolejność wdrożenia

Nowy odczyt: `GET /shops/{shop}/production-plan?offset=0&sector_id=1`. Parametr działu jest opcjonalny. Uwierzytelnienie i zakres sklepu urządzenia są takie jak przy odczycie dnia produkcyjnego. Tokeny klientów i sesje Panelu bez urządzenia są odrzucane. PIN pracownika nie jest wymagany do samego odczytu. Istniejące edytujące endpointy Panelu pozostają bez zmian.

Odpowiedź zawiera `shop_id`, `today`, `date`, `offset`, `timezone`, `sector_id` i `products` z ilościami oraz źródłem (`weekly_template` / `date_override`). Data jest wyliczana po stronie API w strefie sklepu. Żądanie nie odczytuje statystyk sprzedaży i nie zapisuje danych; relacje okresów i kategorii są pobierane zbiorczo.

Najpierw należy wdrożyć API, następnie Kitchen. Brak nowej migracji — wymagane są istniejące tabele planu tygodniowego i wyjątków kalendarza. Zmiany nie zostały wdrożone w ramach tego zadania.

## Weryfikacja

Testy jednostkowe API obejmują wyjątki (również zero), dostępność datową, trzydniowy zakres, działy, brak planu i brak zapisów/statystyk. Kontrakty obejmują kontener, trasę i ograniczenie dostępu urządzenia do sklepu. Kitchen sprawdza proxy, ilości domyślne, stan niedostępny i renderowanie w pięciu językach. Chromium obejmuje trzy układy ekranu, przełączanie dni, ręczne korekty, produkty o zerowym stanie oraz brak zapisów przed otwarciem. Przeszła również regresja kreatora, zamknięcia zaległości i przygotowania na jutro.

Nie uruchamiano testów z rzeczywistym MySQL, POS ani działającym zdalnym API; użyto atrap danych i transportu. Nie testowano fizycznych tabletów ani pełnego zestawu niezwiązanych modułów.
