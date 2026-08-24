<?php

namespace App\Http\Controllers\DataController;

use App\Http\Controllers\Configurations\ConfigurationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataController extends ConfigurationController
{
   public function getOperatorCollections(Request $request): array
   {
       $opening_time = $request->opening_time;
       $current_time = date('Y-m-d H:i:s');
       $user_id = $request->user_id;

       $result = DB::table('toll_transaction')
           ->selectRaw('SUM(charged_amount) as TotalCash, COUNT(id) as Passage')
           ->where('created_by', $user_id)
           ->where('trans_type', 'CASH')
           ->whereBetween('created_at', [$opening_time, $current_time])
           ->first();


       return array('cash' => (int)$result->TotalCash==null?0:(int)$result->TotalCash, 'vehicle_passed' => (int)$result->Passage, 'status' => 1);

   }
}
