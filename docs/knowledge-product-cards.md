# Karty produktów Kitchen

`/knowledge/products` pokazuje kompaktowe karty z miniaturą po lewej (1/3 szerokości), nazwą i kategorią po prawej. Karty i filtry mają zaokrąglenie 10 px, tak jak globalne przyciski wyboru pracownika i działu. Usunięto z listy czas przechowywania, miejsce składowania, oznaczenia dodatkowe i stopkę karty; szczegóły produktu pozostają dostępne po kliknięciu.

Filtry łączą się przez AND: trwałość (domyślnie SSL), sekcja, grupa kategorii („Kategoria”) i kategoria produktu („Podkategoria”), a także wyszukiwanie nazwy. Grupy mogą zawierać tę samą podkategorię i produkty mogą należeć do wielu grup. Filtrowanie używa identyfikatorów, więc jednakowe nazwy nie łączą różnych kategorii. Zmiana grupy zeruje wybraną podkategorię. Sekcje i obie kategorie są zwinięte pod przyciskiem z aktualnym wyborem; rozwijają badge’e z możliwością wyboru na tablecie. Wybór „Wszystkie” usuwa ograniczenie na danym poziomie. Zliczanie wyników i pusty wynik aktualizują się bez żądań sieciowych. Filtry są lokalne dla listy; nie zmieniają wybranego globalnie działu tabletu.

Kitchen zachowuje z istniejącego API `sector_id`, `sector_name`, `category.groups`, `id_category` i `shelf_life_category` w modelu listy. Odczyt `/shops/{shop}/products/available?include=kitchen_cards` dodaje główne zdjęcie receptury produktu jako `main_photo_path`, zgodnie ze źródłem zdjęcia na jego karcie szczegółowej. Zdjęcia są pobierane jednym zbiorczym zapytaniem dla produktów z już ograniczonej do sklepu listy. Brak `include=kitchen_cards` nie wykonuje tego zapytania i nie dodaje pola (bez zmian danych POS/WebShop).

Miniatury korzystają z istniejącej obsługi `r2://`, mają lazy loading i zachowują kompaktowy placeholder przy braku lub błędzie zdjęcia. Nie generowano nowych plików ani wariantów obrazów. Wspólne zaokrąglenia przycisków wyboru pracownika i sekcji przeniesiono z CSS dnia produkcyjnego do globalnego `atelier-theme.css`, którego URL zależy od hasha zawartości.

Zmiany w Kitchen i API, bez migracji. API należy wdrożyć przed Kitchen, aby zdjęcia pojawiły się również na liście. Nie wykonywano deploymentu.

Testy: PHP renderowania w pięciu językach, PHPUnit opcjonalnego pobierania zdjęć i zgodności dotychczasowych odczytów, Chromium w układach 1024/768/390 px (połączone filtry, domyślny SSL, różne ID przy takich samych nazwach kategorii, zdjęcia/poprawny fallback, proporcje i globalne narożniki). Testy korzystają z atrap transportu i danych. Nie uruchamiano rzeczywistej bazy, zdalnego API, R2 ani fizycznych tabletów; nie uruchamiano całego niezwiązanego zestawu testów.
