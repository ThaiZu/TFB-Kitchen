<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Models\Production\MepLineModel;
use App\Kitchen\app\Services\Baking\BakingService;
use App\Kitchen\app\Services\Production\ProductionBoardService;
use App\Kitchen\app\Services\Production\ProductionService;
use App\Kitchen\app\Services\Production\StockOutlookService;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService,
        private ProductionBoardService $boardService,
        // L'étape d'un produit vient du plan de cuisson, pas d'un second champ
        // qui dériverait : une seule source de vérité pour « où en est ce
        // produit », partagée avec le module Cuisson.
        private BakingService $bakingService,
        private StockOutlookService $outlookService
    ) {}

    /**
     * GET /production[?view=mep|morning|noon|afternoon|stock][&mep=morning|afternoon]
     *
     * L'écran travaille toujours pour aujourd'hui : pas de sélecteur de date.
     * Une cuisine ne produit pas pour hier, et un écran qui peut afficher une
     * autre journée finit par en afficher une par erreur au milieu du service.
     * Seul l'encodage de la MEP regarde demain, et il le dit.
     */
    public function index(): void
    {
        $today   = date('Y-m-d');
        $periods = $this->productionService->getPeriods();
        $view    = $this->readView($periods);

        $data = [
            // Le sélecteur ne montre que ce qui reste à faire ; `periods` garde
            // la journée entière pour les calculs d'horizon.
            'periods'     => $periods,
            'tabs'        => $this->productionService->upcomingPeriods($this->outlookService, $view),
            'active_view' => $view,
            'today'       => $today,
            'params'      => $this->productionService->getParams(),
        ];

        if ($view === 'stock') {
            $products = $this->productionService->getProducts();
            $stock    = $this->productionService->getStock();
            $rebakes  = $this->productionService->getRebakeSuggestions($products, $stock, $today);
            // Le stock lu vers l'avant : ce n'est pas « combien il en reste »
            // qui se décide, c'est « est-ce que ça tient jusqu'au bout ».
            $outlook  = $this->productionService->stockOutlook($stock, $products, $this->outlookService, $today);

            $data += [
                'stock_available'   => $stock !== null,
                'stock'             => $stock ?? [],
                'rebakes'           => $rebakes['suggestions'],
                'rebakes_available' => $rebakes['available'],
                'rebakes_samples'   => $rebakes['samples'],
                'outlook'           => $outlook['rows'],
                'outlook_available' => $outlook['available'],
                'outlook_counts'    => $outlook['counts'],
                'outlook_categories'=> $outlook['categories'],
                'horizons'          => $outlook['horizons'],
            ];
        } elseif ($view === 'mep') {
            $data += $this->mepData($today);
        } else {
            $data += $this->periodData($today, $view);
        }

        $this->view('production/index', $data);
    }

    /**
     * Une période de la journée : le tableau de travail.
     *
     * Quatre sources se rejoignent ici, et aucune n'est facultative pour la
     * même raison : le catalogue dit quoi, la MEP dit ce qui est produit, le
     * plan de cuisson dit où ça en est, le stock et les ventes disent dans
     * quel ordre s'en occuper. Chacune peut manquer sans casser l'écran —
     * elle manque alors *visiblement*.
     */
    private function periodData(string $today, string $view): array
    {
        $products = $this->productionService->getProducts();
        $mep      = $this->productionService->getMep($today);
        $lines    = $mep !== null
            ? $this->productionService->mepLinesForPeriod($mep['lines'], $view)
            : [];

        if ($products === null) {
            return [
                'products_available' => false,
                'mep_available'      => $mep !== null,
                'mep_lines'          => $lines,
                'mep_pending_count'  => count(array_filter($lines, fn(MepLineModel $l) => $l->isPending())),
                'board'              => null,
            ];
        }

        $periodProducts = array_values(array_filter(
            $products,
            fn($p) => $p->isActive() && $p->belongsTo($view)
        ));

        // Le plan de cuisson peut ne pas être servi : les tuiles tombent alors
        // toutes dans « rien en cours », et l'écran le dit plutôt que de
        // laisser croire qu'aucune fournée n'est au four.
        $plan   = $this->bakingService->getPlan($today);
        $stages = $plan !== null ? $this->boardService->stagesByProduct($plan['batches']) : [];

        $stock   = $this->productionService->getStock();
        $tension = $this->productionService->tension($periodProducts, $stock, $today);

        return [
            'products_available' => true,
            'mep_available'      => $mep !== null,
            'mep_lines'          => $lines,
            'mep_pending_count'  => count(array_filter($lines, fn(MepLineModel $l) => $l->isPending())),
            'plan_available'     => $plan !== null,
            'stock_available'    => $stock !== null,
            'forecast_available' => $tension['available'],
            'board'              => $this->boardService->build($periodProducts, $lines, $stages, $tension['map']),
        ];
    }

    /**
     * Les deux temps de la MEP.
     *
     * Le matin, on valide ce qui a été préparé hier : c'est cette validation
     * qui ouvre la vente. L'après-midi, on encode ce qu'on prépare pour
     * demain. Deux gestes opposés — l'un ferme une journée, l'autre en ouvre
     * une — d'où deux écrans plutôt qu'un formulaire à double usage.
     */
    private function mepData(string $today): array
    {
        $sub = ($_GET['mep'] ?? '') === 'afternoon' ? 'afternoon' : 'morning';

        if ($sub === 'morning') {
            $mep     = $this->productionService->getMep($today);
            $lines   = $mep['lines'] ?? [];
            $pending = array_values(array_filter($lines, fn(MepLineModel $l) => $l->isPending()));

            return [
                'mep_sub'            => 'morning',
                'mep_available'      => $mep !== null,
                'mep_status'         => $mep['status'] ?? null,
                'mep_prepared_at'    => $mep['prepared_at'] ?? null,
                'mep_lines'          => $lines,
                'mep_pending'        => $pending,
                // Groupées par catégorie : une MEP arrive dans l'ordre de
                // saisie de la veille, on la relit dans l'ordre du magasin.
                'mep_by_category'    => $this->productionService->mepLinesByCategory($lines),
                'mep_pending_by_cat' => $this->productionService->mepLinesByCategory($pending),
                // La ligne de MEP ne porte pas la taille de fournée : elle vient
                // du catalogue, et c'est elle qui fait le pas des boutons.
                'mep_batch'          => $this->batchSizes(),
            ];
        }

        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        $products = $this->productionService->getProducts();
        // Un brouillon peut déjà exister : on reprend l'encodage là où il en
        // était plutôt que de repartir de zéro à chaque ouverture.
        $draft    = $this->productionService->getMep($tomorrow);

        // Le sélecteur ne propose que ce qui se prépare la veille : sur deux
        // cents références, en proposer dix fait choisir au lieu de chercher.
        $pdb = $products !== null
            ? $this->productionService->pdbProductsByCategory($products)
            : [];

        $byId = [];
        foreach ($products ?? [] as $p) {
            if ($p->getIdProduct() !== null) {
                $byId[$p->getIdProduct()] = $p;
            }
        }

        // Les lignes déjà encodées, enrichies de la fiche produit : c'est elle
        // qui porte le pas de fournée (12 pour un croissant, 15 pour une
        // baguette), et la ligne de MEP ne le transporte pas.
        $rows = [];
        foreach ($draft['lines'] ?? [] as $line) {
            $id      = $line->getIdProduct();
            $product = $id !== null ? ($byId[$id] ?? null) : null;
            $cat     = $line->getCategoryName() ?: ($product?->getCategoryName() ?? '—');

            $rows[$cat][] = [
                'id_product' => $id,
                'name'       => $line->getName() ?? $product?->getName() ?? '—',
                'unit_name'  => $line->getUnitName() ?? $product?->getUnitName(),
                'batch_size' => $product !== null ? $product->getEffectiveBatchSize() : 1.0,
                'quantity'   => $line->getQuantityPlanned(),
            ];
        }

        // Une ligne encodée hier sur un produit qu'on ne prépare plus la veille
        // reste affichée : la retirer de l'écran ne la retire pas de la MEP.
        $categories = array_values(array_unique(array_merge(array_keys($pdb), array_keys($rows))));
        usort($categories, 'strnatcasecmp');

        foreach ($rows as &$group) {
            usort($group, fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));
        }
        unset($group);

        return [
            'mep_sub'            => 'afternoon',
            'tomorrow'           => $tomorrow,
            'products_available' => $products !== null,
            'pdb_catalog'        => $pdb,
            'mep_next_categories'=> $categories,
            'mep_next_rows'      => $rows,
            'draft_available'    => $draft !== null,
        ];
    }

    /**
     * Taille de fournée par produit — le pas des boutons « − » et « + ».
     *
     * Un catalogue muet ne bloque pas la validation : le pas retombe à 1 et
     * on saisit au clavier, ce qui reste préférable à un écran vide.
     *
     * @return array<int, float>
     */
    private function batchSizes(): array
    {
        $sizes = [];
        foreach ($this->productionService->getProducts() ?? [] as $p) {
            if ($p->getIdProduct() !== null) {
                $sizes[$p->getIdProduct()] = $p->getEffectiveBatchSize();
            }
        }
        return $sizes;
    }

    /**
     * GET /ajax/production/stock
     *
     * Stock et propositions dans une seule réponse : deux sondages séparés
     * afficheraient un stock et des propositions calculées sur un autre.
     */
    public function ajaxStock(): void
    {
        $today    = date('Y-m-d');
        $products = $this->productionService->getProducts();
        $stock    = $this->productionService->getStock();
        $rebakes  = $this->productionService->getRebakeSuggestions($products, $stock, $today);

        $this->json([
            'success'           => $stock !== null,
            'stock_available'   => $stock !== null,
            'stock'             => $stock ?? [],
            'rebakes_available' => $rebakes['available'],
            'rebakes_samples'   => $rebakes['samples'],
            'rebakes'           => $rebakes['suggestions'],
        ], $stock !== null ? 200 : 502)->send();
    }

    /**
     * POST /ajax/production/mep/validate
     *
     * Corps : { lines: [ { id, quantity, skipped? } ] } — toujours pour
     * aujourd'hui.
     */
    public function ajaxValidateMep(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input) || !is_array($input['lines'] ?? null)) {
            $this->json(['success' => false, 'description' => 'invalid_payload'], 400)->send();
            return;
        }

        $lines = [];
        foreach ($input['lines'] as $line) {
            if (!is_array($line) || !isset($line['id'])) {
                continue;
            }
            $entry = [
                'id'       => (int)$line['id'],
                'quantity' => max(0, (float)($line['quantity'] ?? 0)),
            ];
            if (!empty($line['skipped'])) {
                $entry['skipped'] = true;
            }
            $lines[] = $entry;
        }

        if ($lines === []) {
            $this->json(['success' => false, 'description' => 'nothing_to_validate'], 400)->send();
            return;
        }

        $response = $this->productionService->validateMep(date('Y-m-d'), $lines);
        $this->json($response, ($response['success'] ?? false) ? 200 : 502)->send();
    }

    /**
     * POST /ajax/production/mep
     *
     * Corps : { date, lines: [ { id_product, quantity } ] }
     * Encodage de la mise en place du lendemain.
     */
    public function ajaxSaveMep(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input) || !is_array($input['lines'] ?? null)) {
            $this->json(['success' => false, 'description' => 'invalid_payload'], 400)->send();
            return;
        }

        $lines = [];
        foreach ($input['lines'] as $line) {
            if (!is_array($line) || empty($line['id_product'])) {
                continue;
            }
            $quantity = max(0, (float)($line['quantity'] ?? 0));
            // Une ligne à zéro n'est pas une mise en place : elle est omise
            // plutôt qu'envoyée, sinon la MEP de demain s'ouvrirait sur
            // quarante lignes vides.
            if ($quantity <= 0) {
                continue;
            }
            $lines[] = ['id_product' => (int)$line['id_product'], 'quantity' => $quantity];
        }

        $date = (string)($input['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }

        $response = $this->productionService->saveMep($date, $lines);
        $this->json($response, ($response['success'] ?? false) ? 200 : 502)->send();
    }

    /**
     * POST /ajax/production/shelf
     *
     * Corps : { id_product, quantity, id_mep_line?, id_employee? }
     *
     * C'est ce geste qui met en vente : la validation de MEP constate ce qui
     * est sorti du four, la mise en rayon décide de ce que la caisse peut
     * vendre. Voir docs/ENDPOINTS_PRODUCTION.md.
     */
    public function ajaxShelve(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);

        $idProduct = (int)($input['id_product'] ?? 0);
        $quantity  = (float)($input['quantity'] ?? 0);

        if ($idProduct <= 0 || $quantity <= 0) {
            $this->json(['success' => false, 'description' => 'invalid_payload'], 400)->send();
            return;
        }

        $response = $this->productionService->shelve(
            $idProduct,
            $quantity,
            isset($input['id_mep_line']) ? (int)$input['id_mep_line'] : null,
            isset($input['id_employee']) ? (int)$input['id_employee'] : null
        );

        $this->json($response, ($response['success'] ?? false) ? 200 : 502)->send();
    }

    /**
     * POST /ajax/production/rebake
     *
     * Corps : { id_product, quantity, id_employee? }
     */
    public function ajaxRebake(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);

        $idProduct = (int)($input['id_product'] ?? 0);
        $quantity  = (float)($input['quantity'] ?? 0);

        if ($idProduct <= 0 || $quantity <= 0) {
            $this->json(['success' => false, 'description' => 'invalid_payload'], 400)->send();
            return;
        }

        $response = $this->productionService->createRebake(
            $idProduct,
            $quantity,
            isset($input['id_employee']) ? (int)$input['id_employee'] : null
        );

        $this->json($response, ($response['success'] ?? false) ? 200 : 502)->send();
    }

    /** @param \App\Kitchen\app\Models\Production\PeriodModel[] $periods */
    private function readView(array $periods): string
    {
        $allowed = array_map(fn($p) => $p->getKey(), $periods);
        $allowed[] = 'stock';
        $allowed[] = 'mep';

        $view = (string)($_GET['view'] ?? '');
        if (in_array($view, $allowed, true)) {
            return $view;
        }
        // Sans choix explicite, on ouvre sur la période en cours : à 15 h, un
        // écran qui s'ouvre sur « Matin » se referme aussitôt.
        return $this->productionService->currentPeriodKey();
    }
}
