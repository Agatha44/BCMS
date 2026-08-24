<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\BasicController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShiftRecordController extends BasicController
{
    /**
     * List counter sessions for an operator (legacy shift-record/query-shift).
     */
    public function queryShift(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', ['errors' => $validator->errors()]);
        }

        $userId = (int) $request->user_id;

        $rows = DB::table('counter as c')
            ->leftJoin('lane as l', 'l.id', '=', 'c.lane_id')
            ->leftJoin('shift as s', 's.id', '=', 'c.shift_id')
            ->where('c.user_id', $userId)
            ->where('c.payment_method', 2)
            ->select([
                'c.id',
                'c.close_counter',
                'c.user_id',
                'c.open_counter',
                'c.shift_id',
                'c.lane_id',
                'l.lane_no',
                's.name',
                's.description',
            ])
            ->orderByDesc('c.id')
            ->get();

        return $this->sendResponse($rows, 'Shift records retrieved successfully');
    }

    /**
     * Get a single counter session by id (legacy shift-record/get-shift).
     */
    public function getShift(int $id): JsonResponse
    {
        $row = DB::table('counter as c')
            ->leftJoin('auth_user as au', 'au.id', '=', 'c.user_id')
            ->leftJoin('lane as l', 'l.id', '=', 'c.lane_id')
            ->leftJoin('shift as s', 's.id', '=', 'c.shift_id')
            ->where('c.id', $id)
            ->select([
                'au.id as user_id',
                'c.close_counter',
                'au.first_name',
                'au.middle_name',
                'au.surname',
                'c.open_counter',
                'c.id',
                'c.created_at',
                'c.shift_id',
                'c.lane_id',
                'l.lane_no',
                's.name as shift_name',
            ])
            ->first();

        if (!$row) {
            return $this->sendError('Shift record not found', [], 0, 404);
        }

        return $this->sendResponse($row, 'Shift record retrieved successfully');
    }
}
