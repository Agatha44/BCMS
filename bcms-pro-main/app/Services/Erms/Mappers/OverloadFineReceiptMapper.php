<?php

namespace App\Services\Erms\Mappers;

use App\Models\OverloadFine;

class OverloadFineReceiptMapper
{
    /**
     * Canonical mapper output consumed by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(OverloadFine $fine): array
    {
        return $this->mapReceiptAttributes($fine);
    }

    /**
     * Attributes read directly from the "receipt model" by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function mapReceiptAttributes(OverloadFine $fine): array
    {
        $payerName = $this->resolvePayerName($fine);

        return [
            'bill_id' => (string) $fine->id,
            'bill_desc' => (string) ($fine->bill_desc),
            'paid_amount' => (float) ($fine->paid_amt),
            'control_number' => (string) ($fine->contr_num),
            'transaction_datetime' => $fine->trx_dt_tm,
            'credited_acc_num' => (string) ($fine->ctr_acc_num),
            'pay_ref_id' => (string) ($fine->pay_ref_id),
            'trx_id' => (string) ($fine->trx_id),
            'receipt_number' => (string) ($fine->receipt_number),
            'psp_receipt_num' => (string) ($fine->psp_receipt_num),
            'payer_name' => $payerName,
            'payer_cell_number' => (string) ($fine->pyr_cell_num),
            'payer_email' => (string) ($fine->pyr_email),
            'created_by' => (string) ($fine->bill_gen_by),
            'bill_gen_by' => (string) ($fine->bill_gen_by),
            'currency' => 'TZS',
            'reversed_at' => null,
            'source_type' => 'overload_fine',
        ];
    }

    protected function resolvePayerName(OverloadFine $fine): string
    {
        $preferred = trim((string) ($fine->pyr_name));
        if ($preferred !== '') {
            return $preferred;
        }

        $parts = [
            trim((string) ($fine->first_name.' '.$fine->middle_name.' '.$fine->surname)),
        ];

        $fullName = $parts[0];
        if ($fullName !== '') {
            return $fullName;
        }

        return 'Payer';
    }
}
