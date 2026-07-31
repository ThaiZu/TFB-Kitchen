<?php

namespace App\Kitchen\app\Services\Production;

use App\Kitchen\app\Models\Production\ProductionProductModel;
use App\Kitchen\app\Models\Production\RebakeSuggestionModel;
use App\Kitchen\app\Models\Production\SalesProfileModel;
use App\Kitchen\app\Models\Production\StockLineModel;

/**
 * Décide s'il faut relancer une cuisson, et de combien.
 *
 * Service volontairement PUR : ni HTTP, ni horloge, ni session. L'heure
 * courante est passée en paramètre. C'est ce qui rend la logique vérifiable
 * hors serveur — voir bin/forecast-test.php et tests/fixtures/.
 *
 *     fenêtre(produit) = [maintenant ; maintenant + forecast_hours + lead]
 *     ventes_prévues   = Σ profil sur la fenêtre
 *     projection       = stock − ventes_prévues − marge
 *     si projection < 0 : ceil(−projection / batch) × batch
 */
class ForecastService
{
    /**
     * @param ProductionProductModel[] $products
     * @param StockLineModel[]         $stockLines
     * @param SalesProfileModel|null   $profile     null = profil non servi : aucune proposition
     * @param int                      $nowMinutes  minutes depuis minuit
     * @param array{forecast_hours?: float, safety_margin?: float} $params
     *
     * @return RebakeSuggestionModel[] triées par urgence, la rupture la plus
     *         profonde d'abord
     */
    public function suggest(
        array $products,
        array $stockLines,
        ?SalesProfileModel $profile,
        int $nowMinutes,
        array $params = []
    ): array {
        if ($profile === null) {
            // Sans profil, toute proposition serait inventée. L'écran affiche
            // le stock et dit que la prévision n'est pas disponible.
            return [];
        }

        $forecastMinutes = (int)round(((float)($params['forecast_hours'] ?? PRODUCTION_FORECAST_HOURS)) * 60);
        $margin          = (float)($params['safety_margin'] ?? PRODUCTION_SAFETY_MARGIN);

        $stockByProduct = [];
        foreach ($stockLines as $line) {
            if ($line->getIdProduct() !== null) {
                $stockByProduct[$line->getIdProduct()] = $line;
            }
        }

        $suggestions = [];

        foreach ($products as $product) {
            $id = $product->getIdProduct();
            if ($id === null || !$product->isActive()) {
                continue;
            }

            // Un produit absent du profil n'est pas un produit qui ne se vend
            // pas : c'en est un dont on ne sait rien. On ne propose pas.
            $window   = $forecastMinutes + $product->getLeadMinutes();
            $expected = $profile->expectedBetween($id, $nowMinutes, $nowMinutes + $window);
            if ($expected === null) {
                continue;
            }

            $stockLine = $stockByProduct[$id] ?? null;
            $stock     = $stockLine?->getQuantity() ?? 0.0;

            $projected = $stock - $expected - $margin;
            if ($projected >= 0) {
                continue;
            }

            $deficit = -$projected;
            $batch   = $product->getEffectiveBatchSize();
            $qty     = ceil($deficit / $batch) * $batch;

            $suggestions[] = new RebakeSuggestionModel(
                $id,
                $product->getName() ?? $stockLine?->getName(),
                $product->getCategoryName() ?? $stockLine?->getCategoryName(),
                $stock,
                $expected,
                $projected,
                $deficit,
                $batch,
                $product->hasBatchSize(),
                $qty,
                $window,
                $product->getLeadMinutes(),
                $product->getUnitName() ?? $stockLine?->getUnitName()
            );
        }

        // La rupture la plus profonde d'abord : c'est celle qui doit entrer au
        // four en premier.
        usort($suggestions, fn($a, $b) => $a->getProjected() <=> $b->getProjected());

        return $suggestions;
    }

    /** Minutes depuis minuit, pour une heure « HH:MM ». */
    public static function minutesOf(string $time): int
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)
            ? (int)$m[1] * 60 + (int)$m[2]
            : 0;
    }
}
