<?php

namespace App\Traits\Erms;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/** ERMS payable request: assemble inner `data`, validate, sign via {@see ErmsSignPayloadTrait}. */
trait ErmsPayaplePayloadTrait
{
    use ErmsSignPayloadTrait;

    /** @return array<string, mixed> */
    protected function ermsPayablePayloadParts(): array
    {
        $parts = $this->getAttribute('erms_payable_parts');
        if (! is_array($parts)) {
            throw new InvalidArgumentException('Set attribute erms_payable_parts (array) or override ermsPayablePayloadParts().');
        }

        return $parts;
    }

    /** @return array<string, mixed> */
    protected function ermsPayableContextDefaults(): array
    {
        $v = $this->getAttribute('erms_payable_context_defaults');

        return is_array($v) ? $v : [];
    }

    /** @return array<string, mixed> */
    public function toPayableRequestData(): array
    {
        $parts = $this->normalizePayableParts($this->ermsPayablePayloadParts());
        $cfg = $this->payableSettings();
        $total = $parts['totalAmount'];
        $gfs = (string) $cfg['request_item_gfs_code'];

        $desc = (string) ($parts['description'] ?? '');
        if ($desc === '') {
            $desc = (string) (($cfg['description'] ?? '') !== '' ? $cfg['description'] : 'Payable payment request');
        }

        $rate = (float) $cfg['current_currency_rate'];

        $data = [
            'currencyCode' => (string) $cfg['currency_code'],
            'currentCurrencyRate' => $rate,
            'exchangeRate' => $rate,
            'description' => $desc,
            'departmentCode' => $this->defaultConfig('department_code'),
            'branchCode' => $this->defaultConfig('branch_code'),
            'businessLineCode' => $this->defaultConfig('business_line_code'),
            'payeeList' => $parts['payeeList'],
            'requestedDate' => $parts['requestedDate'],
            'requestItems' => [['amount' => $total, 'gfsCode' => $gfs]],
            'externalResources' => $parts['externalResources'],
            'subActivityCode' => (string) $cfg['sub_activity_code'],
            'entries' => $parts['entries'],
            'paymentType' => (string) $cfg['payment_type'],
        ];

        $root = $this->getAttribute('erms_payable_root_extensions');

        return array_merge($data, is_array($root) ? $root : []);
    }

    /** @return array{data: array<string, mixed>, signature: string} */
    public function toErmsPayload(bool $asBatchItem = false): array
    {
        unset($asBatchItem);

        return $this->signPayloadForErms($this->toPayableRequestData());
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
            'erms.urls.create_payable_payment_request',
            Config::get('services.erms.create_payable_payment_request_url', Config::get('services.erms.create_payable_payment_request_path', ''))
        );
    }

    public function validateErmsPayload(): void
    {
        $parts = $this->normalizePayableParts($this->ermsPayablePayloadParts());

        if ($parts['totalAmount'] <= 0) {
            throw new InvalidArgumentException('ERMS payable requires positive totalAmount.');
        }
        if ($parts['payeeList'] === []) {
            throw new InvalidArgumentException('ERMS payable requires a non-empty payeeList.');
        }
        if ($parts['entries'] === []) {
            throw new InvalidArgumentException('ERMS payable requires a non-empty entries list.');
        }

        $cfg = $this->payableSettings();
        foreach (['department_code', 'sub_activity_code', 'payment_type', 'request_item_gfs_code'] as $k) {
            if ((string) ($cfg[$k] ?? '') === '') {
                throw new InvalidArgumentException("Missing erms.payable_settings.{$k}.");
            }
        }

        $this->validateErmsPayablePayloadParts($parts, $cfg);
    }

    protected function validateErmsPayablePayloadParts(array $parts, array $cfg): void
    {
        unset($parts, $cfg);
    }

    /** @param array<string, mixed> $parts */
    protected function normalizePayableParts(array $parts): array
    {
        foreach (['totalAmount', 'requestedDate', 'payeeList', 'entries'] as $k) {
            if (! array_key_exists($k, $parts)) {
                throw new InvalidArgumentException("ERMS payable parts missing \"{$k}\".");
            }
        }

        $ext = $parts['externalResources'] ?? [];
        if (! is_array($ext)) {
            throw new InvalidArgumentException('externalResources must be an array.');
        }

        $n = [
            'totalAmount' => round((float) $parts['totalAmount'], 2),
            'requestedDate' => (string) $parts['requestedDate'],
            'payeeList' => array_values($parts['payeeList']),
            'entries' => array_values($parts['entries']),
            'externalResources' => array_values($ext),
        ];

        if (array_key_exists('description', $parts)) {
            $n['description'] = (string) $parts['description'];
        }

        return $n;
    }

    protected function normalizePhone(string $phone, string $prefix): string
    {
        $cell = preg_replace('/\s+/', '', $phone) ?? '';
        if ($cell === '') {
            return $prefix . '000000000';
        }
        if (str_starts_with($cell, '+')) {
            return ltrim($cell, '+');
        }
        if (str_starts_with($cell, '0')) {
            return $prefix . substr($cell, 1);
        }
        if (str_starts_with($cell, $prefix)) {
            return $cell;
        }

        return $prefix . $cell;
    }

    /** @return array<string, mixed> */
    protected function payableSettings(): array
    {
        $c = config('erms.payable_settings', Config::get('services.erms.payable_settings', []));
        $c = is_array($c) ? $c : [];
        $ctx = $this->ermsPayableContextDefaults();
        $ctx = is_array($ctx) ? $ctx : [];
        $ex = (float) ($c['exchange_rate'] ?? $this->defaultConfig('exchange_rate', 1));

        return array_merge([
            'currency_code' => (string) ($c['currency_code'] ?? $this->defaultConfig('currency_code', 'TZS')),
            'current_currency_rate' => $ex,
            'description' => (string) ($c['description'] ?? ''),
            'department_code' => (string) ($c['department_code'] ?? ''),
            'sub_activity_code' => (string) ($c['sub_activity_code'] ?? ''),
            'payment_type' => (string) ($c['payment_type'] ?? ''),
            'request_item_gfs_code' => (string) ($c['request_item_gfs_code'] ?? ''),
            'distribution_gfs_code' => (string) ($c['distribution_gfs_code'] ?? ''),
            'debit_account_code' => (string) ($c['debit_payable_account_code'] ?? $c['debit_account_code'] ?? ''),
            'credit_account_code' => (string) ($c['credit_payable_account_code'] ?? $c['credit_account_code'] ?? ''),
            'debit_gfs_code' => (string) ($c['debit_payable_gfs_code'] ?? $c['debit_gfs_code'] ?? ''),
            'credit_gfs_code' => (string) ($c['credit_payable_gfs_code'] ?? $c['credit_gfs_code'] ?? ''),
            'client_type' => (string) ($c['client_type'] ?? ''),
            'client_category' => (string) ($c['client_category'] ?? ''),
            'loyalty_type' => (string) ($c['loyalty_type'] ?? ''),
            'group_code' => (string) ($c['group_code'] ?? ''),
            'retirable' => (bool) ($c['retirable'] ?? false),
            'default_bank_code' => (string) ($c['default_bank_code'] ?? ''),
            'default_branch_code' => (string) ($c['default_branch_code'] ?? ''),
            'default_bank_name' => (string) ($c['default_bank_name'] ?? ''),
            'default_email' => (string) ($c['default_email'] ?? ''),
            'default_client_address' => (string) ($c['default_client_address'] ?? ''),
            'phone_country_prefix' => (string) ($c['phone_country_prefix'] ?? $this->defaultConfig('phone_country_prefix', '255')),
        ], $ctx);
    }

    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.'.$key, Config::get('services.erms.default_config.'.$key, $default));
    }
}
