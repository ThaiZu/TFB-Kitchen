# Karty produktów Kitchen

`/knowledge/products` pokazuje kompaktowe karty z miniaturą po lewej (1/3 szerokości), nazwą i kategorią po prawej. Karty i filtry mają zaokrąglenie 10 px, tak jak globalne przyciski wyboru pracownika i działu. Usunięto z listy czas przechowywania, miejsce składowania, oznaczenia dodatkowe i stopkę karty; szczegóły produktu pozostają dostępne po kliknięciu.

Filtry łączą się przez AND: trwałość (domyślnie SSL), sekcja, grupa kategorii („Kategoria”) i kategoria produktu („Podkategoria”), a także wyszukiwanie nazwy. Grupy mogą zawierać tę samą podkategorię i produkty mogą należeć do wielu grup. Filtrowanie używa identyfikatorów, więc jednakowe nazwy nie łączą różnych kategorii. Zmiana grupy zeruje wybraną podkategorię. Sekcje i obie kategorie są zwinięte pod przyciskiem z aktualnym wyborem; rozwijają badge’e z możliwością wyboru na tablecie. Wybór „Wszystkie” usuwa ograniczenie na danym poziomie. Zliczanie wyników i pusty wynik aktualizują się bez żądań sieciowych. Filtry są lokalne dla listy; nie zmieniają wybranego globalnie działu tabletu.

Kitchen zachowuje z istniejącego API `sector_id`, `sector_name`, `category.groups`, `id_category` i `shelf_life_category` w modelu listy. Odczyt `/shops/{shop}/products/available?include=kitchen_cards` dodaje zdjęcie produktu z tego samego serwisu co WebShop (`webshop_image.url`, zgodne pole `main_photo_path`). Zdjęcia pochodzą z `file_attachment` typu `product_webshop_image` i są pobierane zbiorczo; nie są to zdjęcia receptury. Brak `include=kitchen_cards` nie wykonuje tego zapytania i nie dodaje pola (bez zmian danych POS/WebShop).

Miniatury korzystają z istniejącej obsługi `r2://`, mają lazy loading i zachowują kompaktowy placeholder przy braku lub błędzie zdjęcia. Nie przetwarzano obrazów ani nie tworzono nowych wariantów. Wspólne zaokrąglenia przycisków wyboru pracownika i sekcji przeniesiono z CSS dnia produkcyjnego do globalnego `atelier-theme.css`, którego URL zależy od hasha zawartości.

Zmiany w Kitchen i API, bez migracji. API należy wdrożyć przed Kitchen, aby zdjęcia pojawiły się również na liście. Nie wykonywano deploymentu.

Testy: PHP renderowania w pięciu językach, PHPUnit opcjonalnego pobierania zdjęć i zgodności dotychczasowych odczytów, Chromium w układach 1024/768/390 px (połączone filtry, domyślny SSL, różne ID przy takich samych nazwach kategorii, zdjęcia/poprawny fallback, proporcje i globalne narożniki). Testy korzystają z atrap transportu i danych. Nie uruchamiano rzeczywistej bazy, zdalnego API, R2 ani fizycznych tabletów; nie uruchamiano całego niezwiązanego zestawu testów.

## Układ mobilny

Poniżej 1200 px lub na urządzeniu dotykowym bez hover nad listą pozostają tylko wyszukiwarka, wybór trwałości i przycisk „Filtry” z liczbą aktywnych ustawień sekcji/kategorii. Podtytuł strony jest ukryty. Pasek jest sticky pod rzeczywistą wysokością górnego navbara, aktualizowaną również przy obrocie urządzenia.

Sekcja, kategoria i podkategoria przenoszą się do natywnego dialogu wysuwanego od dołu. Wybory działają na bieżąco; „Pokaż produkty” zamyka panel. „Wyczyść” usuwa wyszukiwanie i wszystkie ograniczenia (również trwałość). Początkowo nadal wybrany jest SSL. Panel można zamknąć przyciskiem, Escape albo kliknięciem tła; fokus wraca do przycisku filtrów. Zmiana na widok komputerowy zamyka dialog i przywraca te same kontrolki nad listę, zachowując ustawienia.

W kompaktowym widoku lista zawiera dwie karty w wierszu, a poniżej 360 px jedną. Obraz nadal zajmuje jedną trzecią karty. Desktop od 1200 px z precyzyjnym wskaźnikiem zachowuje filtry nad listą. Zmiana mobilna dotyczy wyłącznie Kitchen, bez API i migracji. Testy Chromium obejmują także panel modalny, czyszczenie, zachowanie wyboru po zmianie szerokości oraz sticky pasek na długiej liście.


## Poprawka filtrów i źródła obrazów

Poprzedni próg 767 px pozostawiał stary układ na tabletach 768/1024 px. CSS i JS używają teraz tego samego warunku: maksymalnie 1199 px lub `(hover:none) and (pointer:coarse)`. Urządzenie dotykowe pozostaje w kompaktowym trybie także po obrocie do 1280 px lub więcej. URL arkusza i skryptu zawiera hash ich zawartości, aby cache service workera nie utrzymywał wcześniejszej wersji. HTML listy wysyłany jest z `Cache-Control: private, no-store`.

Wcześniejsze podłączenie zdjęcia receptury nie odpowiadało źródłu używanemu przez WebShop. Poprawione API korzysta z `ProductWebshopImageService`. Gdy nie ma obrazu API, model używa kopii lokalnego zdjęcia WebShopu po ID, zgodnie z adapterem WebShop. Paczka zawiera 114 obrazów (około 14 MB), skopiowanych bez zmiany bajtów ze źródła WebShop `7246cfd`; pliki są ładowane leniwie. Nazwy zawierają hash, więc ich aktualizacja nie pozostawia starej miniatury w cache.

Aktualizacja lokalnej paczki: `python bin/sync-webshop-product-images.py /sciezka/TFB-WebShop/assets/product_pictures`. Skrypt tworzy manifest i kopiuje istniejące pliki; nie przetwarza obrazów ani nie usuwa plików użytkownika. Nowe zdjęcia w API są odczytywane na bieżąco i mają pierwszeństwo. Wdrożenie tej poprawki: API przed Kitchen, bez migracji.

Nieudane lub nieprawidłowe odpowiedzi katalogu nie są już zamieniane na pustą listę. Widok pokazuje komunikat błędu i link ponowienia. Poprawny pusty katalog nadal jest pusty. Jeśli katalog zawiera produkty, ale filtry (w tym domyślny SSL) nic nie pokazują, dostępny jest przycisk „Pokaż wszystkie produkty”. Bez dostępu do działającego API nie stwierdzono, który błąd upstream wystąpił u użytkownika.
