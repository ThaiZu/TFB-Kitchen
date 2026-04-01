# Podsumowanie zmian - Aplikacja kuchenna

## Cel
Dostosowanie aplikacji do kontekstu kuchni sklepu franczyzowego.
Użytkownik zawsze wie gdzie się znajduje i jaki zakres funkcjonalności ma do dyspozycji.

## Zmiany w plikach

### 1. System tłumaczeń (i18n)

#### Nowe pliki:
- `src/core/I18n/translations/page/en/auth.json` - tłumaczenia angielskie dla strony logowania
- `src/core/I18n/translations/page/en/dashboard.json` - tłumaczenia angielskie dla dashboardu
- `src/core/I18n/translations/page/en/page_components.json` - tłumaczenia angielskie dla komponentów

#### Zaktualizowane pliki:
- `src/core/I18n/translations/page/pl/auth.json`
  - Dodano klucze dla funkcjonalności kuchni:
    - `kitchen_production` - Dzisiejsza produkcja
    - `kitchen_cards` - Karty produkcyjne
    - `kitchen_checklists` - Checklisty
    - `kitchen_offline` - Tryb offline
  - Zaktualizowano opisy funkcjonalności

- `src/core/I18n/translations/page/pl/dashboard.json`
  - Zmiana z kontekstu dostawcy na kontekst kuchni
  - Nowe akcje: produkcja, karty, checklisty, zamówienia
  - Statystyki: zadania do zrobienia, w trakcie, ukończone

- `src/core/I18n/translations/page/pl/page_components.json`
  - Dodano tłumaczenia dla navbar: notifications, hello, settings

### 2. Kontroler Auth

**Plik:** `src/app/Http/Controllers/Auth/AuthController.php`

**Zmiana:**
- Zamiana hardkodowanego komunikatu błędu `"Nieprawidłowy login lub hasło."` na klucz tłumaczenia `"error_invalid_credentials"`
- Dzięki temu komunikat jest wyświetlany w języku użytkownika

### 3. Widok logowania

**Plik:** `src/app/Views/auth/login.twig`

**Zmiany:**
- Lewa kolumna: Zaktualizowano listę funkcji na 4 główne moduły:
  1. **Dzisiejsza produkcja** - lista zadań, priorytety, statusy
  2. **Karty produkcyjne** - receptury, składniki, instrukcje
  3. **Checklisty** - listy dzienne (otwarcie, porządek, zamknięcie)
  4. **Tryb offline** - dostęp do kart bez internetu
  
- Ikony Bootstrap dostosowane do kontekstu:
  - `bi-list-check` - produkcja
  - `bi-journal-text` - karty
  - `bi-clipboard-check` - checklisty
  - `bi-cloud-arrow-down` - offline

- Alert błędu używa teraz tłumaczeń:
  ```twig
  {{ translations[errors.invalid_credentials] ?? translations.error_invalid_credentials }}
  ```

### 4. Dashboard

**Plik:** `src/app/Views/dashboard/dashboard.twig`

**Zmiany:**
- Nagłówek: zmiana z `supplier_dashboard` na `kitchen_dashboard`
- Quick Actions: 4 główne moduły zamiast katalogu/cenników:
  1. **Dzisiejsza produkcja** (`/production`)
  2. **Karty produkcyjne** (`/cards`)
  3. **Checklisty** (`/checklists`)
  4. **Moje zamówienia** (`/orders`)
  
- Statystyki: zmiana z produktów/cenników na zadania:
  - Zadania do zrobienia
  - W trakcie
  - Ukończone dzisiaj

### 5. Sidebar (Menu boczne)

**Plik:** `src/app/Views/page_components/sidebar.twig`

**Zmiany:**
- Menu główne dostosowane do kontekstu kuchni:
  1. Dashboard (pulpit kuchni)
  2. Produkcja (dzisiejsza lista zadań)
  3. Karty (receptury i instrukcje)
  4. Checklisty (listy do odhaczenia)
  5. Zamówienia (składniki od dostawców)
  6. Mój profil
  7. Wyloguj

## 4 główne moduły aplikacji

Aplikacja kuchenna skupia się na konkretnym celu: **"wiem co dziś robić i jak"**

### 1. Dzisiejsza produkcja
- Lista pozycji do wykonania
- Ilość, deadline, priorytet
- Status: do zrobienia / w trakcie / zrobione

### 2. Karty produkcyjne / instrukcje
- Wyszukiwarka + kategorie
- Składniki, gramatury, kroki, zdjęcia
- Przycisk "Dodaj do dzisiejszej listy"

### 3. Checklisty
- Listy dzienne/stanowiskowe (otwarcie/porządek/zamknięcie)
- Odhaczanie + timestamp + "kto wykonał"

### 4. Tryb offline
- Karty/instrukcje dostępne bez internetu
- Prosta synchronizacja statusów po odzyskaniu połączenia

## Design System

### Identyfikacja kontekstu
- **Chip "Kitchen Portal"** w prawym górnym rogu formularza logowania
- **Lewa kolumna** z jasnym opisem aplikacji i jej funkcji
- **Ikona kuchni** (bi-shop-window) w nagłówku
- **Spójne kolory**: ciemny gradient + zielone akcenty (system online)

### Ikony Bootstrap używane w aplikacji
- `bi-list-check` - produkcja, zadania
- `bi-journal-text` - karty, receptury
- `bi-clipboard-check` - checklisty
- `bi-cloud-arrow-down` - offline mode
- `bi-truck` - zamówienia
- `bi-shop-window` - kuchnia (brand)

## Co dalej?

Przyszłe fazy rozwoju mogą zawierać:
- Moduł inwentaryzacji składników
- Historia produkcji i raporty
- Integracja z zamówieniami/dostawcami
- Planowanie produkcji (tygodniowe/miesięczne)
- Fotodokumentacja procesu produkcji
- Szkolenia pracowników (wideo/quizy)

## Mechanizm tłumaczeń

Projekt używa funkcji `loadTranslations($type, $lang, $module)` zdefiniowanej w `src/core/Support/functions.php`.

**Struktura:**
```
src/core/I18n/translations/
└── page/
    ├── pl/
    │   ├── auth.json
    │   ├── dashboard.json
    │   └── page_components.json
    └── en/
        ├── auth.json
        ├── dashboard.json
        └── page_components.json
```

**Użycie w Twig:**
```twig
{{ translations.kitchen_production }}
{{ translations.error_invalid_credentials }}
```

**Fallback:** Jeśli brak tłumaczenia w wybranym języku, system automatycznie sięgnie po wersję angielską (EN).

