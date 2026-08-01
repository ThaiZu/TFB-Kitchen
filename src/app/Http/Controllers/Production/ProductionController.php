<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Models\Production\MepLineModel;
use App\Kitchen\app\Services\Production\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService
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
            'periods'     => $periods,
            'active_view' => $view,
            'today'       => $today,
            'params'      => $this->productionService->getParams(),
        ];

        if ($view === 'stock') {
            $products = $this->productionService->getProducts();
            $stock    = $this->productionService->getStock();
            $rebakes  = $this->productionService->getRebakeSuggestions($products, $stock, $today);

            $data += [
                'stock_available'   => $stock !== null,
                'stock'             => $stock ?? [],
                'rebakes'           => $rebakes['suggestions'],
                'rebakes_available' => $rebakes['available'],
                'rebakes_samples'   => $rebakes['samples'],
            ];
        } elseif ($view === 'mep') {
            $data += $this->mepData($today);
        } else {
            $products = $this->productionService->getProducts();
            $mep      = $this->productionService->getMep($today);
            $lines    = $mep !== null
                ? $this->productionService->mepLinesForPeriod($mep['lines'], $view)
                : [];

            $data += [
                'products_available' => $products !== null,
                'groups'             => $products !== null
                    ? $this->productionService->groupByCategory($products, $view)
                    : [],
                'mep_available'      => $mep !== null,
                'mep_lines'          => $lines,
                'mep_pending_count'  => count(array_filter($lines, fn(MepLineModel $l) => $l->isPending())),
            ];
        }

        $this->view('production/index', $data);
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
