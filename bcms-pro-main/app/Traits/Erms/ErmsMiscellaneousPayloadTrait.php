<?php

namespace App\Traits\Erms;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * ERMS Miscellaneous Entries API payload.
 * POST body: { "data": { camelCase ... }, "signature": "<base64>" }.
 *
 * All business fields must be supplied by a mapper via setRawAttributes() (snake_case).
 * Mappers read erms.* config; this trait only maps attributes → ERMS JSON shape.
 *
 * Root attributes: description, record_date, amount, source_ref, tracking_reference,
 * branch_code, department_code, entry_purpose, business_line_code, sub_activity_code,
 * currency_code, exchange_rate, phone_country_prefix (optional), client_details, entries,
 * include_entry_client_details (optional bool, default true), external_resources.
 *
 * Entry lines: account_code, gfs_code, book_side, description, amount,
 * entry_client_details (optional when include_entry_client_details is false).
 */
trait ErmsMiscellaneousPayloadTrait
{
    use ErmsFormatDateTrait;
    use ErmsSignPayloadTrait;

    /**
     * @return array{data: array<string, mixed>, signature: string}
     */
    public function toErmsMiscellaneousPayload(): array
    {
        return $this->signPayloadForErms($this->toMiscellaneousEntryData());
    }

    /**
     * @return array<string, mixed>
     */
    public function toMiscellaneousEntryData(): array
    {
        $external = $this->miscDecodeListAttribute('external_resources');
        $entries = $this->miscDecodeListAttribute('entries');
        $clientRaw = $this->miscDecodeMapAttribute('client_details');

        return [
            'description' => (string) $this->miscAttribute('description'),
            'entryPurpose' => (string) $this->miscAttribute('entry_purpose'),
            'recordDate' => $this->parseMiscRecordDateIso(),
            'businessLineCode' => (string) $this->miscAttribute('business_line_code'),
            'subActivityCode' => (string) $this->miscAttribute('sub_activity_code'),
            'departmentCode' => (string) $this->miscAttribute('department_code'),
            'branchCode' => (string) $this->miscAttribute('branch_code'),
            'amount' => round((float) $this->getAttribute('amount'), 2),
            'sourceRef' => (string) $this->miscAttribute('source_ref'),
            'trackingReference' => (string) $this->miscAttribute('tracking_reference'),
            'currencyCode' => (string) $this->miscAttribute('currency_code'),
            'exchangeRate' => (float) $this->getAttribute('exchange_rate'),
            'externalResources' => array_values(array_filter(array_map(
                fn (mixed $row) => $this->mapMiscExternalResource($row),
                $external
            ))),
            'clientDetails' => $this->mapMiscClientDetails($clientRaw),
            'entries' => array_values(array_map(
                fn (mixed $row) => $this->mapMiscEntry($row),
                $entries
            )),
        ];
    }

    public function toErmsMiscellaneousHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getErmsMiscellaneousEndpoint(): string
    {
        return (string) config(
            'erms.urls.miscellaneous_entries',
            Config::get('services.erms.miscellaneous_entries_url', '')
        );
    }

    public function validateErmsMiscellaneousPayload(): void
    {
        $data = $this->toMiscellaneousEntryData();

        if ($data['description'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload missing description.');
        }

        if ($data['entryPurpose'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload missing entry_purpose.');
        }

        if ($data['recordDate'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload recordDate invalid: set record_date.');
        }

        if ($data['businessLineCode'] === '' || $data['subActivityCode'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload needs business_line_code and sub_activity_code.');
        }

        if ($data['departmentCode'] === '' || $data['branchCode'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload needs branch_code and department_code.');
        }

        if ($data['sourceRef'] === '' || $data['trackingReference'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload needs sourceRef and trackingReference.');
        }

        if ($data['currencyCode'] === '') {
            throw new InvalidArgumentException('ERMS miscellaneous payload missing currency_code.');
        }

        if ($data['amount'] <= 0) {
            throw new InvalidArgumentException('ERMS miscellaneous payload amount must be positive.');
        }

        $client = $data['clientDetails'];
        if ($client === []) {
            throw new InvalidArgumentException('ERMS miscellaneous payload clientDetails is required.');
        }

        $name = (string) ($client['name'] ?? '');
        $code = (string) ($client['code'] ?? '');
        if ($name === '' && $code === '') {
            throw new InvalidArgumentException('ERMS miscellaneous clientDetails needs at least name or code.');
        }

        $entries = $data['entries'];
        if ($entries === []) {
            throw new InvalidArgumentException('ERMS miscellaneous payload entries cannot be empty.');
        }

        $requireEntryClient = $this->requiresMiscEntryClientDetails();

        foreach ($entries as $i => $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] must be an array.");
            }
            foreach (['accountCode', 'description', 'bookSide', 'gfsCode'] as $key) {
                if (! array_key_exists($key, $entry) || $entry[$key] === '' || $entry[$key] === null) {
                    throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] missing or empty: {$key}.");
                }
            }
            if (! array_key_exists('amount', $entry) || (float) $entry['amount'] <= 0) {
                throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] amount must be positive.");
            }
            $bs = strtoupper((string) $entry['bookSide']);
            if ($bs !== 'DEBIT' && $bs !== 'CREDIT') {
                throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] bookSide must be DEBIT or CREDIT.");
            }

            if ($requireEntryClient) {
                $ec = $entry['entryClientDetails'] ?? null;
                if (! is_array($ec) || $ec === []) {
                    throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] entryClientDetails is required.");
                }
                $ecn = (string) ($ec['name'] ?? '');
                $ecc = (string) ($ec['code'] ?? '');
                if ($ecn === '' && $ecc === '') {
                    throw new InvalidArgumentException("ERMS miscellaneous entries[{$i}] entryClientDetails needs name or code.");
                }
            }
        }

        if ($this->getErmsMiscellaneousEndpoint() === '') {
            throw new InvalidArgumentException('ERMS miscellaneous endpoint is empty. Configure erms.urls.miscellaneous_entries.');
        }
    }

    protected function miscAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return $value === null ? '' : trim((string) $value);
    }

    protected function requiresMiscEntryClientDetails(): bool
    {
        $flag = $this->getAttribute('include_entry_client_details');

        if ($flag === null) {
            return true;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    protected function parseMiscRecordDateIso(): string
    {
        return $this->formatReceivableDateIso($this->getAttribute('record_date'));
    }

    /**
     * @return list<mixed>
     */
    protected function miscDecodeListAttribute(string $key): array
    {
        $v = $this->getAttribute($key);
        if ($v === null || $v === '') {
            return [];
        }

        if (is_string($v)) {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }

            return [];
        }

        if (is_array($v)) {
            return array_values($v);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function miscDecodeMapAttribute(string $key): array
    {
        $v = $this->getAttribute($key);
        if ($v === null || $v === '') {
            return [];
        }

        if (is_string($v)) {
            $decoded = json_decode($v, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($v) ? $v : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMiscExternalResource(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        return [
            'fullUrl' => (string) ($row['fullUrl'] ?? $row['full_url'] ?? ''),
            'resourceType' => (string) ($row['resourceType'] ?? $row['resource_type'] ?? ''),
            'requiresAuthentication' => (bool) ($row['requiresAuthentication'] ?? $row['requires_authentication'] ?? false),
            'title' => (string) ($row['title'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapMiscClientDetails(array $row): array
    {
        if ($row === []) {
            return [];
        }

        $email = $row['email'] ?? null;
        $email = ($email !== null && $email !== '') ? (string) $email : null;

        $phoneRaw = (string) ($row['phone'] ?? $row['payer_cell_number'] ?? '');
        $phone = $phoneRaw !== '' ? $this->normalizeMiscellaneousClientPhone($phoneRaw) : null;

        return [
            'clientType' => (string) ($row['client_type'] ?? $row['clientType'] ?? ''),
            'loyaltyType' => (string) ($row['loyalty_type'] ?? $row['loyaltyType'] ?? ''),
            'clientCategory' => (string) ($row['client_category'] ?? $row['clientCategory'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'email' => $email,
            'phone' => $phone,
            'code' => (string) ($row['code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMiscEntry(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        $entry = [
            'accountCode' => (string) ($row['account_code'] ?? $row['accountCode'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'bookSide' => strtoupper((string) ($row['book_side'] ?? $row['bookSide'] ?? '')),
            'gfsCode' => (string) ($row['gfs_code'] ?? $row['gfsCode'] ?? ''),
        ];

        $ecRaw = $row['entryClientDetails'] ?? $row['entry_client_details'] ?? [];
        $ecRaw = is_array($ecRaw) ? $ecRaw : [];
        if ($ecRaw !== []) {
            $mapped = $this->mapMiscEntryClientDetails($ecRaw);
            if ($mapped !== []) {
                $entry['entryClientDetails'] = $mapped;
            }
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapMiscEntryClientDetails(array $row): array
    {
        if ($row === []) {
            return [];
        }

        return [
            'clientType' => (string) ($row['client_type'] ?? $row['clientType'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
        ];
    }

    protected function normalizeMiscellaneousClientPhone(string $cell): string
    {
        $cell = preg_replace('/\s+/', '', $cell) ?? '';
        if ($cell === '' || $cell === '0') {
            return '0';
        }

        $prefix = $this->miscAttribute('phone_country_prefix');
        if ($prefix === '') {
            $prefix = '255';
        }

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
}
