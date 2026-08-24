<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\BridgeBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillsController extends ConfigurationController
{
    /**
     * @throws ValidationException
     */
    public function initiateSelfBilling(Request $request)
    {
        $rules = [
            'bundle_id' => 'required|string|exists:toll_bundles',
            'plate_no' => 'required|string|exists:vehicle,plate_no',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        $validatedData = $validator->validated();

        $unpaidBill = DB::table('bridge_bills')
            ->where('dist_param', $validatedData['plate_no'])
            ->where('bill_status', BridgeBill::PAID)
            ->first();

        if (is_null($unpaidBill)) {

        }



    }


}
