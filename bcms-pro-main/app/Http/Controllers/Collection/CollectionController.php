<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function getTollTransactions(Request $request): JsonResponse
    {
        $perPage = 15;
        $search = trim((string) $request->get('search', ''));

        $query = DB::table('toll_transaction');

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($q) use ($like, $search) {
                $columns = [
                    'receipt_num',
                    'plate_no',
                    'account_no',
                    'trans_type',
                    'lane_id',
                    'charged_amount',
                    'status',
                    'created_by',
                    'created_at',
                ];

                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $like);
                }

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $tollTransactions = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Toll transactions retrieved successfully',
            'data' => $tollTransactions->items(),
            'pagination' => [
                'current_page' => $tollTransactions->currentPage(),
                'last_page' => $tollTransactions->lastPage(),
                'per_page' => $tollTransactions->perPage(),
                'total' => $tollTransactions->total(),
                'from' => $tollTransactions->firstItem(),
                'to' => $tollTransactions->lastItem(),
            ],
        ]);

    }
}

