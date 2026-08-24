<?php

namespace App\Services\Erms;

use App\Models\BridgeBill;
use App\Models\EventPayment;
use App\Models\IncidentFine;
use App\Models\OverloadFine;
use App\Services\Erms\Mappers\BridgeBillReceiptMapper;
use App\Services\Erms\Mappers\EventPaymentReceiptMapper;
use App\Services\Erms\Mappers\IncidentFineReceiptMapper;
use App\Services\Erms\Mappers\OverloadFineReceiptMapper;
use App\Services\Erms\Mappers\PrepaymentMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ErmsReceiptRetryService
{
    public function __construct(
        private ErmsReceiptSubmissionService $submissionService,
        private OverloadFineReceiptMapper $overloadFineReceiptMapper,
        private IncidentFineReceiptMapper $incidentFineReceiptMapper,
        private BridgeBillReceiptMapper $bridgeBillReceiptMapper,
        private EventPaymentReceiptMapper $eventPaymentReceiptMapper,
        private PrepaymentMapper $prepaymentMapper,
    ) {
    }

    public function isEnabled(): bool
    {
        return filter_var(config('erms.receipt_retry.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function resolveLimitPerSource(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return max(1, (int) config('erms.receipt_retry.limit_per_source'));
    }

    public function trxDtTmFrom(): ?string
    {
        $from = trim((string) config('erms.receipt_retry.trx_dt_tm_from'));

        return $from !== '' ? $from : null;
    }

    /**
     * @return array{
     *   posted: int,
     *   failed: int,
     *   skipped: int,
     *   dry_run: int,
     *   sources: array<string, array{
     *     label: string,
     *     posted: int,
     *     failed: int,
     *     skipped: int,
     *     dry_run: int,
     *     pending_total: int
     *   }>
     * }
     */
    public function retryPendingReceipts(?int $limitPerSource = null, bool $dryRun = false, ?string $typeFilter = null): array
    {
        $totals = ['posted' => 0, 'failed' => 0, 'skipped' => 0, 'dry_run' => 0, 'sources' => []];
        $limit = $this->resolveLimitPerSource($limitPerSource);

        foreach ($this->configuredSources() as $key => $source) {
            if ($typeFilter !== null && $typeFilter !== $key) {
                continue;
            }

            if (! Schema::hasTable($source['table'])) {
                Log::warning('ERMS receipt retry: table missing', ['table' => $source['table']]);

                continue;
            }

            if (! Schema::hasColumn($source['table'], 'erp_status')) {
                Log::warning('ERMS receipt retry: erp_status column missing', ['table' => $source['table']]);

                continue;
            }

            $result = $this->retrySource($key, $source, $limit, $dryRun);
            $totals['sources'][$key] = [
                'label' => $source['label'],
                'posted' => $result['posted'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
                'dry_run' => $result['dry_run'],
                'pending_total' => $result['pending_total'],
            ];

            foreach (['posted', 'failed', 'skipped', 'dry_run'] as $metric) {
                $totals[$metric] += $result[$metric];
            }
        }

        return $totals;
    }

    /**
     * @return array<string, array{
     *   enabled: bool,
     *   label: string,
     *   table: string,
     *   order_column: string
     * }>
     */
    public function configuredSources(): array
    {
        $sources = config('erms.receipt_retry.sources', []);

        return array_filter($sources, static function (array $source): bool {
            return filter_var($source['enabled'], FILTER_VALIDATE_BOOLEAN);
        });
    }

    /**
     * @param  array{label: string, table: string, order_column: string}  $source
     * @return array{posted: int, failed: int, skipped: int, dry_run: int, pending_total: int}
     */
    private function retrySource(string $key, array $source, int $limit, bool $dryRun): array
    {
        $counts = ['posted' => 0, 'failed' => 0, 'skipped' => 0, 'dry_run' => 0, 'pending_total' => 0];
        $counts['pending_total'] = $this->countPendingRows($source['table']);
        $rows = $this->fetchPendingRows($source['table'], $source['order_column'], $limit);

        foreach ($rows as $row) {
            $label = sprintf('%s #%s', $source['label'], $row->id);

            if (! $this->isEligibleForSubmission($row)) {
                $counts['skipped']++;

                continue;
            }

            if ($dryRun) {
                $counts['dry_run']++;
                Log::info('ERMS receipt retry dry-run: would submit', [
                    'source' => $key,
                    'id' => $row->id,
                    'receipt_number' => $row->receipt_number ?? null,
                    'trx_dt_tm' => $row->trx_dt_tm ?? null,
                ]);

                continue;
            }

            $outcome = $this->submitReceipt($key, (int) $row->id, $source['table']);

            if ($outcome === 'posted') {
                $counts['posted']++;
                Log::info('ERMS receipt retry posted', ['source' => $key, 'id' => $row->id, 'label' => $label]);
            } elseif ($outcome === 'failed') {
                $counts['failed']++;
                Log::warning('ERMS receipt retry failed', ['source' => $key, 'id' => $row->id, 'label' => $label]);
            } else {
                $counts['skipped']++;
            }
        }

        return $counts;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function pendingRowsQuery(string $table)
    {
        $query = DB::table($table)
            ->whereNotNull('psp_receipt_num')
            ->where('psp_receipt_num', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('erp_status')
                    ->orWhere('erp_status', '!=', 1);
            });

        if (Schema::hasColumn($table, 'paid_amt')) {
            $query->whereNotNull('paid_amt')->where('paid_amt', '>', 0);
        }

        $trxFrom = $this->trxDtTmFrom();
        if ($trxFrom !== null && Schema::hasColumn($table, 'trx_dt_tm')) {
            $query->whereNotNull('trx_dt_tm')->where('trx_dt_tm', '>=', $trxFrom);
        }

        return $query;
    }

    private function countPendingRows(string $table): int
    {
        return $this->pendingRowsQuery($table)->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchPendingRows(string $table, string $orderColumn, int $limit)
    {
        $query = $this->pendingRowsQuery($table)->limit(max(1, $limit));

        if (Schema::hasColumn($table, $orderColumn)) {
            $query->orderBy($orderColumn);
        } else {
            $query->orderBy('id');
        }

        $columns = ['id', 'psp_receipt_num', 'erp_status', 'receipt_number', 'pay_ref_id'];
        if (Schema::hasColumn($table, 'trx_dt_tm')) {
            $columns[] = 'trx_dt_tm';
        }

        return $query->get($columns);
    }

    private function isEligibleForSubmission(object $row): bool
    {
        if (empty($row->psp_receipt_num)) {
            return false;
        }

        return (int) ($row->erp_status ?? 0) !== 1;
    }

    /**
     * @return 'posted'|'failed'|'skipped'
     */
    private function submitReceipt(string $sourceKey, int $id, string $table): string
    {
        try {
            $payload = $this->mapReceiptPayload($sourceKey, $id);
            if ($payload === null) {
                return 'skipped';
            }

            $submission = $this->submissionService->submit($payload);

            DB::table($table)
                ->where('id', $id)
                ->update([
                    'erp_status' => $submission['ok'] ? 1 : 2,
                    'http_status' => $submission['http_status'],
                    'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                ]);

            return $submission['ok'] ? 'posted' : 'failed';
        } catch (\Throwable $e) {
            DB::table($table)
                ->where('id', $id)
                ->update(['erp_status' => 2]);

            Log::error('ERMS receipt retry exception', [
                'source' => $sourceKey,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapReceiptPayload(string $sourceKey, int $id): ?array
    {
        return match ($sourceKey) {
            'overload_fine' => $this->mapOverloadFine($id),
            'incident_fine' => $this->mapIncidentFine($id),
            'bridge_bill' => $this->mapBridgeBill($id),
            'event_payment' => $this->mapEventPayment($id),
            'top_up' => $this->mapTopUp($id),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapOverloadFine(int $id): ?array
    {
        $fine = OverloadFine::query()->find($id);

        return $fine !== null ? $this->overloadFineReceiptMapper->map($fine) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapIncidentFine(int $id): ?array
    {
        $fine = IncidentFine::query()->find($id);

        return $fine !== null ? $this->incidentFineReceiptMapper->map($fine) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapBridgeBill(int $id): ?array
    {
        $bill = BridgeBill::query()->find($id);

        return $bill !== null ? $this->bridgeBillReceiptMapper->map($bill) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapEventPayment(int $id): ?array
    {
        $payment = EventPayment::query()->find($id);

        return $payment !== null ? $this->eventPaymentReceiptMapper->map($payment) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapTopUp(int $id): ?array
    {
        $row = DB::table('top_up')->where('id', $id)->first();

        return $row !== null ? $this->prepaymentMapper->map($row) : null;
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return array_keys(config('erms.receipt_retry.sources', []));
    }
}
