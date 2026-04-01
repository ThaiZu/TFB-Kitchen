# Aktualizacja formularza logowania - pole wyboru sklepu

## Cel
Integracja pola wyboru sklepu z systemem tłumaczeń i zapewnienie spójnej stylizacji oraz walidacji.

## Wprowadzone zmiany

### 1. System tłumaczeń (i18n)

#### Zaktualizowane pliki tłumaczeń:

**`src/core/I18n/translations/page/pl/auth.json`**
Dodano klucze:
- `shop_label` - "Sklep"
- `shop_placeholder` - "Wybierz sklep"
- `error_shop_required` - "Wybór sklepu jest wymagany."
- `error_login_required` - "Login jest wymagany."
- `error_password_required` - "Hasło jest wymagane."

**`src/core/I18n/translations/page/en/auth.json`**
Dodano klucze:
- `shop_label` - "Store"
- `shop_placeholder` - "Select store"
- `error_shop_required` - "Store selection is required."
- `error_login_required` - "Login is required."
- `error_password_required` - "Password is required."

### 2. Walidacja formularza

**Plik:** `src/app/Http/Requests/LoginRequest.php`

**Zmiany:**
- Dodano walidację pola `shop` (wymagane)
- Zmieniono wszystkie komunikaty błędów z hardkodowanych tekstów na klucze tłumaczeń:
  - `'Shop is required'` → `'error_shop_required'`
  - `'Login is required'` → `'error_login_required'`
  - `'Password is required'` → `'error_password_required'`

### 3. Widok logowania

**Plik:** `src/app/Views/auth/login.twig`

**Zmiany CSS:**
- Rozszerzono style `.input-wrap input` na `.input-wrap input, .input-wrap select`
- Dodano specjalne style dla `select`:
  - Ukryto domyślną strzałkę (`appearance: none`)
  - Dodano niestandardową strzałkę dropdown (SVG)
  - Wyrównano padding i stylizację z innymi polami input
  - Dodano focus states i invalid states

**Zmiany HTML formularza:**
- Dodano ikonę `bi-shop-window` przed selectem sklepu
- Dodano atrybut `aria-label` z tłumaczeniem
- Dodano pusty `<option>` z placeholderem z tłumaczenia
- Dodano obsługę `old('shop')` dla zachowania wartości po błędnej walidacji
- Zmieniono wyświetlanie błędów na używanie tłumaczeń:
  - `{{ errors.shop }}` → `{{ translations[errors.shop] ?? errors.shop }}`
  - `{{ errors.login }}` → `{{ translations[errors.login] ?? errors.login }}`
  - `{{ errors.password }}` → `{{ translations[errors.password] ?? errors.password }}`

## Jak to działa

### Proces logowania:
1. User wybiera sklep z listy dropdown
2. Wprowadza login i hasło
3. Formularz przesyła dane POST do kontrolera
4. `LoginRequest::validateLogin()` sprawdza czy wszystkie pola są wypełnione
5. Jeśli walidacja przejdzie, `AuthService->login()` wywołuje `LoginRepository->login()`
6. `LoginRepository` wysyła do API endpoint `/material-suppliers/auth/login` z danymi:
   ```json
   {
     "shop": "ID_SKLEPU",
     "login": "login_uzytkownika",
     "password": "haslo"
   }
   ```
7. API zwraca tokeny JWT, które są zapisywane w cookies

### Wyświetlanie błędów:
- Błędy walidacji są kluczami tłumaczeń (np. `error_shop_required`)
- Widok używa `{{ translations[errors.shop] ?? errors.shop }}` aby:
  - Najpierw spróbować pobrać tłumaczenie z klucza
  - Jeśli nie istnieje, wyświetlić surowy klucz jako fallback

## Wizualne ulepszenia

### Select sklepu:
- ✅ Ikona `bi-shop-window` po lewej stronie
- ✅ Placeholder "Wybierz sklep" / "Select store"
- ✅ Spójna stylizacja z innymi polami input
- ✅ Strzałka dropdown po prawej stronie
- ✅ Focus state z ciemnoszarym borderem i subtelnym cieniem
- ✅ Invalid state z czerwonym borderem
- ✅ Zachowanie wybranej wartości po błędnej walidacji

### Kontekst użytkownika:
Widok jasno pokazuje:
- **Lewa kolumna:** "Kuchnia Franczyzy" z listą 4 głównych funkcji
- **Badge kontekstu:** "Kitchen Portal" z zieloną kropką w prawym górnym rogu formularza
- **Tytuł formularza:** "Zaloguj się do systemu kuchennego"
- **Pole sklepu:** Jako pierwszy element, użytkownik musi wybrać sklep

## Zgodność z systemem
- ✅ Używa mechanizmu tłumaczeń `loadTranslations('page', $langCode, 'auth')`
- ✅ Obsługuje języki: PL i EN
- ✅ Zgodne z konwencją nazewnictwa kluczy tłumaczeń
- ✅ Wszystkie komunikaty błędów są tłumaczone
- ✅ ID sklepu jest wysyłane do API w formacie JSON

