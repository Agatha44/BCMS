<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use App\Models\BodyType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegistrationRequestController extends Controller
{
    /**
     * Get all registration requests with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = RegistrationRequest::with(['bodyType', 'submittedBy', 'reviewedBy']);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%")
                      ->orWhere('owner_name', 'like', "%{$search}%")
                      ->orWhere('owner_phone', 'like', "%{$search}%")
                      ->orWhere('owner_email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('request_type')) {
                $query->ofType($request->get('request_type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'submitted_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $requests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Registration requests retrieved successfully',
                'data' => [
                    'requests' => $requests->items(),
                    'pagination' => [
                        'current_page' => $requests->currentPage(),
                        'last_page' => $requests->lastPage(),
                        'per_page' => $requests->perPage(),
                        'total' => $requests->total(),
                        'from' => $requests->firstItem(),
                        'to' => $requests->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching registration requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve registration requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific registration request.
     */
    public function show($id): JsonResponse
    {
        try {
            $request = RegistrationRequest::with(['bodyType', 'submittedBy', 'reviewedBy'])->find($id);

            if (!$request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration request retrieved successfully',
                'data' => $request
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching registration request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve registration request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new registration request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_type' => ['required', Rule::in(['registration', 'exemption', 'update'])],
                'plate_number' => 'required|string|max:20',
                'body_type_id' => 'nullable|exists:body_type,id',
                'body_type_name' => 'nullable|string|max:100',
                'owner_name' => $request->input('request_type') === 'update' ? 'nullable|string|max:255' : 'required|string|max:255',
                'owner_phone' => $request->input('request_type') === 'update' ? 'nullable|string|max:20' : 'required|string|max:20',
                'owner_email' => 'nullable|email|max:255',
                'nida_number' => 'nullable|string|max:50',
                'exemption_reason' => 'nullable|string',
                'update_details' => 'nullable|array',
                'registration_card_base64' => 'nullable|string',
                'registration_card_filename' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['submitted_by'] = auth()->id();
            $data['status'] = 'pending';

            // Handle base64 file upload for registration card
            if ($request->filled('registration_card_base64') && $request->filled('registration_card_filename')) {
                $base64Data = $request->input('registration_card_base64');
                $fileName = time() . '_' . $request->input('registration_card_filename');
                
                // Decode base64 and save file
                $fileData = base64_decode($base64Data);
                $filePath = 'registration_cards/' . $fileName;
                $fullPath = storage_path('app/public/' . $filePath);
                
                // Ensure directory exists
                $directory = dirname($fullPath);
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                // Save the file
                file_put_contents($fullPath, $fileData);
                $data['registration_card_path'] = $filePath;
            }

            $registrationRequest = RegistrationRequest::create($data);

            // Load relationships
            $registrationRequest->load(['bodyType', 'submittedBy']);

            Log::info('Registration request created', [
                'id' => $registrationRequest->id,
                'type' => $registrationRequest->request_type,
                'plate_number' => $registrationRequest->plate_number,
                'submitted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration request submitted successfully',
                'data' => $registrationRequest
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating registration request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit registration request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a registration request (only for pending requests).
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $registrationRequest = RegistrationRequest::find($id);

            if (!$registrationRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration request not found'
                ], 404);
            }

            if (!$registrationRequest->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update non-pending request'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'plate_number' => 'sometimes|required|string|max:20',
                'body_type_id' => 'nullable|exists:body_type,id',
                'body_type_name' => 'nullable|string|max:100',
                'owner_name' => 'sometimes|required|string|max:255',
                'owner_phone' => 'sometimes|required|string|max:20',
                'owner_email' => 'nullable|email|max:255',
                'nida_number' => 'nullable|string|max:50',
                'exemption_reason' => 'nullable|string',
                'update_details' => 'nullable|array',
                'registration_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Handle base64 file upload for registration card
            if ($request->filled('registration_card_base64') && $request->filled('registration_card_filename')) {
                $base64Data = $request->input('registration_card_base64');
                $fileName = time() . '_' . $request->input('registration_card_filename');
                
                // Decode base64 and save file
                $fileData = base64_decode($base64Data);
                $filePath = 'registration_cards/' . $fileName;
                $fullPath = storage_path('app/public/' . $filePath);
                
                // Ensure directory exists
                $directory = dirname($fullPath);
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                // Save the file
                file_put_contents($fullPath, $fileData);
                $data['registration_card_path'] = $filePath;
            }

            $registrationRequest->update($data);
            $registrationRequest->load(['bodyType', 'submittedBy']);

            Log::info('Registration request updated', [
                'id' => $registrationRequest->id,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration request updated successfully',
                'data' => $registrationRequest
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating registration request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update registration request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Review a registration request (approve/reject).
     */
    public function review(Request $request, $id): JsonResponse
    {
        try {
            $registrationRequest = RegistrationRequest::find($id);

            if (!$registrationRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration request not found'
                ], 404);
            }

            if (!$registrationRequest->canBeReviewed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request cannot be reviewed'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'status' => ['required', Rule::in(['approved', 'rejected'])],
                'comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['reviewed_by'] = auth()->id();
            $data['reviewed_at'] = now();

            $registrationRequest->update($data);
            $registrationRequest->load(['bodyType', 'submittedBy', 'reviewedBy']);

            // Process the request based on status
            if ($registrationRequest->isApproved()) {
                $this->processApprovedRequest($registrationRequest);
            }

            Log::info('Registration request reviewed', [
                'id' => $registrationRequest->id,
                'status' => $registrationRequest->status,
                'reviewed_by' => auth()->id(),
                'comments' => $data['comments'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => "Registration request {$registrationRequest->status} successfully",
                'data' => $registrationRequest
            ]);

        } catch (\Exception $e) {
            Log::error('Error reviewing registration request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to review registration request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a registration request (only pending requests).
     */
    public function destroy($id): JsonResponse
    {
        try {
            $registrationRequest = RegistrationRequest::find($id);

            if (!$registrationRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration request not found'
                ], 404);
            }

            if (!$registrationRequest->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete non-pending request'
                ], 422);
            }

            $registrationRequest->delete();

            Log::info('Registration request deleted', [
                'id' => $id,
                'deleted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration request deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting registration request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete registration request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download registration card file.
     */
    public function downloadRegistrationCard($id): JsonResponse
    {
        try {
            $registrationRequest = RegistrationRequest::find($id);

            if (!$registrationRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration request not found'
                ], 404);
            }

            if (!$registrationRequest->registration_card_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No registration card file found'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $registrationRequest->registration_card_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration card file not found'
                ], 404);
            }

            // Read file content and convert to base64
            $fileContent = file_get_contents($filePath);
            $base64Content = base64_encode($fileContent);

            return response()->json([
                'success' => true,
                'message' => 'Registration card file retrieved successfully',
                'data' => [
                    'file_path' => $registrationRequest->registration_card_path,
                    'file_url' => asset('storage/' . $registrationRequest->registration_card_path),
                    'file_name' => basename($registrationRequest->registration_card_path),
                    'file_content' => $base64Content
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error downloading registration card: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download registration card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for pending requests for a specific vehicle.
     */
    public function checkPendingRequests($plateNumber): JsonResponse
    {
        try {
            $pendingRequests = RegistrationRequest::where('plate_number', $plateNumber)
                ->where('status', 'pending')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending requests check completed',
                'data' => [
                    'has_pending' => $pendingRequests->count() > 0,
                    'pending_count' => $pendingRequests->count(),
                    'pending_requests' => $pendingRequests->map(function ($request) {
                        return [
                            'id' => $request->id,
                            'request_type' => $request->request_type,
                            'submitted_at' => $request->submitted_at,
                            'submitted_by' => $request->submittedBy ? $request->submittedBy->name : 'Unknown'
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking pending requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check pending requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for registration requests.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => RegistrationRequest::count(),
                'pending' => RegistrationRequest::pending()->count(),
                'approved' => RegistrationRequest::approved()->count(),
                'rejected' => RegistrationRequest::rejected()->count(),
                'by_type' => [
                    'registration' => RegistrationRequest::ofType('registration')->count(),
                    'exemption' => RegistrationRequest::ofType('exemption')->count(),
                    'update' => RegistrationRequest::ofType('update')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching registration statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process approved registration request.
     */
    private function processApprovedRequest(RegistrationRequest $request): void
    {
        try {
            switch ($request->request_type) {
                case 'registration':
                    $this->processRegistrationApproval($request);
                    break;
                case 'exemption':
                    $this->processExemptionApproval($request);
                    break;
                case 'update':
                    $this->processUpdateApproval($request);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Error processing approved request: ' . $e->getMessage(), [
                'request_id' => $request->id,
                'request_type' => $request->request_type
            ]);
        }
    }

    /**
     * Process approved registration request.
     */
    private function processRegistrationApproval(RegistrationRequest $request): void
    {
        // TODO: Create vehicle record in vehicles table
        // TODO: Create account if needed
        // TODO: Send notification to user
        Log::info('Processing approved registration request', [
            'request_id' => $request->id,
            'plate_number' => $request->plate_number
        ]);
    }

    /**
     * Process approved exemption request.
     */
    private function processExemptionApproval(RegistrationRequest $request): void
    {
        // TODO: Update vehicle exemption status
        // TODO: Send notification to user
        Log::info('Processing approved exemption request', [
            'request_id' => $request->id,
            'plate_number' => $request->plate_number
        ]);
    }

    /**
     * Process approved update request.
     */
    private function processUpdateApproval(RegistrationRequest $request): void
    {
        // TODO: Update vehicle details based on update_details
        // TODO: Send notification to user
        Log::info('Processing approved update request', [
            'request_id' => $request->id,
            'plate_number' => $request->plate_number
        ]);
    }
}
