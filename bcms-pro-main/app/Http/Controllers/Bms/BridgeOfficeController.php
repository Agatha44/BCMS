<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\BridgeOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BridgeOfficeController extends BasicController
{
    /**
     * Display a listing of bridge offices.
     *
     * Query Parameters:
     * - search: Search by office name or office code (optional)
     * - is_active: Filter by active status (true/false, optional)
     * - auto_generate_pf: Filter by auto_generate_pf flag (true/false, optional)
     * - sort_by: Column to sort by (default: 'office_name')
     * - sort_order: Sort order 'asc' or 'desc' (default: 'asc')
     * - per_page: Number of records per page (default: 15)
     * - page: Page number (default: 1)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'office_name');
            $sortOrder = $request->input('sort_order', 'asc');
            $isActive = $request->input('is_active');
            $autoGeneratePf = $request->input('auto_generate_pf');

            $query = BridgeOffice::query();

            // Apply search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('office_name', 'like', "%{$search}%")
                      ->orWhere('office_code', 'like', "%{$search}%");
                });
            }

            // Apply active filter
            if ($isActive !== null) {
                $isActiveBool = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_active', $isActiveBool);
            } else {
                // Default to active only if not specified
                $query->where('is_active', true);
            }

            // Apply auto_generate_pf filter
            if ($autoGeneratePf !== null) {
                $autoGenerateBool = filter_var($autoGeneratePf, FILTER_VALIDATE_BOOLEAN);
                $query->where('auto_generate_pf', $autoGenerateBool);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->input('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $offices = $query->offset($offset)->limit($perPage)->get();

            return $this->sendResponse([
                'offices' => $offices,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 'Bridge offices retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge offices: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve bridge offices: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get a specific office by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $office = BridgeOffice::find($id);

            if (!$office) {
                return $this->sendError('Office not found', [], 0, 404);
            }

            return $this->sendResponse($office, 'Office retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching office: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve office: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

