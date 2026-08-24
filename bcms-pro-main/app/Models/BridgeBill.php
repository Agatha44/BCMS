<?php

namespace App\Models;

use App\Http\Controllers\Billing\BillingController;
use App\Traits\Erms\ErmsReceiptPayloadTrait;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\GePG;
use Illuminate\Support\Facades\Log;

class BridgeBill extends Model
{
    use ErmsReceiptPayloadTrait;

    public $timestamps = false;  // Disable timestamps

    const PENDING = '0';

    const REQUESTED = '1';
    const PAID = '2';
    const CANCELLED = '2';


    const BILL_EXPIRE_DAYS = 1;

    protected $fillable = [
        // during createing bill below fields are required
        "bill_desc",
        "bill_amount",
        "bill_exp_dt",
        "contr_num",
        "bill_gen_by",
        "bill_gen_at",

        "bill_source",
        "dist_param", // plate number
        "bundle_id",
        "source", // todo: clarification
        "phone_number",

        // on every update
        "updated_at",

        // on cancellation below fields are to be updated
        "cancel_reason",
        "is_cancelled",
        "bill_cancel_by",
        "bill_cancel_date",
        // also on cancellation update the psp_receipt_num to 'CANC{{timestamp}}'

        // on payment below fields are to be updated
        "trx_id",
        "trx_dt_tm",
        "usd_pay_chn", // todo: clarification
        "pyr_cell_num",
        "psp_receipt_num",
        "psp_name",
        "ctr_acc_num",
        "error_code",
        "t_status", // todo: clarification
        "bill_status",
        "receipt_number",
        "pay_ref_id",
        "receipt_type",
        "collection_office",
        "erp_status", // todo: clarification - 7101
        "receipt_date",
        "payment_date",
        "paid_amt",
        "payer_name",
        "updated_by",
        "shift_date",
        "shift_id",
        "http_status",
    ];


     public static function requestBundleSubscriptionControlNo(BundleSubscription $bundleSubscription)
     {

        $price = PriceList::query()
            ->where('body_type_id', $bundleSubscription->vehicle->body_type_id)
            ->where('status', PriceList::STATUS_ACTIVE)
            ->first();

        if (is_null($price)) {
            return [
                'success' => false,
                'status_code' => BillingController::STATUS_CODE_PRICE_LIST_NOT_FOUND,
                'message' => 'Price amount not found'
            ];
        }

        $amount = $price->getAmount($bundleSubscription->bundle_id);

        $genDate = now();
        $expDate = $genDate->addDays(self::BILL_EXPIRE_DAYS);

        $bill = BridgeBill::create([
            'bill_desc' => $bundleSubscription->tollBundle->bundle_description,
            'bill_amount' => $amount,
            'bill_gen_at' => $genDate,
            'bill_exp_dt' => $expDate,
            'bill_gen_by' => $bundleSubscription->created_by, // todo: clarification
            'bill_source' => 1,
            'dist_param' => $bundleSubscription->vehicle->plate_number,
            'bundle_id' => $bundleSubscription->bundle_id,
            'source' => 'TBS',
            'phone_number' => (int) preg_replace('/\D/', '', (string) ($bundleSubscription->account->phone ?? '')),
            'bill_status' => BridgeBill::REQUESTED,
        ]);

        Log::info('Bridge bill created', ['bill' => $bill]);

        $params = [
            'payment_ref' => (string)$bill->id,
            'amount' => $amount,
            'equiv_amount' => $amount,
            'bill_desc' => $bill->bill_desc,

            'currency' => 'TZS',
            'payment_type' => 3,
            'payerid' => $bundleSubscription->account->account_no, // account number
            'payer_name' => $bundleSubscription->account->full_name,
            'payer_cell' => '255' . intval($bundleSubscription->account->phone),

            'generated_by' => (string)$bundleSubscription->created_by, // todo: clarification
            'days_expires_after' => 365, // to be discussed
            'payer_email' => $bundleSubscription->account->email,
            'bill_gen_date' => $bill->bill_gen_at,
            'bill_exp_date' => $bill->bill_exp_dt
        ];

        $response = GePG::postBill($params);

        if ($response['status'] == 'invalid_') {
            return [
                'success' => false,
                'status_code' => BillingController::STATUS_CODE_GEPG_INVALID_PARAMS,
                'message' => 'Invalid request params',
                'errors' => $response
            ];
        }

        else if ($response['status'] == 'invalid_request') {
            return [
                'success' => false,
                'status_code' => BillingController::STATUS_CODE_GEPG_INVALID_REQUEST,
                'message' => 'Invalid request data',
                'errors' => $response
            ];
        }

        return [
            'success' => true,
            'message' => 'Bill created successfully',
        ];
    }

    public static function getBill($bill_id)
    {
        $bill = BridgeBill::find($bill_id);
        return $bill;
    }

}
