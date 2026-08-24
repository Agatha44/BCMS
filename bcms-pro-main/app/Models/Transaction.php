<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = 'transactions';
    public $timestamps = false;

    public static function recordTransaction(string $receipt_num, $data, null | string $vehicle_id): bool
    {
        $model = new Transaction();
        $model->receipt_num = $receipt_num;
        $model->vehicle_id = $vehicle_id;
        $model->payment_type = 'CASH';
        $model->lane_id = $data->lane_id;
        $model->shift_id = $data->shift_id;
        $model->charged_amount =  $data->amount;
        $model->plate_no = $data->plate_no;
        $model->created_at = now()->toDateTimeString();
        $model->created_by = $data->user_id;
        $model->body_type_id = $data->body_type_id;
        $success = $model->save();
        if ($success){
            return true;
        }else{
            return false;
        }
    }

}
