<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TollTransaction extends Model
{
    use HasFactory;
    protected $table = 'toll_transaction';
    public $timestamps = false;
    
    protected $fillable = [
        'shift_id',
        'account_no',
        'vehicle_id',
        'body_type_id',
        'plate_no',
        'receipt_num',
        'charged_amount',
        'erms_status',
        'lane_id',
        'rctnum',
        'trans_type',
        'exemption',
        'status',
        'reason',
        'created_by',
        'updated_by',
        'created_at'
    ];

    public static function recordTollTransaction(bool $exempted, string $trans_type, $data, null | string $vehicle_id, null | string $account_no): string | array | null
    {
        $receipt = TollTransaction::generateReceiptNo();
        $model = new TollTransaction();

        $model->receipt_num = $exempted ? 'EXEMPTED' : $receipt; // todo: create receipt number
        $model->vehicle_id = $vehicle_id;
        $model->account_no = $account_no;
        $model->lane_id = $data->lane_id;
        $model->shift_id = $data->shift_id;
        $model->charged_amount = $exempted ? 0 : $data->amount;
        $model->plate_no = $data->plate_no;
        $model->exemption = $exempted ? 1 : 0;
        $model->trans_type = $trans_type;
        $model->reason = data_get($data, 'reason') ?? data_get($data, 'exempt_reason');
        $model->created_at = now()->toDateTimeString();
        $model->created_by = $data->user_id;
        $model->body_type_id = $data->body_type_id;
        $success = $model->save();

        if ($success){
            $trans = null;
            if ($trans_type == 'CASH') {
                $trans = Transaction::recordTransaction($receipt, $data, $vehicle_id);
            }

            return [
                'toll'=>$model,
                'trans'=>$trans
            ];

        }else{
            return null;
        }
    }

    static public function generateReceiptNo(): string
    {
        $receipt = Receipt::create(['prefix' => 'B']);
        $receiptNumber = 'B' . strval($receipt->number);
        
        // Update the receipt_num field with the complete receipt number
        $receipt->receipt_num = $receiptNumber;
        $receipt->save();
        
        return $receiptNumber;
    }
    
    /**
     * Get the account balance history records linked to this toll transaction
     */
    public function accountBalanceHistories()
    {
        return $this->hasMany(AccountBalanceHistory::class, 'transaction_id', 'id');
    }
}
