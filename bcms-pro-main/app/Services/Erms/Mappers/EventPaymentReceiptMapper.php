<?php

namespace App\Services\Erms\Mappers;

use App\Models\EventPayment;

class EventPaymentReceiptMapper
{
    /**
     * Canonical mapper output consumed by ErmsReceiptPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(EventPayment $payment): array
    {
        return [
            'bill_id' => (string) $payment->id,
            'bill_desc' => (string) ('Event payment for payment reference: ' . $payment->pay_ref_id),
            'paid_amount' => (float) ($payment->paid_amt),
            'control_number' => (string) ($payment->control_num),
            'transaction_datetime' => $payment->trx_dt_tm,
            'credited_acc_num' => (string) ($payment->ctr_acc_num),
            'pay_ref_id' => (string) ($payment->pay_ref_id),
            'trx_id' => (string) ($payment->trx_id),
            'psp_receipt_num' => (string) ($payment->psp_receipt_num),
            'receipt_number' => (string) ($payment->receipt_number),
            'payer_name' => $this->resolvePayerName($payment),
            'payer_cell_number' => (string) ($payment->pyr_cell_num ?? $payment->phone_number),
            'payer_email' => (string) ($payment->email),
            'created_by' => (string) ($payment->created_by),
            'bill_gen_by' => (string) ($payment->created_by),
            'currency' => 'TZS',
            'reversed_at' => null,
            'source_type' => 'event_payment',
        ];
    }

    protected function resolvePayerName(EventPayment $payment): string
    {
        $candidates = [
            trim((string) ($payment->pyr_name)),
            trim((string) ($payment->payer_name)),
            trim((string) ($payment->receiver_name)),
        ];

        foreach ($candidates as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return 'Payer';
    }
}
