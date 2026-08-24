<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Configurations\ConfigurationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use App\Models\Account;

class PublicServicesController extends ConfigurationController
{

    public function searchVehicle(Request $request): JsonResponse
    {
        // validatore

        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $plate_no = $request->plate_no;

        $result = Vehicle::where('plate_no', $plate_no)
            ->select('id', 'plate_no', 'body_type_id', 'account_no')
            ->with(['bodyType'])
            ->first();

        if ($result == null) {
            return $this->sendError('Vehicle not found');
        }

        // return base64 image of the vehicle if account_no is null
//        if (is_null($result->account_no)) {
            $base64 = Vehicle::getImageBase64($result->id);
            $result->image = $base64;
//        }

        return $this->sendResponse($result, 'Vehicle data retrieved successfully');
    }

    public function searchAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $validated = $validator->validated();

        $result = Account::where('phone', $validated['phone'])
            ->select('phone', 'email', 'account_no', 'first_name', 'middle_name', 'surname')
            ->first();

        if ($result == null) {
            return $this->sendError('Account not found');
        }

        return $this->sendResponse($result, 'Account data retrieved successfully');
    }

}
