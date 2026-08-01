<?php

namespace App\Kitchen\app\Services\Baking;

use App\Kitchen\app\Models\Baking\BakingBatchModel;
use App\Kitchen\app\Repositories\Baking\BakingRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class BakingService
{
    /** Étapes affichables, dans l'ordre du cycle. */
    public const STAGES = [
        BakingBatchModel::STAGE_PREP,
        BakingBatchModel::STAGE_COOK,
        BakingBatchModel::STAGE_FINISH,
    ];

    public function __construct(
        private BakingRepository $bakingRepository
    ) {}

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    /**
     * Plan de cuisson en cours.
     *
     * @return array{server_time: ?string, batches: BakingBatchModel[]}|null
     *         null = l'API ne sert pas encore le plan
     */
    public function getPlan(?string $date = null): ?array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return null;
        }
        return $this->bakingRepository->getPlan($shopId, $date ?? date('Y-m-d'));
    }

    /**
     * Ce que l'écran affiche : les fournées non terminées, dans l'ordre où la
     * cuisine veut les lire — ce qui est au four d'abord, puis les finitions,
     * puis les préparations, puis ce qui n'a pas commencé.
     *
     * @param BakingBatchModel[] $batches
     * @return BakingBatchModel[]
     */
    public function active(array $batches): array
    {
        $rows = array_values(array_filter($batches, fn(BakingBatchModel $b) => !$b->isDone()));

        $rang = [
            BakingBatchModel::STAGE_COOK   => 0,
            BakingBatchModel::STAGE_FINISH => 1,
            BakingBatchModel::STAGE_PREP   => 2,
        ];

        usort($rows, function (BakingBatchModel $a, BakingBatchModel $b) use ($rang) {
            // Ce qui n'a pas commencé passe après tout le reste, quelle que
            // soit son étape théorique.
            $wa = $a->isWaiting() ? 1 : 0;
            $wb = $b->isWaiting() ? 1 : 0;
            if ($wa !== $wb) {
                return $wa <=> $wb;
            }
            $ra = $rang[$a->getStage()] ?? 9;
            $rb = $rang[$b->getStage()] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return $a->getStageEnd() <=> $b->getStageEnd();
        });

        return $rows;
    }

    /**
     * Nombre de fournées par étape, pour les pastilles des filtres.
     *
     * @param BakingBatchModel[] $batches
     */
    public function countByStage(array $batches): array
    {
        $counts = array_fill_keys(self::STAGES, 0);
        foreach ($batches as $b) {
            if (isset($counts[$b->getStage()])) {
                $counts[$b->getStage()]++;
            }
        }
        $counts['all'] = count($batches);
        return $counts;
    }

    /**
     * @param BakingBatchModel[] $batches
     * @return BakingBatchModel[]
     */
    public function filterByStage(array $batches, string $stage): array
    {
        if (!in_array($stage, self::STAGES, true)) {
            return $batches;
        }
        return array_values(array_filter($batches, fn(BakingBatchModel $b) => $b->getStage() === $stage));
    }

    /**
     * La file des ordres : quoi faire, dans l'ordre où ça devient urgent.
     *
     * La frise répond à « où en est la matinée », les cartes à « qu'est-ce que
     * je fais sur cette fournée-là ». Il manquait la question qu'on se pose en
     * entrant dans le fournil : « je fais quoi, là, maintenant ». D'où un tri
     * par échéance seule — un four à vider dans deux minutes passe devant une
     * préparation à lancer dans vingt, quelle que soit l'étape.
     *
     * @param BakingBatchModel[] $batches
     * @return BakingBatchModel[]
     */
    public function orders(array $batches): array
    {
        $rows = array_values(array_filter(
            $batches,
            fn(BakingBatchModel $b) => $b->getActionKey() !== null
        ));

        usort($rows, function (BakingBatchModel $a, BakingBatchModel $b) {
            $c = $a->getDueTime() <=> $b->getDueTime();
            // À échéance égale, ce qui est déjà engagé passe devant : on ne
            // lance pas une préparation avec un four plein qui attend.
            return $c !== 0 ? $c : ($a->isWaiting() <=> $b->isWaiting());
        });

        return $rows;
    }

    /**
     * Fenêtre temporelle du plan de charge, arrondie à l'heure.
     *
     * Calculée sur les fournées affichées plutôt que fixée à la journée : à
     * 07 h, une frise qui va de 05 h à 19 h consacre l'essentiel de sa largeur
     * à du vide.
     *
     * @param BakingBatchModel[] $batches
     * @return array{from: int, to: int, hours: string[]}
     */
    public function window(array $batches, int $nowMinutes): array
    {
        $starts = array_map(fn(BakingBatchModel $b) => $b->getPrepStart(), $batches);
        $ends   = array_map(fn(BakingBatchModel $b) => $b->getShelfTime(), $batches);

        $from = $starts !== [] ? min($starts) : $nowMinutes - 60;
        $to   = $ends   !== [] ? max($ends)   : $nowMinutes + 120;

        // L'instant présent doit rester dans le cadre, même quand toutes les
        // fournées visibles sont derrière ou devant.
        $from = intdiv(min($from, $nowMinutes), 60) * 60;
        $to   = (int)(ceil(max($to, $nowMinutes) / 60) * 60);
        if ($to <= $from) {
            $to = $from + 60;
        }

        $hours = [];
        for ($m = $from; $m < $to; $m += 60) {
            $hours[] = BakingBatchModel::toClock($m);
        }

        return ['from' => $from, 'to' => $to, 'hours' => $hours];
    }

    /** Fait avancer une fournée. Renvoie la réponse brute de l'API. */
    public function advance(int $batchId, string $status, ?int $employeeId = null, ?int $allottedMinutes = null): array
    {
        return $this->bakingRepository->advance($batchId, $status, $employeeId, $allottedMinutes);
    }

    /**
     * Heure courante en minutes depuis minuit, calée sur le serveur quand il
     * la donne. Une tablette d'atelier n'est presque jamais à l'heure.
     */
    public function nowMinutes(?string $serverTime = null): int
    {
        if ($serverTime !== null && preg_match('/^(\d{1,2}):(\d{2})/', $serverTime)) {
            return BakingBatchModel::toMinutes($serverTime);
        }
        return (int)date('G') * 60 + (int)date('i');
    }
}
