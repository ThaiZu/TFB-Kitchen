<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Production\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService
    ) {}

    /**
     * GET /production[?view=morning|noon|afternoon|stock][&date=Y-m-d]
     *
     * Un seul écran, quatre vues. Le toggle ne recharge que ce que la vue
     * demandée exige : la vue Stock n'a pas besoin du catalogue de MEP, et la
     * vue Matin n'a pas besoin du profil de ventes.
     */
    public function index(): void
    {
        $date    = $this->readDate();
        $periods = $this->productionService->getPeriods();
        $view    = $this->readView($periods);

        $data = [
            'periods'       => $periods,
            'active_view'   => $view,
            'selected_date' => $date,
            'today'         => date('Y-m-d'),
            'params'        => $this->productionService->getParams(),
        ];

        if ($view === 'stock') {
            $products = $this->productionService->getProducts();
            $stock    = $this->productionService->getStock();
            $rebakes  = $this->productionService->getRebakeSuggestions($products, $stock, $date);

            $data += [
                'stock_available' => $stock !== null,
                'stock'           => $stock ?? [],
                'rebakes'         => $rebakes['suggestions'],
                'rebakes_available' => $rebakes['available'],
                'rebakes_samples'   => $rebakes['samples'],
            ];
        } else {
            $products = $this->productionService->getProducts();
            $mep      = $this->productionService->getMep($date);

            $data += [
                'products_available' => $products !== null,
                'groups'             => $products !== null
                    ? $this->productionService->groupByCategory($products, $view)
                    : [],
                'mep_available'      => $mep !== null,
                'mep_status'         => $mep['status'] ?? null,
                'mep_prepared_at'    => $mep['prepared_at'] ?? null,
                'mep_lines'          => $mep !== null
                    ? $this->productionService->mepLinesForPeriod($mep['lines'], $view)
                    : [],
            ];
        }

        $this->view('production/index', $data);
    }

    /**
     * GET /ajax/production/stock
     *
     * Stock et propositions dans une seule réponse : deux sondages séparés
     * afficheraient un stock et des propositions calculées sur un autre.
     */
    public function ajaxStock(): void
    {
        $date     = $this->readDate();
        $products = $this->productionService->getProducts();
        $stock    = $this->productionService->getStock();
        $rebakes  = $this->productionService->getRebakeSuggestions($products, $stock, $date);

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
     * Corps : { date, lines: [ { id, quantity, skipped? } ] }
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

        $date     = $this->readDate($input['date'] ?? null);
        $response = $this->productionService->validateMep($date, $lines);

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

    private function readDate(?string $raw = null): string
    {
        $date = $raw ?? ($_GET['date'] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? (string)$date : date('Y-m-d');
    }

    /** @param \App\Kitchen\app\Models\Production\PeriodModel[] $periods */
    private function readView(array $periods): string
    {
        $allowed = array_map(fn($p) => $p->getKey(), $periods);
        $allowed[] = 'stock';

        $view = (string)($_GET['view'] ?? '');
        if (in_array($view, $allowed, true)) {
            return $view;
        }
        // Sans choix explicite, on ouvre sur la période en cours : à 15 h, un
        // écran qui s'ouvre sur « Matin » se referme aussitôt.
        return $this->productionService->currentPeriodKey();
    }
}
