<?php

namespace App\Services\Erms\Mappers;

use App\Models\IncidentFine;
use Illuminate\Support\Facades\DB;

class IncidentFineReceiptMapper
{
    /**
     * Canonical mapper output consumed by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(IncidentFine $fine): array
    {
        $natureName = $this->resolveIncidentNatureName($fine);
        $billDesc = $natureName !== ''
            ? trim($natureName . ' - ' . (string) ($fine->plate_number ?? ''))
            : (string) ($fine->nature_incident ?? 'Incident fine payment');

        return [
            'bill_id' => (string) $fine->id,
            'bill_desc' => $billDesc,
            'paid_amount' => (float) ($fine->paid_amt),
            'control_number' => (string) ($fine->control_num),
            // receiptDate comes from transaction_datetime in ErmsReceiptPayloadTrait
            'transaction_datetime' => $fine->trx_dt_tm,
            'credited_acc_num' => (string) ($fine->ctr_acc_num),
            'pay_ref_id' => (string) ($fine->pay_ref_id),
            'trx_id' => (string) ($fine->trx_id),
            'receipt_number' => (string) ($fine->receipt_number),
            'psp_receipt_num' => (string) ($fine->psp_receipt_num),
            'payer_name' => $this->resolvePayerName($fine),
            'payer_cell_number' => (string) ($fine->pyr_cell_num ?? $fine->phone_number),
            'payer_email' => (string) ($fine->email),
            'created_by' => (string) ($fine->created_by),
            'bill_gen_by' => (string) ($fine->created_by),
            'currency' => 'TZS',
            'reversed_at' => null,
            'source_type' => 'incident_fine',
        ];
    }

    protected function resolvePayerName(IncidentFine $fine): string
    {
        $preferred = trim((string) ($fine->pyr_name));
        if ($preferred !== '') {
            return $preferred;
        }

        $fallbacks = [
            trim((string) ($fine->payer_name)),
            trim((string) ($fine->driver_name)),
            trim((string) ($fine->owner_name)),
            trim((string) ($fine->vehicle_owner)),
        ];

        foreach ($fallbacks as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return 'Payer';
    }

    protected function resolveIncidentNatureName(IncidentFine $fine): string
    {
        $raw = $fine->nature_incident;
        if ($raw === '') {
            return '';
        }

        // Some flows store nature_incident as descriptive text already.
        if (! is_numeric($raw)) {
            return trim((string) $raw);
        }

        $id = (int) $raw;
        if ($id <= 0) {
            return '';
        }

        $name = DB::table('incident_nature')->where('id', $id)->value('name');

        return $name ? trim($name): '';
    }
}
