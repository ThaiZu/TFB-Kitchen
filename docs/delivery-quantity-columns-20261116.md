# Dostawy — zamówiono, przyjęto, różnica

Lista `/deliveries` pokazuje liczbę opakowań bez końcowych zer po separatorze dziesiętnym. Usuwamy wyłącznie zbędne zera; istniejące ilości ułamkowe nie są zaokrąglane ani zmieniane w danych.

Formularz przyjęcia ma trzy osobne kolumny: zamówiono, przyjęto oraz różnica (przyjęto minus zamówiono). Początkową wartością przyjęcia jest ilość zamówiona, nie ilość zadeklarowana w wysyłce przez dostawcę. Zmiana pola lub przycisków ± aktualizuje różnicę i podsumowanie. Nie wyświetlamy już przelicznika sztuk w opakowaniu ani powtórzonego podpisu zamówionej ilości pod nazwą produktu. Na mniejszych ekranach etykiety są powtarzane przy każdej pozycji.

Zamknięte i archiwalne dostawy zachowują ilości zapisane/zadeklarowane w API i nie mają kontrolek zapisu. Kontrakt API, uprawnienia pracownika, rewizja wysyłki, potwierdzenie zapisu, wykluczanie niepowiązanych produktów oraz blokada przy niepewnym wyniku pozostają zachowane. Bez migracji, zmian API, nowych zależności i zmian Panelu. Nie wprowadzano nowego zakazu ilości ułamkowych w istniejącym API/formularzu.

## Pliki

- `src/app/Views/deliveries/index.twig`, `receive.twig`: format liczb, kolumny, domyślne ilości i widok zamknięty; wersja zasobów zwiększona do 3.
- `public/assets/css/deliveries.css`: wspólne kolumny i wariant mobilny.
- `public/assets/js/deliveries.js`: liczba różnicy w osobnej kolumnie, zachowanie układu po zapisie.
- `bin/deliveries-test.php`, `deliveries-browser-test.cjs`: nowe asercje i zachowane regresje zapisu.

## Weryfikacja

86 sprawdzeń PHP PASS (renderowanie pięciu języków, domyślne ilości, format liczby, zabezpieczenia usługi i trasy). 200 sprawdzeń przeglądarkowych PASS przy 1024×768, 768×1024, 390×844 i 1280×900: różnice dodatnie/ujemne/zero, przyciski ±, ręczna edycja, potwierdzenie/anulowanie, dokładny payload, wykluczanie produktów, zmieniona rewizja, niepewny zapis, ostrzeżenie kosztów, utrata zmiany pracownika i podgląd zamkniętej dostawy. Brak poziomego przepełnienia i błędów JavaScript. Obejrzano zrzuty desktop i mobile.

Testy renderują rzeczywiste szablony i uruchamiają rzeczywisty JavaScript. Zapis HTTP jest atrapą, bez zmian produkcyjnej bazy. Pierwsze uruchomienie przeglądarki wymagało wspólnego katalogu fixture poza `/tmp` kontenera; usunięto również istniejący wyścig w teście przez oczekiwanie na pozycję po nawigacji (zachowując asercję dwóch wierszy).

```sh
/workspace/cloud-setup/php -r 'putenv("KITCHEN_DELIVERY_FIXTURE_DIR=/workspace/work/kitchen-delivery-quantities"); require "bin/deliveries-test.php";'
KITCHEN_DELIVERY_FIXTURE_DIR=/workspace/work/kitchen-delivery-quantities node bin/deliveries-browser-test.cjs
node --check public/assets/js/deliveries.js
git diff --check
```

## Odbiór po aktualizacji Kitchen

1. Lista dostaw: 10 opakowań ma być pokazane jako 10, bez „,00”.
2. Otwórz dostawę: zamówiono i przyjęto początkowo równe, różnica zero; brak przelicznika sztuk w opakowaniu. Sprawdź tablet i telefon.
3. Wpisz mniejszą i większą ilość, także przez ±: znak i różnica mają być poprawne. Anulowanie potwierdzenia nie zapisuje dostawy. Zapis potwierdza właściwe ilości; po ponownym otwarciu podgląd jest tylko do odczytu.
