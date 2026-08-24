<?php

namespace App\Services\Erms\Mappers;

use App\Models\BridgeBill;

class BridgeBillReceiptMapper
{
    /**
     * Canonical mapper output consumed by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(BridgeBill $bill): array
    {
        return [
            'bill_id' => (string) $bill->id,
            'bill_desc' => (string) ($bill->bill_desc),
            'paid_amount' => (float) ($bill->paid_amt),
            'control_number' => (string) ($bill->contr_num),
            'transaction_datetime' => $bill->trx_dt_tm,
            'credited_acc_num' => (string) ($bill->ctr_acc_num),
            'pay_ref_id' => (string) ($bill->pay_ref_id),
            'trx_id' => (string) ($bill->trx_id),
            'psp_receipt_num' => (string) ($bill->psp_receipt_num),
            'receipt_number' => (string) ($bill->receipt_number),
            'payer_name' => $this->resolvePayerName($bill),
            'payer_cell_number' => (string) ($bill->pyr_cell_num ?? $bill->phone_number),
            'payer_email' => (string) ($bill->payer_email),
            'created_by' => (string) ($bill->bill_gen_by),
            'bill_gen_by' => (string) ($bill->bill_gen_by),
            'source' => (string) ($bill->source),
            'currency' => 'TZS',
            'reversed_at' => null,
            'source_type' => 'bridge_bill',
        ];
    }

    protected function resolvePayerName(BridgeBill $bill): string
    {
        $candidates = [
            trim((string) ($bill->payer_name)),
            trim((string) ($bill->pyr_name)),
        ];

        foreach ($candidates as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return 'Payer';
    }
}
