<?php

namespace App\Kitchen\app\Services\Production;

use App\Kitchen\app\Models\Production\ProductionItemModel;
use App\Kitchen\app\Repositories\Production\ProductionRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class ProductionService
{
    public function __construct(
        private ProductionRepository $productionRepository
    ) {}

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    /**
     * Plan du jour.
     *
     * @return ProductionItemModel[]|null null quand l'API ne sert pas encore
     *         le plan — à distinguer d'un tableau vide, qui signifie « rien à
     *         produire aujourd'hui ».
     */
    public function getPlan(?string $date = null): ?array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return null;
        }
        return $this->productionRepository->getPlan($shopId, $date ?? date('Y-m-d'));
    }

    /**
     * Répartit le plan par statut, dans l'ordre où la cuisine le lit :
     * ce qui est en cours d'abord, puis ce qui reste, puis ce qui est fait.
     *
     * @param ProductionItemModel[] $items
     * @return array{in_progress: array, todo: array, done: array}
     */
    public function groupByStatus(array $items): array
    {
        $groups = ['in_progress' => [], 'todo' => [], 'done' => []];

        foreach ($items as $item) {
            if ($item->isCancelled()) {
                continue; // une ligne annulée n'a rien à faire dans un plan de travail
            }
            if ($item->isInProgress()) {
                $groups['in_progress'][] = $item;
            } elseif ($item->isDone()) {
                $groups['done'][] = $item;
            } else {
                $groups['todo'][] = $item;
            }
        }

        // Priorité déclarée d'abord, puis créneau horaire : c'est l'ordre dans
        // lequel les choses doivent sortir du four.
        $sort = function (array &$g) {
            usort($g, function (ProductionItemModel $a, ProductionItemModel $b) {
                $pa = $a->getPriority() ?? PHP_INT_MAX;
                $pb = $b->getPriority() ?? PHP_INT_MAX;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
                return ($a->getSlot() ?? '99:99') <=> ($b->getSlot() ?? '99:99');
            });
        };
        $sort($groups['in_progress']);
        $sort($groups['todo']);
        $sort($groups['done']);

        return $groups;
    }

    /**
     * Totaux affichés en tête d'écran.
     *
     * @param ProductionItemModel[] $items
     */
    public function summarise(array $items): array
    {
        $todo = $inProgress = $done = 0;
        $plannedTotal = $doneTotal = 0.0;

        foreach ($items as $item) {
            if ($item->isCancelled()) {
                continue;
            }
            $plannedTotal += $item->getQuantityPlanned();
            $doneTotal    += $item->getQuantityDone();

            if ($item->isDone())            { $done++; }
            elseif ($item->isInProgress())  { $inProgress++; }
            else                            { $todo++; }
        }

        return [
            'todo'        => $todo,
            'in_progress' => $inProgress,
            'done'        => $done,
            'total'       => $todo + $inProgress + $done,
            'percent'     => $plannedTotal > 0
                ? (int)min(100, round($doneTotal / $plannedTotal * 100))
                : 0,
        ];
    }

    /** Met à jour une ligne ; renvoie la réponse brute de l'API. */
    public function updateItem(int $itemId, array $payload): array
    {
        return $this->productionRepository->updateItem($itemId, $payload);
    }
}
