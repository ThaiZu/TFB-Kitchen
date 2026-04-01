# Struktura modeli - Knowledge/Product

## Przegląd

Na bazie struktury danych zwracanej z API (`productRaw`) została stworzona kompletna hierarchia modeli PHP obsługujących pełne dane produktu wraz z recepturą, opakowaniem, kompetencjami i krokami przygotowania.

## Hierarchia modeli

```
ProductDetailModel (główny model)
├── object: ProductModel
├── photos: ProductPhotoModel
├── recipe: RecipeModel
│   └── elements: Array
│       ├── RecipeIngredientModel
│       └── RecipeSubrecipeModel
│           └── elements: Array (rekurencja)
│               ├── RecipeIngredientModel
│               └── RecipeSubrecipeModel
├── package: PackageModel
│   └── materials: Array<PackageMaterialModel>
├── competences: Array<CompetenceModel>
└── preparation: PreparationModel
    ├── types: Array<PreparationTypeModel>
    └── steps: Array<PreparationStepModel>
```

## Szczegółowy opis modeli

### 1. `ProductDetailModel`
**Plik:** `src/app/Models/Knowledge/Product/ProductDetailModel.php`

Główny model agregujący wszystkie dane produktu.

**Właściwości:**
- `$object` - ProductModel (podstawowe dane produktu)
- `$photos` - ProductPhotoModel (zdjęcia produktu)
- `$recipe` - RecipeModel (receptura)
- `$package` - PackageModel (opakowanie)
- `$competences` - Array<CompetenceModel> (wymagane kompetencje)
- `$preparation` - PreparationModel (kroki przygotowania)

**Metody pomocnicze:**
- `hasPhotos()`, `hasRecipe()`, `hasPackage()`, `hasCompetences()`, `hasPreparation()`
- `getAllAllergens()` - zwraca wszystkie alergeny z receptury
- `getTotalPreparationMinutes()` - suma czasu przygotowania

---

### 2. `ProductModel`
**Plik:** `src/app/Models/Knowledge/Product/ProductModel.php`

Podstawowe dane produktu (istniejący, już zaktualizowany).

**Właściwości:**
- ID, nazwa, kategoria, cena, przechowywanie, itp.
- Wszystkie pola z sekcji `object` w `productRaw`

---

### 3. `ProductPhotoModel`
**Plik:** `src/app/Models/Knowledge/Product/ProductPhotoModel.php`

Zarządza ścieżkami do zdjęć produktu.

**Właściwości:**
- `$main_photo_path`
- `$photo_1_path`
- `$photo_2_path`
- `$photo_3_path`

**Metody:**
- `hasMainPhoto()`, `hasPhoto1()`, `hasPhoto2()`, `hasPhoto3()`

---

### 4. `RecipeModel`
**Plik:** `src/app/Models/Knowledge/Recipe/RecipeModel.php`

Receptura produktu z zagnieżdżonymi składnikami i podrecepturami.

**Właściwości:**
- `$id`, `$name`
- `$is_subrecipe` - czy to podreceptura
- `$yield_quantity` - wydajność
- `$id_unit` - jednostka
- `$elements` - Array składników i podreceptur

**Metody:**
- `getElements()` - zwraca wszystkie elementy
- `getAllAllergens()` - rekurencyjnie zbiera alergeny z całej receptury

---

### 5. `RecipeIngredientModel`
**Plik:** `src/app/Models/Knowledge/Recipe/RecipeIngredientModel.php`

Pojedynczy składnik w recepturze.

**Właściwości:**
- `$ingredient_id`, `$name`
- `$type` = 'ingredient'
- `$recipe_quantity` - ilość w recepturze
- `$required_quantity` - wymagana ilość
- `$unit_name` - jednostka
- `$allergens` - tablica alergenów
- Ceny netto/brutto

**Metody:**
- `hasAllergens()` - czy składnik ma alergeny

---

### 6. `RecipeSubrecipeModel`
**Plik:** `src/app/Models/Knowledge/Recipe/RecipeSubrecipeModel.php`

Podreceptura w recepturze głównej (zagnieżdżona struktura).

**Właściwości:**
- `$recipe_id`, `$name`
- `$type` = 'sub-recipe'
- `$recipe_quantity`, `$yield_quantity`
- `$elements` - Array<RecipeIngredientModel | RecipeSubrecipeModel>

**Uwaga:** Model obsługuje **rekurencję** - podreceptura może zawierać inne podreceptury.

---

### 7. `PackageModel`
**Plik:** `src/app/Models/Knowledge/Product/PackageModel.php`

Opakowanie produktu.

**Właściwości:**
- `$id`, `$name`, `$description`
- `$materials` - Array<PackageMaterialModel>

**Metody:**
- `getTotalPriceNet()` - suma cen netto materiałów
- `getTotalPriceGross()` - suma cen brutto materiałów

---

### 8. `PackageMaterialModel`
**Plik:** `src/app/Models/Knowledge/Product/PackageMaterialModel.php`

Pojedynczy materiał opakowania (np. torebka, etykieta).

**Właściwości:**
- `$id`, `$name`
- `$quantity` - ilość
- `$base_unit_price_net`, `$base_unit_price_gross`
- `$unit_name` - jednostka
- `$is_part_of_package` - czy część opakowania
- `$source_type`, `$kind`

---

### 9. `CompetenceModel`
**Plik:** `src/app/Models/Knowledge/Product/CompetenceModel.php`

Wymagana kompetencja do przygotowania produktu.

**Właściwości:**
- `$id`
- `$name` - nazwa kompetencji (np. "Mise en Traiteur")
- `$description` - szczegółowy opis

---

### 10. `PreparationModel`
**Plik:** `src/app/Models/Knowledge/Recipe/PreparationModel.php`

Agreguje typy i kroki przygotowania.

**Właściwości:**
- `$types` - Array<PreparationTypeModel>
- `$steps` - Array<PreparationStepModel>

**Metody:**
- `getTotalDurationSeconds()` - suma czasu wszystkich typów
- `getTotalDurationMinutes()` - suma czasu w minutach

---

### 11. `PreparationTypeModel`
**Plik:** `src/app/Models/Knowledge/Recipe/PreparationTypeModel.php`

Typ przygotowania (np. "Mise en place").

**Właściwości:**
- `$id`, `$name`
- `$duration_second` - czas trwania w sekundach

**Metody:**
- `getDurationMinutes()` - czas w minutach

---

### 12. `PreparationStepModel`
**Plik:** `src/app/Models/Knowledge/Recipe/PreparationStepModel.php`

Pojedynczy krok przygotowania.

**Właściwości:**
- `$step_number` - numer kroku
- `$step_description` - opis instrukcji

---

## Przykład użycia

```php
// W repozytorium:
$rawData = $this->apiClient->get("/products/{$id}");
$productDetail = new ProductDetailModel($rawData);

// W kontrolerze:
$data['product'] = $productDetail;

// W widoku (Twig):
{{ product.getName() }}
{{ product.getCategoryName() }}

{% if product.hasPhotos() %}
    <img src="{{ product.getPhotos().getPhoto1Path() }}">
{% endif %}

{% if product.hasRecipe() %}
    <h3>Składniki:</h3>
    <ul>
    {% for element in product.getRecipe().getElements() %}
        <li>{{ element.getName() }} - {{ element.getRequiredQuantity() }} {{ element.getUnitName() }}</li>
    {% endfor %}
    </ul>
{% endif %}

{% if product.hasPreparation() %}
    <p>Czas przygotowania: {{ product.getTotalPreparationMinutes() }} min</p>
    
    <h3>Kroki:</h3>
    <ol>
    {% for step in product.getPreparation().getSteps() %}
        <li>{{ step.getStepDescription() }}</li>
    {% endfor %}
    </ol>
{% endif %}

<p>Alergeny: {{ product.getAllAllergens()|join(', ') }}</p>
```

## Mapowanie API → Model

| API field | Model | Property |
|-----------|-------|----------|
| `object` | ProductModel | wszystkie pola produktu |
| `photos` | ProductPhotoModel | ścieżki do zdjęć |
| `recipe` | RecipeModel | receptura z elementami |
| `recipe.elements[].type='ingredient'` | RecipeIngredientModel | składnik |
| `recipe.elements[].type='sub-recipe'` | RecipeSubrecipeModel | podreceptura |
| `package` | PackageModel | opakowanie |
| `package.materials[]` | PackageMaterialModel | materiał opakowania |
| `competences[]` | CompetenceModel | kompetencja |
| `preparation.types[]` | PreparationTypeModel | typ przygotowania |
| `preparation.steps[]` | PreparationStepModel | krok przygotowania |

## Zalety struktury

1. **Typ-safe** - modele wymuszają strukturę danych
2. **Czytelność** - jasna hierarchia obiektów
3. **JsonSerializable** - łatwa serializacja do JSON (API responses)
4. **Metody pomocnicze** - np. `getAllAllergens()`, `getTotalPriceNet()`
5. **Rekurencja** - obsługa zagnieżdżonych podreceptur
6. **Walidacja** - metody `has*()` do sprawdzania obecności danych

## Walidacja

Wszystkie modele zostały zwalidowane składniowo:
```bash
✓ All model files - syntax OK
```

## Pliki utworzone

### Product:
- `CompetenceModel.php`
- `PackageMaterialModel.php`
- `PackageModel.php`
- `ProductDetailModel.php` ⭐ (główny)
- `ProductPhotoModel.php`
- `ProductModel.php` (zaktualizowany)

### Recipe:
- `PreparationModel.php`
- `PreparationStepModel.php`
- `PreparationTypeModel.php`
- `RecipeIngredientModel.php`
- `RecipeModel.php` (zaktualizowany)
- `RecipeSubrecipeModel.php`

**Łącznie:** 12 modeli (6 nowych, 2 zaktualizowane, 4 pomocnicze)

