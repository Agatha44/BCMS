<?php

namespace App\Traits\Erms;

use App\Models\BridgeBill;
use App\Services\Erms\CreditedAccountResolver;
use App\Services\Erms\ErmsPayloadSigner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * ERMS receivable receipt (2.0) payload for {@see \App\Models\DataManagement\ManageBill\BillPayment} (BILLING_PAYMENTS).
 * POST body: { "data": { camelCase ... }, "signature": "<base64>" }.
 */
trait ErmsReceiptPayloadTrait
{
    use ErmsFormatDateTrait;
    use ErmsSignPayloadTrait;

    public function toErmsPayload(bool $asBatchItem = false): array
    {
        unset($asBatchItem);

        return $this->signPayloadForErms($this->toReceivableReceiptData());
    }

    public function toReceivableReceiptData(): array
    {
        $bill = $this->resolveReceiptBillContext();
        $amount = round((float) ($this->receiptAttr('paid_amount', $bill, 'paid_amt') ?? 0), 2);
        $incomeAmount = round((float) ($amount/1.18), 2);
        $VATAmount = round((float) ($amount - $incomeAmount), 2);
        $controlNumber = (string) ($this->receiptAttr('control_number', $bill, 'contr_num') ?? '');
        $exchequerNo = (string) ($this->receiptAttr('pay_ref_id') ?? '');
        $referenceNumber = (string) ($this->receiptAttr('psp_receipt_num') ?? '');

        $desc = (string) ($this->getAttribute('bill_desc') ?? '');
        if ($desc === '' && $bill !== null) {
            $desc = (string) ('Payment for ' . ($this->billAttr($bill, 'bill_desc') ?? ''));
        }

        $billNumber = (string) ($this->getAttribute('receipt_number') ?? '');
        $title = 'Payment for ' . $desc;
        
        $creditedAccNum = (string) ($this->getAttribute('credited_acc_num') ?? '');
        $creditedMap = app(CreditedAccountResolver::class)->resolve($creditedAccNum);

        // $cfgDebitAccount = (string) $this->receiptConfig('debit_account_code', '');
        $cfgDebitAccount = (string) ($creditedMap['account_code']);
        $debitAccount = $creditedMap !== null && $creditedMap['account_code'] !== ''
            ? (string) $creditedMap['account_code']
            : ($creditedAccNum !== '' ? $creditedAccNum : $cfgDebitAccount);
        $creditAccount = $this->resolveReceiptCreditAccountCode();
        $debitGfs = $creditedMap !== null && $creditedMap['gfs_code'] !== ''
            ? (string) $creditedMap['gfs_code']
            : (string) ($creditedMap['gfs_code']);
        $creditGfs = (string) ($this->resolveReceiptGfs('credit_gfs_code', 'credit_gfs_code', $bill) ?? '');

        $debitLineDesc = (string) $this->receiptConfig('debit_line_description', 'Bank payment');
        $creditLineDesc = (string) $this->receiptConfig('credit_line_description', 'Contribution receivable');
        $portfolioKey = (string) $this->receiptConfig('portfolio_key', 'portifolio');

        $entries = $this->buildReceivableReceiptEntries(
            $amount,
            $incomeAmount,
            $VATAmount,
            $debitAccount,
            $creditAccount,
            $debitGfs,
            $creditGfs,
            $debitLineDesc,
            $creditLineDesc,
        );

        $data = [
            'description' => 'Payment for ' . $desc,
            'branchCode' => (string) (($this->defaultConfig('branch_code'))),
            // 'departmentCode' => (string) (($this->defaultConfig('department_code'))),
            'departmentCode' => (string) (($this->receiptConfig('department_code'))),
            'receiptDate' => $this->parsePaymentTransactionDateIso(),
            'bankAccountNo' => (string) ($this->getAttribute('credited_acc_num')),
            // 'bankAccountNo' => (string) ('011103000689'),
            'billNumber' => $billNumber,
            'controlNumber' => $controlNumber,
            'referenceNumber' => $referenceNumber,
            'exchequerNo' => $exchequerNo,
            'title' => $title,
            'businessLineCode' => (string) ($this->receiptConfig('business_line_code')),
            // 'subActivityCode' => (string) ($this->receiptConfig('sub_activity_code')),
            'client' => $this->buildReceiptClient($bill),
            'entries' => $entries,
            'generatedBy' => $this->resolveReceiptGeneratedBy(),
            'amount' => $amount,
            'currencyCode' => (string) ($this->getAttribute('currency') ?? 'TZS'),
            'exchangeRate' => (float) $this->defaultConfig('exchange_rate', 1),
        ];

        $data[$portfolioKey] = $this->receiptPortfolioLines();

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $innerDataList
     * @return array<int, array{data: array<string, mixed>, signature: string}>
     */
    public static function wrapReceivableReceiptBatch(array $innerDataList): array
    {
        $signer = app(ErmsPayloadSigner::class);

        return array_map(static fn (array $d) => $signer->signDataEnvelope($d), $innerDataList);
    }

    public function toErmsHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getErmsEndpoint(): string
    {
        return (string) config(
            'erms.urls.create_sale_receipt',
            Config::get('services.erms.create_sale_receipt_url', Config::get('services.erms.receivable_receipt_url', Config::get('services.erms.receivable_receipt_path', '')))
        );
    }

    public function validateErmsPayload(): void
    {
        if ($this->getAttribute('reversed_at') !== null) {
            throw new InvalidArgumentException('Cannot submit reversed bill payment as receivable receipt to ERMS.');
        }

        $required = ['paid_amount' => 'amount', 'control_number' => 'controlNumber'];
        foreach ($required as $attr => $api) {
            $v = $this->getAttribute($attr);
            if ($v === null || $v === '') {
                throw new InvalidArgumentException("Receivable receipt (ERMS) payload missing required field: {$api} ({$attr}).");
            }
        }

        if ($this->parsePaymentTransactionDateIso() === '') {
            throw new InvalidArgumentException('Receivable receipt (ERMS) receiptDate invalid: check transaction_datetime.');
        }

        $cn = preg_replace('/\D/', '', (string) $this->getAttribute('control_number'));
        if ($cn === '' || strlen($cn) < 10 || strlen($cn) > 20) {
            throw new InvalidArgumentException('Receivable receipt (ERMS) controlNumber must be numeric and 10 to 20 digits.');
        }

        $creditedAccNum = (string) ($this->getAttribute('credited_acc_num') ?? '');
        $creditedMap = app(CreditedAccountResolver::class)->resolve($creditedAccNum);
        $cfgDebitAccount = (string) ($creditedMap['account_code']);
        $debitAccount = $cfgDebitAccount;

        $cAcc = $this->resolveReceiptCreditAccountCode();
        if (! is_string($cAcc) || $cAcc === '') {
            if ($this->isTbsDeferredReceivableReceipt()) {
                throw new InvalidArgumentException('Configure erms.miscellaneous_entries.tbs_bundle_deferred_account_code (ERMS_MISC_TBS_BUNDLE_DEFERRED_ACCOUNT) for TBS bridge bill receipt.');
            }
            if ($this->isTopUpReceivableReceipt()) {
                throw new InvalidArgumentException('Configure erms.receivable_receipt.prepayment_credit_account_code (ERMS_RECEIPT_PREPAYMENT_ACCOUNT_CODE) for top-up receivable receipt.');
            }
            throw new InvalidArgumentException('Configure services.erms.receivable_receipt.credit_account_code (ERMS_RECEIPT_CREDIT_ACCOUNT).');
        }

        $bill = $this->resolveReceiptBillContext();

        $debitGfs = (string) ($creditedMap['gfs_code']);
        $creditGfs = $this->resolveReceiptGfs('credit_gfs_code', 'credit_gfs_code', $bill);
        if ($debitGfs === null || $debitGfs === '' || $creditGfs === null || $creditGfs === '') {
            throw new InvalidArgumentException('Receivable receipt (ERMS) entries need gfsCode: set receivable_receipt GFS or ERMS_GEPG_GFS_CODE.');
        }

        if ($this->isBridgeBillVatApplicable($bill)) {
            $vatAcc = $this->receiptConfig('vat_credit_account_code');
            $vatGfsCfg = $this->receiptConfig('vat_credit_gfs_code');
            if (! is_string($vatAcc) || $vatAcc === '') {
                throw new InvalidArgumentException('Configure erms.receivable_receipt.vat_credit_account_code (ERMS_RECEIPT_VAT_CREDIT_ACCOUNT) for bridge bill receipts.');
            }
            if (! is_string($vatGfsCfg) || $vatGfsCfg === '') {
                throw new InvalidArgumentException('Configure erms.receivable_receipt.vat_credit_gfs_code (ERMS_RECEIPT_VAT_CREDIT_GFS) for bridge bill receipts.');
            }
        }

        $branch = $this->defaultConfig('branch_code');
        // $dept = $this->defaultConfig('department_code');
        $dept = $this->receiptConfig('department_code');
        if ($branch === null || $branch === '' || $dept === null || $dept === '') {
            throw new InvalidArgumentException('Receivable receipt (ERMS) needs branchCode and departmentCode.');
        }

        if ($this->resolveReceiptGeneratedBy() === '') {
            throw new InvalidArgumentException('Receivable receipt (ERMS) generatedBy empty: set ERMS_GENERATED_BY.');
        }
    }

    /**
     * @return array<string, mixed|null>
     */
    protected function buildReceiptClient(?object $bill): array
    {
        $explicitClientCode = $this->getAttribute('client_code');
        $useExplicitClientCode = is_string($explicitClientCode) && $explicitClientCode !== '';

        if ($useExplicitClientCode) {
            $code = (string) $explicitClientCode;
        } else {
            $code = $bill !== null ? (string) ($this->billAttr($bill, 'bill_gen_by') ?? $this->billAttr($bill, 'created_by') ?? '') : '';
        }

        $name = (string) ($this->getAttribute('payer_name') ?? '');
        if ($name === '' && $bill !== null) {
            $name = (string) ($this->billAttr($bill, 'payer_name') ?? '');
        }

        $email = $this->getAttribute('payer_email');
        if (($email === null || $email === '') && $bill !== null) {
            $email = $this->billAttr($bill, 'payer_email');
        }

        $phoneRaw = (string) ($this->getAttribute('payer_cell_number'));

        if ($phoneRaw === '' && $bill !== null) {
            $phoneRaw = (string) ($this->billAttr($bill, 'payer_cell'));
        }

        if (! $useExplicitClientCode) {
            $code = $code !== '' ? $code : (string) $this->getAttribute('bill_gen_by');
        }
        $tin = $bill !== null ? (string) ($this->billAttr($bill, 'tin')) : '';
        $vrn = $bill !== null ? (string) ($this->billAttr($bill, 'vrn')) : '';

        return [
            'clientType' => $this->resolveReceiptClientType($bill),
            'loyaltyType' => 'RECURRING',
            'clientCategory' => 'CUSTOMER',
            'code' => $code,
            'name' => $name,
            'email' => ($email !== null && $email !== '') ? (string) $email : null,
            'phone' => $this->normalizeReceiptClientPhone($phoneRaw),
            'address' => $this->receiptClientAddress(),
            'tin' => $tin !== '' ? $tin : null,
            'vrn' => $vrn !== '' ? $vrn : null,
        ];
    }

    protected function receiptClientAddress(): ?string
    {
        $addr = $this->defaultConfig('client_address');
        if (is_string($addr) && $addr !== '') {
            return $addr;
        }

        return null;
    }

    protected function resolveReceiptClientType(?object $bill): string
    {
        if ($bill === null) {
            return (string) $this->defaultConfig('default_client_type');
        }

        $pt = strtoupper((string) ($this->billAttr($bill, 'payer_type')));

        return match (true) {
            str_contains($pt, 'INDIVIDUAL'),
            str_contains($pt, 'PUBLIC_INSTITUTION'),
            str_contains($pt, 'PRIVATE_INSTITUTION'),
            str_contains($pt, 'SELF') => 'INDIVIDUAL',
            default => (string) $this->defaultConfig('default_client_type'),
        };
    }

    protected function resolveReceiptGeneratedBy(): string
    {
        return (string) $this->defaultConfig('generated_by');
    }

    protected function parsePaymentTransactionDateIso(): string
    {
        $raw = $this->getAttribute('transaction_datetime');
        if ($raw === null || $raw === '') {
            return '';
        }

        if (is_string($raw)) {
            try {
                $normalized = preg_replace('/\.\d+Z?$/', '', $raw);
                $normalized = str_replace('T', ' ', (string) $normalized);

                return Carbon::parse($normalized)->format('Y-m-d');
            } catch (\Throwable) {
                return '';
            }
        }

        return $this->formatReceivableDateIso($raw);
    }

    protected function normalizeReceiptClientPhone(string $cell): string
    {
        $cell = preg_replace('/\s+/', '', $cell) ?? '';
        if ($cell === '' || $cell === '0') {
            return '0';
        }

        $prefix = (string) $this->defaultConfig('phone_country_prefix', '255');
        if (str_starts_with($cell, '+')) {
            return ltrim($cell, '+');
        }
        if (str_starts_with($cell, '0')) {
            return $prefix.substr($cell, 1);
        }
        if (str_starts_with($cell, $prefix)) {
            return $cell;
        }

        return $prefix.$cell;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function receiptPortfolioLines(): array
    {
        $lines = $this->receiptConfig('portfolio');
        if (is_array($lines) && $lines !== []) {
            return array_values($lines);
        }

        return [];
    }

    protected function receiptGepgFallback(string $key, ?object $bill): ?string
    {
        if ($bill !== null) {
            $v = $this->billAttr($bill, $key);
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }

        $cfg = $this->receiptGepgConfig($key);

        return $cfg !== null && $cfg !== '' ? (string) $cfg : null;
    }

    /**
     * Top-up: prepayment account. TBS bundle payment: TBS deferred account (income posted at expiry).
     */
    protected function resolveReceiptCreditAccountCode(): string
    {
        if ($this->isTbsDeferredReceivableReceipt()) {
            return (string) $this->miscellaneousEntriesConfig('tbs_bundle_deferred_account_code');
        }

        if ($this->isTopUpReceivableReceipt()) {
            return (string) $this->receiptConfig('prepayment_credit_account_code');
        }

        return (string) $this->receiptConfig('credit_account_code');
    }

    protected function isTopUpReceivableReceipt(): bool
    {
        $st = strtoupper((string) ($this->getAttribute('source_type')));

        return $st === 'PREPAYMENT' || $st === 'TOP_UP';
    }

    /**
     * TBS toll-bundle payment: defer revenue until bundle expiry (miscellaneous entry).
     */
    protected function isTbsDeferredReceivableReceipt(): bool
    {
        if (! $this->isBridgeBillReceivableReceipt()) {
            return false;
        }

        return $this->resolveBridgeBillSource() === 'TBS';
    }

    protected function isDeferredReceivableReceipt(): bool
    {
        return $this->isTopUpReceivableReceipt() || $this->isTbsDeferredReceivableReceipt();
    }

    protected function resolveBridgeBillSource(?object $bill = null): string
    {
        $bill ??= $this->resolveReceiptBillContext();
        $src = (string) ($this->getAttribute('source') ?? '');
        if ($src === '' && $bill !== null) {
            $src = (string) ($this->billAttr($bill, 'source') ?? '');
        }

        return strtoupper(trim($src));
    }

    protected function isBridgeBillReceivableReceipt(): bool
    {
        return strtoupper((string) ($this->getAttribute('source_type') ?? '')) === 'BRIDGE_BILL';
    }

    /**
     * Bridge bills are normally posted as 3 lines (income + VAT).
     * ADV: no VAT. TBS: deferred to bundle expiry (2 lines to prepayment at payment time).
     */
    protected function isBridgeBillVatApplicable(?object $bill = null): bool
    {
        if (! $this->isBridgeBillReceivableReceipt()) {
            return false;
        }

        $src = $this->resolveBridgeBillSource($bill);

        return $src !== 'ADV' && $src !== 'TBS';
    }

    /**
     * Two lines by default; three lines (net revenue + VAT credits) for {@see isBridgeBillReceivableReceipt()}.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildReceivableReceiptEntries(
        float $amount,
        float $incomeAmount,
        float $vatAmount,
        string $debitAccount,
        string $creditAccount,
        string $debitGfs,
        string $creditGfs,
        string $debitLineDesc,
        string $creditLineDesc,
    ): array {
        $debitLine = [
            'accountCode' => $debitAccount,
            'amount' => $amount,
            'gfsCode' => $debitGfs,
            'description' => $debitLineDesc,
            'bookSide' => 'DEBIT',
            'serviceEntry' => false,
            'taxEntry' => false,
        ];

        $bill = $this->resolveReceiptBillContext();

        // Non-bridge-bill (and bridge-bill ADV): 2 lines only (debit + credit).
        if (! $this->isBridgeBillReceivableReceipt() || ! $this->isBridgeBillVatApplicable($bill)) {
            return [
                $debitLine,
                [
                    'accountCode' => $creditAccount,
                    'amount' => $amount,
                    'gfsCode' => $creditGfs,
                    'description' => $creditLineDesc,
                    'bookSide' => 'CREDIT',
                    'serviceEntry' => false,
                    'taxEntry' => false,
                ],
            ];
        }

        $vatAccount = (string) $this->receiptConfig('vat_credit_account_code', '');
        $vatGfs = (string) $this->receiptConfig('vat_credit_gfs_code', '');
        $vatDesc = (string) $this->receiptConfig('vat_credit_line_description', 'VAT');

        return [
            $debitLine,
            [
                'accountCode' => $creditAccount,
                'amount' => $incomeAmount,
                'gfsCode' => $creditGfs,
                'description' => $creditLineDesc,
                'bookSide' => 'CREDIT',
                'serviceEntry' => false,
                'taxEntry' => false,
            ],
            [
                'accountCode' => $vatAccount,
                'amount' => $vatAmount,
                'gfsCode' => $vatGfs,
                'description' => $vatDesc,
                'bookSide' => 'CREDIT',
                'serviceEntry' => false,
                'taxEntry' => false,
            ],
        ];
    }

    protected function receiptConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.receivable_receipt.'.$key, Config::get('services.erms.receivable_receipt.'.$key, $default));
    }

    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.' . $key, Config::get('services.erms.default_config.' . $key, $default));
    }

    protected function receiptGepgConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.gepg.'.$key, Config::get('services.erms.gepg.'.$key, $default));
    }

    protected function miscellaneousEntriesConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.miscellaneous_entries.'.$key, $default);
    }

    protected function resolveReceiptGfs(string $receiptKey, string $invoiceKey, ?object $bill): ?string
    {
        if ($receiptKey === 'credit_gfs_code' && $this->isTbsDeferredReceivableReceipt()) {
            $tbsDeferredGfs = $this->miscellaneousEntriesConfig('tbs_bundle_deferred_gfs_code');
            if (is_string($tbsDeferredGfs) && $tbsDeferredGfs !== '') {
                return $tbsDeferredGfs;
            }
        }

        if ($receiptKey === 'credit_gfs_code' && $this->isTopUpReceivableReceipt()) {
            $prepaymentCreditGfs = $this->receiptConfig('prepayment_credit_gfs_code');
            if (is_string($prepaymentCreditGfs) && $prepaymentCreditGfs !== '') {
                return $prepaymentCreditGfs;
            }
        }

        $fromReceipt = $this->receiptConfig($receiptKey);
        if (is_string($fromReceipt) && $fromReceipt !== '') {
            return $fromReceipt;
        }

        $fromInvoice = config('erms.receivable_invoice.'.$invoiceKey);
        if (is_string($fromInvoice) && $fromInvoice !== '') {
            return $fromInvoice;
        }

        return $this->receiptGepgFallback('gfs_code', $bill);
    }

    protected function resolveReceiptBillContext(): ?object
    {
        if (method_exists($this, 'relationLoaded') && $this->relationLoaded('bridgeBill')) {
            return $this->getRelation('bridgeBill');
        }

        if (method_exists($this, 'bridgeBill')) {
            $this->loadMissing('bridgeBill');

            return $this->getRelation('bridgeBill');
        }

        if ($this instanceof BridgeBill) {
            return $this;
        }

        return null;
    }

    protected function billAttr(object $bill, string $key): mixed
    {
        if (method_exists($bill, 'getAttribute')) {
            return $bill->getAttribute($key);
        }

        return $bill->{$key} ?? null;
    }

    protected function receiptAttr(string $receiptKey, ?object $bill = null, ?string $billKey = null): mixed
    {
        $value = $this->getAttribute($receiptKey);
        if ($value !== null && $value !== '') {
            return $value;
        }

        if ($bill !== null && $billKey !== null && $billKey !== '') {
            return $this->billAttr($bill, $billKey);
        }

        return null;
    }
}
