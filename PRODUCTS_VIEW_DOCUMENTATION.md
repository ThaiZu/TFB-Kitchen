# Widok przeglądu produktów - Knowledge/Products Overview

## Cel
Stworzenie intuicyjnego widoku dla pracowników kuchni, który umożliwia szybkie wyszukiwanie i wybór produktów oraz przejście do ich kart technicznych/receptur.

## Wprowadzone zmiany

### 1. Widok `knowledge/product/overview.twig`

#### Struktura widoku:
- **Breadcrumb navigation**: Home → Knowledge → Products
- **Search bar**: Duże pole wyszukiwania z ikoną (live search)
- **Category filter**: Dropdown do filtrowania po kategoriach (automatycznie wypełniony)
- **Products grid**: Responsywna siatka kart produktów (4 kolumny na dużych ekranach)

#### Funkcjonalności:
1. **Live Search (wyszukiwanie na żywo)**
   - Filtrowanie po nazwie produktu
   - Działa podczas wpisywania (input event)
   - Case-insensitive

2. **Filtrowanie po kategoriach**
   - Automatyczne wypełnianie selecta na podstawie dostępnych produktów
   - Sortowanie kategorii alfabetycznie
   - Możliwość resetowania filtra (opcja "Wszystkie kategorie")

3. **Kombinowane filtrowanie**
   - Możliwość jednoczesnego użycia search + filter
   - Dynamiczne liczenie widocznych produktów
   - Komunikat "Nie znaleziono produktów" gdy brak wyników

#### Karta produktu zawiera:
- **Nazwa produktu** (h5, wyróżniona)
- **Kategoria** (z ikoną tagu)
- **Badge'e**:
  - Wegetariańskie (zielony leaf icon)
  - Przygotowane wcześniej (żółty clock icon)
- **Informacje przechowywania**:
  - Miejsce przechowywania (z ikoną termometru)
  - Czas przydatności (w godzinach)
- **Link**: Prowadzi do `/knowledge/recipes/{id_recipe}`
- **Hover effect**: Karta podnosi się przy najechaniu

### 2. Kontroler `ProductController.php`

**Zmiany:**
- Usunięto linię debugowania `show($data); exit();`
- Widok renderuje się prawidłowo z danymi z serwisu

### 3. Tłumaczenia

#### Dodano do `pl/knowledge.json`:
```json
"products_overview_subtitle": "Znajdź produkt i przeglądaj jego kartę techniczną",
"products_list": "Lista produktów",
"products_total": "produktów",
"search_products": "Wyszukaj produkt po nazwie...",
"search_hint": "Wpisz nazwę produktu aby szybko go odnaleźć",
"filter_category": "Filtruj po kategorii",
"all_categories": "Wszystkie kategorie",
"no_category": "Bez kategorii",
"no_products_found": "Nie znaleziono produktów pasujących do wyszukiwania",
"view_card": "Zobacz kartę",
"vegetarian": "Wegetariańskie",
"prepared_before": "Przygotowane wcześniej",
"hours": "godz."
```

#### Dodano do `en/knowledge.json`:
- Angielskie wersje wszystkich powyższych kluczy

### 4. JavaScript (inline w widoku)

**Funkcjonalności:**
- Automatyczne wypełnianie filtra kategorii
- Live filtering produktów
- Zliczanie widocznych produktów
- Show/hide komunikatu "brak wyników"
- Auto-focus na polu wyszukiwania po załadowaniu strony

## Responsywność

Layout dostosowuje się do rozmiaru ekranu:
- **Desktop (lg+)**: 4 kolumny produktów
- **Tablet (md)**: 3 kolumny produktów
- **Mobile (sm)**: 2 kolumny produktów
- **Mobile small**: 1 kolumna produktów

Search bar i filter również dostosowują się:
- **Desktop**: Search 66% + Filter 33% (bok obok)
- **Mobile**: Pełna szerokość dla obu (jeden pod drugim)

## Wykorzystane dane z ProductModel

Widok wykorzystuje następujące metody z modelu:
- `getId()` - ID produktu
- `getName()` - Nazwa produktu
- `getCategoryName()` - Nazwa kategorii
- `getIsVegetarian()` - Czy wegetariańskie
- `getIsPreparedBeforeSales()` - Czy przygotowane wcześniej
- `getStorageName()` - Miejsce przechowywania
- `getShelfLifeMinutes()` - Czas przydatności w minutach
- `getIdRecipe()` - ID receptury (do linku)

## User Experience

1. **Pracownik wchodzi na /knowledge/products**
2. **Widzi wszystkie produkty w siatce**
3. **Może szybko wyszukać**:
   - Wpisując nazwę w search bar
   - Wybierając kategorię z dropdowna
   - Lub kombinując oba filtry
4. **Widzi live feedback**:
   - Licznik produktów aktualizuje się na bieżąco
   - Komunikat gdy brak wyników
5. **Klika na kartę produktu** → Przechodzi do receptury/karty technicznej

## Testowanie

Widok można przetestować wchodząc na:
```
http://localhost/knowledge/products
```

Wszystkie filtry działają po stronie klienta (JavaScript), więc są bardzo szybkie i nie wymagają zapytań do serwera.

## Zgodność z knowledge/overview.twig

Widok bazuje na strukturze `knowledge/overview.twig`:
- ✅ Taki sam layout z breadcrumb
- ✅ Taki sam nagłówek i podtytuł
- ✅ Zgodny z systemem tłumaczeń
- ✅ Wykorzystuje te same style (hover-lift, karty)
- ✅ Responsywność zgodna z projektem

## Możliwe rozszerzenia (future improvements)

1. **Sortowanie**: Możliwość sortowania po nazwie/kategorii
2. **Ulubione**: Zapamiętywanie często przeglądanych produktów
3. **Historia**: Ostatnio przeglądane produkty
4. **Obrazki**: Dodanie miniatur produktów
5. **Kody kreskowe**: Wyszukiwanie po kodzie produktu
6. **Export**: Możliwość wydruku listy produktów

