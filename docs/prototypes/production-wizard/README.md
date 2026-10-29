# Propozycja kreatora dnia produkcyjnego

Otwórz pobrany `index.html` w przeglądarce. To samodzielna, klikalna makieta w języku polskim: nie wywołuje API, nie zapisuje niczego w bazie ani pamięci przeglądarki i nie zmienia `/production-day`. Dane są przykładowe. Przeładowanie rozpoczyna demonstrację od nowa. Wybrane zdjęcia nie są wysyłane; w makiecie sprawdzana jest tylko obecność pliku.

## Przebieg

1. **Zapas z wczoraj** — potwierdzenie ilości i jakości albo wycofanie części zapasu z powodem i zdjęciem. Potwierdzenie nie dodaje stanu ponownie.
2. **Produkcja nocna** — osobne wpisanie gotowych produktów ukończonych po poprzednim zamknięciu, np. wypieku zakończonego o 01:00. Zera pozwalają przejść dalej; produkcja nadal w toku nie jest gotowym stanem.
3. **Produkcja dzisiaj** — ilość nowej produkcji i wynikowy stan na otwarciu. Obowiązkowy produkt z zerową łączną dostępnością wymaga przyczyny braku; „Inny powód” wymaga krótkiego opisu. Dopiero zatwierdzenie tego kroku otwiera dzień.
4. **Stan sklepu** — ekran roboczy w ciągu dnia z dodawaniem produkcji i zgłaszaniem odpadu. Kreator zatrzymuje się tutaj do czasu decyzji pracownika o przygotowaniu jutra/zamknięciu.
5. **Przygotuj jutro** — przykładowe ilości ewidencjonowane osobno, bez zwiększania dzisiejszej dostępności. Zera oznaczają pominięcie tego etapu.
6. **Zamknij dzień** — SSL w całości do odpadu, MSL/LSL według decyzji; powody i zdjęcia potwierdzane kolejno. Końcowy ekran informuje o zakończeniu demonstracji.

Przyciski ilości ±1/±5/±10, duże pola dotykowe i jedna główna akcja w stałej stopce. Stan można sprawdzić na poziomym i pionowym tablecie. Nocna produkcja trafia do widocznego podsumowania otwarcia; jej księgowanie z czasem ukończenia wymaga osobnego projektu API, nie jest tu implementowane.

## Rozbieżność PDM w mailu i kodzie

- `src/app/Models/Production/ProductionProductModel.php`: `isPdm()` opisano jako minimum sklepu/witryny; `is_pdm` jest czytane również z aliasów `has_shop_minimum` i `is_min_stock`.
- `src/app/Services/Production/MinimumService.php`: rzeczywisty filtr `isPdm()` oraz wyliczenie brakującej produkcji z minimum, zamówień i stanu.
- `docs/PRODUCTION_PARAMETRES.md`: `is_pdm` + `pdm_minimums` to minimum witryny; `is_pdb` oznacza przygotowanie dzień wcześniej.
- `ProductionProductModel::readPdb()` czyta aliasy `is_pdb`, `is_prep_day_before`, `prep_day_before`, `is_prepared_before_sales`.
- API posiada bazowe kolumny `is_pdm` i `is_prepared_before_sales`; nie potwierdzono odrębnej kolumny `is_pdb`. Admin etykietuje `is_pdm` jedynie jako „PDM”, bez rozwinięcia skrótu.

Zatem można potwierdzić znaczenie **obecnej implementacji Kitchen**, ale nie zgodność nazwy z intencją autora maila. Przed wdrożeniem należy uzgodnić, czy mail zmienia znaczenie `is_pdm`, czy chodzi o dotychczasowe przygotowanie D+1. Makieta celowo używa neutralnej nazwy „Przygotuj jutro”, nie filtruje rzeczywistego katalogu po `is_pdm` i nie zmienia istniejących flag.

Przykładowe przyczyny braku obowiązkowego produktu są propozycją interfejsu, nie istniejącym słownikiem API. Decyzje do ustalenia przy implementacji: moment i dzień księgowania produkcji nocnej, znaczenie produktu przygotowanego na jutro (gotowy produkt/półprodukt) i powiązanie flag.

## Podglądy na tablecie 1024×768

[Zapas z wczoraj](previews/01-zapas.png) · [Produkcja nocna](previews/02-produkcja-nocna.png) · [Otwarcie i brak obowiązkowy](previews/03-otwarcie.png) · [Stan sklepu](previews/04-stan-sklepu.png) · [Przygotowanie jutra](previews/05-jutro.png) · [Zamknięcie](previews/06-zamkniecie.png).

## Sprawdzenie makiety

`node docs/prototypes/production-wizard/smoke-test.cjs` wymaga Playwright i Chromium pod `/usr/bin/chromium`. Zrzuty są zapisywane w katalogu tymczasowym. Smoke test przeszedł dla 1024×768, 768×1024 i 390×844: kolejność kroków, blokada do czasu weryfikacji zapasu, dopisanie nocnej produkcji, wymagany powód braku obowiązkowego produktu, niepowiększanie stanu przez przygotowanie jutra, blokada ilości SSL, wymagane zdjęcie przy zamknięciu i brak błędów JavaScript. Nie jest to test integracyjny aplikacji. Pominięto testy rzeczywistego API, bazy, wysyłania zdjęć i fizycznego tabletu; makieta ich nie używa.
