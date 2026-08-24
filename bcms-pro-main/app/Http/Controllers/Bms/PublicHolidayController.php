<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\PublicHoliday;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PublicHolidayController extends BasicController
{
    /**
     * List all public holidays with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    'ph.modified_at',
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                );

            // Filter by year
            if ($request->has('year')) {
                $year = $request->year;
                $query->whereYear('ph.holiday_date', $year);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('ph.holiday_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('ph.holiday_date', '<=', $request->end_date);
            }

            // Filter by holiday type
            if ($request->has('holiday_type') && $request->holiday_type) {
                $query->where('ph.holiday_type', $request->holiday_type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('ph.is_active', $request->boolean('is_active'));
            } else {
                // Default: show only active holidays
                $query->where('ph.is_active', true);
            }

            // Search by holiday name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('ph.holiday_name', 'like', "%{$search}%")
                      ->orWhere('ph.description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'holiday_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy('ph.' . $sortBy, $sortOrder);

            // Get total count for pagination (clone query to avoid affecting main query)
            $total = (clone $query)->count();
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            
            $holidays = $query->offset($offset)->limit($perPage)->get();

            // Add day of week
            $holidays->transform(function ($holiday) {
                $holiday->day_of_week = Carbon::parse($holiday->holiday_date)->format('l');
                return $holiday;
            });

            $paginatedData = [
                'current_page' => (int) $currentPage,
                'data' => $holidays,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ];

            return $this->sendResponse($paginatedData, 'Public holidays retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public holidays', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve public holidays', [], 0, 500);
        }
    }

    /**
     * Get a specific public holiday by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $holiday = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->where('ph.id', $id)
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    'ph.modified_at'
                )
                ->first();
            
            if (!$holiday) {
                return $this->sendError('Public holiday not found', [], 0, 404);
            }

            $holiday->day_of_week = Carbon::parse($holiday->holiday_date)->format('l');

            return $this->sendResponse($holiday, 'Public holiday retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public holiday', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to retrieve public holiday', [], 0, 500);
        }
    }

    /**
     * Check if a specific date is a holiday
     */
    public function checkDate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $date = $request->date;
            $isHoliday = PublicHoliday::isHoliday($date);
            
            $holiday = null;
            if ($isHoliday) {
                $holiday = DB::table('bcmis2.public_holidays as ph')
                    ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                    ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                    ->where('ph.holiday_date', $date)
                    ->where('ph.is_active', true)
                    ->select(
                        'ph.id',
                        'ph.holiday_name',
                        'ph.holiday_date',
                        'ph.holiday_type',
                        'ph.is_active',
                        'ph.description',
                        'ph.created_at',
                        'ph.modified_at',
                        DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                        DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                    )
                    ->first();
            }

            return $this->sendResponse([
                'date' => $date,
                'is_holiday' => $isHoliday,
                'holiday' => $holiday,
            ], 'Date check completed');
        } catch (\Exception $e) {
            Log::error('Failed to check holiday date', [
                'date' => $request->date ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to check holiday date', [], 0, 500);
        }
    }

    /**
     * Create a new public holiday
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'holiday_name' => 'required|string|max:255',
                'holiday_date' => 'required|date|unique:bcmis2.public_holidays,holiday_date',
                'holiday_type' => 'nullable|string|max:50',
                'is_active' => 'nullable|boolean',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if date already exists
            $existingHoliday = PublicHoliday::where('holiday_date', $request->holiday_date)->first();
            if ($existingHoliday) {
                return $this->sendError('A holiday already exists for this date', [], 0, 400);
            }

            DB::connection('bcmis2')->beginTransaction();

            $holiday = PublicHoliday::create([
                'holiday_name' => $request->holiday_name,
                'holiday_date' => $request->holiday_date,
                'holiday_type' => $request->holiday_type ?? 'National',
                'is_active' => $request->boolean('is_active', true),
                'description' => $request->description,
                'created_by' => (string) auth()->id(),
            ]);

            DB::connection('bcmis2')->commit();

            // Fetch with join to get creator name
            $holidayData = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->where('ph.id', $holiday->id)
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    'ph.modified_at',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                )
                ->first();

            $holidayData->day_of_week = Carbon::parse($holidayData->holiday_date)->format('l');

            return $this->sendResponse($holidayData, 'Public holiday created successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to create public holiday', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to create public holiday: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update a public holiday
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $holiday = PublicHoliday::find($id);
            
            if (!$holiday) {
                return $this->sendError('Public holiday not found', [], 0, 404);
            }

            $validator = Validator::make($request->all(), [
                'holiday_name' => 'sometimes|required|string|max:255',
                'holiday_date' => 'sometimes|required|date|unique:bcmis2.public_holidays,holiday_date,' . $id,
                'holiday_type' => 'nullable|string|max:50',
                'is_active' => 'nullable|boolean',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if new date conflicts with another holiday
            if ($request->has('holiday_date') && $request->holiday_date !== $holiday->holiday_date) {
                $existingHoliday = PublicHoliday::where('holiday_date', $request->holiday_date)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingHoliday) {
                    return $this->sendError('A holiday already exists for this date', [], 0, 400);
                }
            }

            DB::connection('bcmis2')->beginTransaction();

            $updateData = array_filter($request->only([
                'holiday_name',
                'holiday_date',
                'holiday_type',
                'is_active',
                'description',
            ]), function ($value) {
                return $value !== null;
            });

            $updateData['modified_by'] = (string) auth()->id();
            $updateData['modified_at'] = now();

            $holiday->update($updateData);

            DB::connection('bcmis2')->commit();

            // Fetch with join to get creator name
            $holidayData = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->where('ph.id', $id)
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    'ph.modified_at',
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                )
                ->first();

            $holidayData->day_of_week = Carbon::parse($holidayData->holiday_date)->format('l');

            return $this->sendResponse($holidayData, 'Public holiday updated successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to update public holiday', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update public holiday: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle active status of a holiday
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $holiday = PublicHoliday::find($id);
            
            if (!$holiday) {
                return $this->sendError('Public holiday not found', [], 0, 404);
            }

            DB::connection('bcmis2')->beginTransaction();

            $holiday->update([
                'is_active' => !$holiday->is_active,
                'modified_by' => (string) auth()->id(),
                'modified_at' => now(),
            ]);

            DB::connection('bcmis2')->commit();

            // Fetch with join to get creator name
            $holidayData = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->where('ph.id', $id)
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    'ph.modified_at',
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                )
                ->first();

            return $this->sendResponse($holidayData, 'Holiday status updated successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to toggle holiday status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to update holiday status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Delete a public holiday
     */
    public function destroy($id): JsonResponse
    {
        try {
            $holiday = PublicHoliday::find($id);
            
            if (!$holiday) {
                return $this->sendError('Public holiday not found', [], 0, 404);
            }

            DB::connection('bcmis2')->beginTransaction();

            $holiday->delete();

            DB::connection('bcmis2')->commit();

            return $this->sendResponse(null, 'Public holiday deleted successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to delete public holiday', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to delete public holiday: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get holidays for a specific year
     */
    public function getByYear(Request $request, $year = null): JsonResponse
    {
        try {
            $year = $year ?? $request->get('year', date('Y'));

            $holidays = DB::table('bcmis2.public_holidays as ph')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                ->whereYear('ph.holiday_date', $year)
                ->where('ph.is_active', true)
                ->orderBy('ph.holiday_date', 'asc')
                ->select(
                    'ph.id',
                    'ph.holiday_name',
                    'ph.holiday_date',
                    'ph.holiday_type',
                    'ph.is_active',
                    'ph.description',
                    'ph.created_at',
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                    'ph.modified_at',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                )
                ->get();

            $holidays->transform(function ($holiday) {
                $holiday->day_of_week = Carbon::parse($holiday->holiday_date)->format('l');
                return $holiday;
            });

            return $this->sendResponse([
                'year' => $year,
                'holidays' => $holidays,
                'count' => $holidays->count(),
            ], 'Holidays for year retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve holidays by year', [
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to retrieve holidays by year', [], 0, 500);
        }
    }

    /**
     * Bulk create holidays (for yearly planning)
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'holidays' => 'required|array|min:1',
                'holidays.*.holiday_name' => 'required|string|max:255',
                'holidays.*.holiday_date' => 'required|date',
                'holidays.*.holiday_type' => 'nullable|string|max:50',
                'holidays.*.description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            $created = [];
            $skipped = [];
            $errors = [];

            foreach ($request->holidays as $index => $holidayData) {
                try {
                    // Check if date already exists
                    $existing = PublicHoliday::where('holiday_date', $holidayData['holiday_date'])->first();
                    if ($existing) {
                        $skipped[] = [
                            'index' => $index,
                            'date' => $holidayData['holiday_date'],
                            'reason' => 'Holiday already exists for this date'
                        ];
                        continue;
                    }

                    $holiday = PublicHoliday::create([
                        'holiday_name' => $holidayData['holiday_name'],
                        'holiday_date' => $holidayData['holiday_date'],
                        'holiday_type' => $holidayData['holiday_type'] ?? 'National',
                        'is_active' => true,
                        'description' => $holidayData['description'] ?? null,
                        'created_by' => (string) auth()->id(),
                    ]);

                    $created[] = $holiday;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'date' => $holidayData['holiday_date'] ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::connection('bcmis2')->commit();

            // Fetch created holidays with creator names
            $createdIds = collect($created)->pluck('id')->toArray();
            $createdHolidays = [];
            if (!empty($createdIds)) {
                $createdHolidays = DB::table('bcmis2.public_holidays as ph')
                    ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'ph.created_by')
                    ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'ph.modified_by')
                    ->whereIn('ph.id', $createdIds)
                    ->select(
                        'ph.id',
                        'ph.holiday_name',
                        'ph.holiday_date',
                        'ph.holiday_type',
                        'ph.is_active',
                        'ph.description',
                        'ph.created_at',
                        'ph.modified_at',
                        DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by"),
                        DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by")
                    )
                    ->get()
                    ->toArray();
            }

            return $this->sendResponse([
                'created' => count($created),
                'skipped' => count($skipped),
                'errors' => count($errors),
                'created_holidays' => $createdHolidays,
                'skipped_details' => $skipped,
                'error_details' => $errors,
            ], 'Bulk holiday creation completed');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to bulk create holidays', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to bulk create holidays: ' . $e->getMessage(), [], 0, 500);
        }
    }
}
