<?php

namespace App\Services\Reports;

use App\Http\Controllers\Reports\CollectionReportController;
use App\Models\ReportEngineDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Executes collection reports by handler slug via the existing CollectionReportController.
 */
class CollectionReportExecutor
{
    private const KNOWN_HANDLERS = [
        'daily-collection',
        'daily-shift-collection',
        'body-type-collection',
        'booth-collection',
        'body-type-audit',
        'exempted-vehicles',
        'daily-cashless',
        'body-cashless',
        'cancelled-transactions',
        'bundle-collection',
        'bundle-registration',
        'bundle-subscription',
        'vehicle-passage',
        'vehicle-passage/paginated',
        'toll-collection-detail',
        'payment-reconciliation',
        'end-of-shift-overall',
        'shift-summary',
        'shift-summary-audit',
        'toll-collection-summary',
        'incident-collection-summary',
        'overload-collection-summary',
        'event-collection-summary',
        'monthly-collection-summary',
        'shift-collection',
    ];

    public static function isKnownHandler(string $handler): bool
    {
        return in_array($handler, self::KNOWN_HANDLERS, true);
    }

    public function __construct(
        protected CollectionReportController $controller,
        protected ReportEngineSqlExecutor $sqlExecutor
    ) {
    }

    /**
     * @return array{success: bool, status_code: int, data: mixed, message: string}
     */
    public function execute(string $handler, array $params): array
    {
        $request = Request::create('/api/collection-reports/' . $handler, 'POST', $params);
        $request->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($params));

        /** @var JsonResponse $response */
        $response = match ($handler) {
            'daily-collection' => $this->controller->dailyCollection($request),
            'daily-shift-collection' => $this->controller->dailyShiftCollection($request),
            'body-type-collection' => $this->controller->bodyTypeCollection($request),
            'booth-collection' => $this->controller->boothCollection($request),
            'body-type-audit' => $this->controller->bodyTypeAudit($request),
            'exempted-vehicles' => $this->controller->exemptedVehicles($request),
            'daily-cashless' => $this->controller->dailyCashlessCollection($request),
            'body-cashless' => $this->controller->bodyCashlessCollection($request),
            'cancelled-transactions' => $this->controller->cancelledTransactions($request),
            'bundle-collection' => $this->controller->bundleCollection($request),
            'bundle-registration' => $this->controller->bundleRegistration($request),
            'bundle-subscription' => $this->controller->bundleSubscription($request),
            'vehicle-passage' => $this->controller->vehiclePassage($request),
            'vehicle-passage/paginated' => $this->controller->vehiclePassagePaginated($request),
            'toll-collection-detail' => $this->controller->tollCollectionDetail($request),
            'payment-reconciliation' => $this->controller->paymentReconciliation($request),
            'end-of-shift-overall' => $this->controller->endOfShiftOverall($request),
            'shift-summary' => $this->controller->shiftSummary($request),
            'shift-summary-audit' => $this->controller->shiftSummaryAudit($request),
            'toll-collection-summary' => $this->controller->tollCollectionSummary($request),
            'incident-collection-summary' => $this->controller->incidentCollectionSummary($request),
            'overload-collection-summary' => $this->controller->overloadCollectionSummary($request),
            'event-collection-summary' => $this->controller->eventCollectionSummary($request),
            'monthly-collection-summary' => $this->controller->monthlyCollectionSummary($request),
            'shift-collection' => $this->controller->shiftCollection($request),
            default => $this->executeFromDefinition($handler, $params),
        };

        $payload = json_decode($response->getContent(), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid report response');
        }

        if (($payload['success'] ?? false) === false) {
            $errors = $payload['data'] ?? [];
            throw ValidationException::withMessages(is_array($errors) ? $errors : ['report' => [$payload['message'] ?? 'Report failed']]);
        }

        return $payload;
    }

    /**
     * Run admin-defined reports stored on report_engine_definitions.query.
     *
     * @return array{success: bool, status_code: int, data: mixed, message: string}
     */
    protected function executeFromDefinition(string $handler, array $params): array
    {
        $definition = ReportEngineDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($handler) {
                $query->where('handler', $handler)
                    ->orWhere('script', $handler)
                    ->orWhere('key', $handler);
            })
            ->first();

        if (!$definition) {
            throw new \InvalidArgumentException("Unknown report handler: {$handler}");
        }

        return $this->sqlExecutor->execute($definition, $params);
    }
}
