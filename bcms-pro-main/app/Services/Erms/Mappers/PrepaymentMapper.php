<?php

namespace App\Services\Erms\Mappers;

/**
 * Maps a top_up row (e.g. stdClass from the query builder) for ERMS receipt payloads.
 */
class PrepaymentMapper
{
    /**
     * Canonical mapper output consumed by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(object $topUp): array
    {
        return [
            'bill_id' => (string) $topUp->id,
            'bill_desc' => (string) ($topUp->bill_desc),
            'client_code' => (string) ($topUp->account_no),
            'paid_amount' => (float) ($topUp->paid_amt),
            'control_number' => (string) ($topUp->contr_num),
            'transaction_datetime' => $topUp->trx_dt_tm,
            'credited_acc_num' => (string) ($topUp->ctr_acc_num),
            'pay_ref_id' => (string) ($topUp->pay_ref_id),
            'psp_receipt_num' => (string) ($topUp->psp_receipt_num),
            'trx_id' => (string) ($topUp->trx_id),
            'receipt_number' => (string) ($topUp->receipt_number),
            'payer_name' => $this->resolvePayerName($topUp),
            'payer_cell_number' => (string) ($topUp->pyr_cell_num),
            'payer_email' => '',
            'created_by' => (string) ($topUp->bill_gen_by),
            'bill_gen_by' => (string) ($topUp->bill_gen_by),
            'currency' => 'TZS',
            'reversed_at' => null,
            'source_type' => 'Prepayment',
        ];
    }

    protected function resolvePayerName(object $topUp): string
    {
        $name = trim((string) ($topUp->payer_name));

        return $name !== '' ? $name : 'Payer';
    }
}
