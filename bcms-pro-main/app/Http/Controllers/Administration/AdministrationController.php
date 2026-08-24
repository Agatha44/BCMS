<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\AuthRole;
use App\Models\AuthUser;
use App\Models\AuthUserRole;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\Role;
use App\Services\Administration\AuthRoleModuleService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdministrationController extends ConfigurationController
{
    private AuthRoleModuleService $authRoleModuleService;

    public function __construct(AuthRoleModuleService $authRoleModuleService)
    {
        $this->authRoleModuleService = $authRoleModuleService;
    }

    public function Users(Request $request)
    {
        try {
            $query = AuthUser::query()->with([
                'activeUserRoles.role:id,name,description,is_active',
            ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('middle_name', 'LIKE', "%{$search}%")
                        ->orWhere('surname', 'LIKE', "%{$search}%")
                        ->orWhere('username', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->filled('role')) {
                $role = $request->role;
                $query->whereHas('effectiveUserRoles.role', function ($q) use ($role) {
                    $q->where('name', 'LIKE', "%{$role}%");
                });
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSortFields = ['first_name', 'surname', 'username', 'email', 'status', 'created_at'];

            if (in_array($sortBy, $allowedSortFields, true)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
            $users = $query->paginate($perPage);

            $roleIds = $users->getCollection()
                ->flatMap(fn (AuthUser $user) => $user->activeUserRoles->pluck('role_id'))
                ->unique()
                ->values()
                ->all();
            $modulesByRoleId = $this->authRoleModuleService->getModulesGroupedByAuthRoleId($roleIds);

            $response = [
                'users' => $users->getCollection()
                    ->map(fn (AuthUser $user) => $this->formatUserWithRoles($user, $modulesByRoleId))
                    ->values(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ];

            return $this->sendResponse($response, 'Users retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve users: ' . $e->getMessage());
        }
    }

    /**
     * List auth users with their BMS bridge employee roles.
     */
    public function bridgeUsers(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
            $sortBy = $request->input('sort_by', 'nida');
            $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

            $allowedSortFields = [
                'nida', 'pf_number', 'first_name', 'middle_name', 'surname',
                'username', 'email', 'phone', 'status', 'created_at',
            ];

            if (!in_array($sortBy, $allowedSortFields, true)) {
                $sortBy = 'nida';
            }

            $users = AuthUser::query()
                ->with(['bridgeRoles.role:id,role_name'])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->input('search');
                    $query->where(function ($q) use ($search) {
                        foreach (['pf_number', 'first_name', 'middle_name', 'surname', 'username', 'email', 'phone', 'nida'] as $column) {
                            $q->orWhere($column, 'like', "%{$search}%");
                        }
                    });
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage)
                ->through(fn (AuthUser $user) => $this->formatBridgeUser($user));

            return $this->sendResponse([
                'employees' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ], 'Bridge users retrieved successfully');
        } catch (\Throwable $e) {
            return $this->sendError('Failed to retrieve bridge users: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get a bridge user by national ID with BMS role assignments only (auth_user, no bridge_employee).
     *
     * @param  string  $nationalId  auth_user.nida (national ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function showBridgeUser($nationalId): JsonResponse
    {
        try {
            if (empty($nationalId) || $nationalId === 'undefined' || $nationalId === 'null') {
                return $this->sendError('Invalid National ID provided. Please provide a valid National ID.', [], 0, 400);
            }

            $user = AuthUser::where('nida', $nationalId)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 0, 404);
            }

            $activeRoleAssignments = BridgeEmployeeRole::byNationalId($user->nida)
                ->active()
                ->with('role')
                ->orderBy('created_at', 'desc')
                ->get();

            $roles = $activeRoleAssignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'role' => $assignment->role ? [
                        'id' => $assignment->role->id,
                        'role_name' => $assignment->role->role_name,
                        'role_description' => $assignment->role->role_description,
                        'is_active' => $assignment->role->is_active,
                    ] : null,
                    'from_date' => $assignment->from_date,
                    'to_date' => $assignment->to_date,
                    'assigned_date' => $assignment->created_at,
                    'description' => $assignment->description,
                    'assigned_by' => $assignment->created_by,
                ];
            });

            return $this->sendResponse([
                'id' => $user->id,
                'pf_number' => $user->pf_number,
                'nida' => $user->nida,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'surname' => $user->surname,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $roles,
                'total_roles' => $roles->count(),
            ], 'Bridge user retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching bridge user: ' . $e->getMessage(), [
                'national_id' => $nationalId,
            ]);
            return $this->sendError('Failed to retrieve bridge user: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Create a bridge user (auth_user only). nida, pf_number, phone must be unique; email unique when provided.
     */
    public function createBridgeUser(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nida' => 'required|string|max:50',
                'pf_number' => 'required|string|max:50|unique:auth_user,pf_number',
                'first_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'surname' => 'required|string|max:50',
                'email' => 'nullable|email|max:100|unique:auth_user,email',
                'phone' => 'required|string|max:50|unique:auth_user,phone',
            ], [
                'pf_number.unique' => 'PF number already assigned to another user',
                'email.unique' => 'Email address already assigned to another user',
                'phone.unique' => 'Phone number already assigned to another user',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $nida = trim((string) $request->input('nida'));
            $pfNumber = trim((string) $request->input('pf_number'));
            $phone = trim((string) $request->input('phone'));
            $email = $request->filled('email') ? trim((string) $request->input('email')) : null;

            if (AuthUser::where('nida', $nida)->exists()) {
                return $this->sendError('User with this National ID already exists', [], 0, 409);
            }

            if (BridgeEmployee::where('national_id', $nida)->exists()) {
                return $this->sendError('User with this National ID already registered as staff', [], 0, 409);
            }

            if (BridgeEmployee::where('pfno', $pfNumber)->orWhere('pfno2', $pfNumber)->exists()) {
                return $this->sendError('PF number already registered to a staff member', [], 0, 409);
            }

            if (AuthUser::where('pf_number', $pfNumber)->exists()) {
                return $this->sendError('User with this PF number already exists', [], 0, 409);
            }

            if (AuthUser::where('phone', $phone)->exists()) {
                return $this->sendError('User with this phone number already exists', [], 0, 409);
            }

            if ($email !== null && $email !== '') {
                if (AuthUser::where('email', $email)->exists()) {
                    return $this->sendError('User with this email address already exists', [], 0, 409);
                }
            }

            $authUser = new AuthUser();
            $authUser->nida = $nida;
            $authUser->pf_number = $pfNumber;
            $authUser->first_name = $request->input('first_name');
            $authUser->middle_name = $request->input('middle_name');
            $authUser->surname = $request->input('surname');
            $authUser->email = $email;
            $authUser->phone = $phone;
            $authUser->username = strtolower($request->input('first_name') . '.' . $request->input('surname'));
            $authUser->status = 1;
            $authUser->password_hash = Hash::make($phone);
            $authUser->must_change_password = true;

            if (Auth::check()) {
                $authUser->created_by = (string) Auth::id();
            }

            $authUser->save();

            Log::info('Bridge user created', [
                'auth_user_id' => $authUser->id,
                'nida' => $authUser->nida,
            ]);

            return $this->sendResponse([
                'id' => $authUser->id,
                'nida' => $authUser->nida,
                'pf_number' => $authUser->pf_number,
                'first_name' => $authUser->first_name,
                'middle_name' => $authUser->middle_name,
                'surname' => $authUser->surname,
                'username' => $authUser->username,
                'email' => $authUser->email,
                'phone' => $authUser->phone,
                'status' => $authUser->status,
            ], 'Bridge user created successfully');
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return $this->sendError('Integrity constraint violation', [], 0, 409);
            }

            Log::error('Error creating bridge user: ' . $e->getMessage());
            return $this->sendError('Failed to create bridge user: ' . $e->getMessage(), [], 0, 500);
        } catch (\Exception $e) {
            Log::error('Error creating bridge user: ' . $e->getMessage());
            return $this->sendError('Failed to create bridge user: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update a bridge user (auth_user only). pf_number, phone must remain unique; email unique when provided.
     *
     * @param  string  $nationalId  auth_user.nida (national ID)
     */
    public function updateBridgeUser(Request $request, $nationalId): JsonResponse
    {
        try {
            if (empty($nationalId) || $nationalId === 'undefined' || $nationalId === 'null') {
                return $this->sendError('Invalid National ID provided. Please provide a valid National ID.', [], 0, 400);
            }

            $user = AuthUser::where('nida', $nationalId)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 0, 404);
            }

            $validator = Validator::make($request->all(), [
                'pf_number' => 'required|string|max:50|unique:auth_user,pf_number,' . $user->id,
                'first_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'surname' => 'required|string|max:50',
                'email' => 'nullable|email|max:100|unique:auth_user,email,' . $user->id,
                'phone' => 'required|string|max:50|unique:auth_user,phone,' . $user->id,
            ], [
                'pf_number.unique' => 'PF number already assigned to another user',
                'email.unique' => 'Email address already assigned to another user',
                'phone.unique' => 'Phone number already assigned to another user',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $pfNumber = trim((string) $request->input('pf_number'));
            $phone = trim((string) $request->input('phone'));
            $email = $request->filled('email') ? trim((string) $request->input('email')) : null;

            if (BridgeEmployee::where(function ($query) use ($pfNumber) {
                $query->where('pfno', $pfNumber)->orWhere('pfno2', $pfNumber);
            })->where('national_id', '!=', $user->nida)->exists()) {
                return $this->sendError('PF number already registered to a staff member', [], 0, 409);
            }

            if ($email !== null && $email !== '') {
                if (AuthUser::where('email', $email)->where('nida', '!=', $user->nida)->exists()) {
                    return $this->sendError('Email address already registered to a staff member', [], 0, 409);
                }
            }

            if (AuthUser::where('phone', $phone)->where('nida', '!=', $user->nida)->exists()) {
                return $this->sendError('Phone number already registered to a staff member', [], 0, 409);
            }

            $user->pf_number = $pfNumber;
            $user->first_name = $request->input('first_name');
            $user->middle_name = $request->input('middle_name');
            $user->surname = $request->input('surname');
            $user->email = $email;
            $user->phone = $phone;
            $user->username = strtolower($request->input('first_name') . '.' . $request->input('surname'));

            $user->save();

            Log::info('Bridge user updated', [
                'auth_user_id' => $user->id,
                'nida' => $user->nida,
            ]);

            return $this->sendResponse([
                'id' => $user->id,
                'nida' => $user->nida,
                'pf_number' => $user->pf_number,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'surname' => $user->surname,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ], 'Bridge user updated successfully');
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return $this->sendError('Integrity constraint violation', [], 0, 409);
            }

            Log::error('Error updating bridge user: ' . $e->getMessage(), [
                'national_id' => $nationalId,
            ]);
            return $this->sendError('Failed to update bridge user: ' . $e->getMessage(), [], 0, 500);
        } catch (\Exception $e) {
            Log::error('Error updating bridge user: ' . $e->getMessage(), [
                'national_id' => $nationalId,
            ]);
            return $this->sendError('Failed to update bridge user: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Assign a BMS role to a bridge user.
     *
     * bridge_employee_role.national_id is set from auth_user.nida.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId  auth_user.nida (national ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(Request $request, $nationalId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $user = AuthUser::where('nida', $nationalId)->where('status', 1)->first();

            if (!$user) {
                return $this->sendError('User not found or inactive', [], 0, 404);
            }

            $role = Role::find($request->role_id);
            if (!$role) {
                return $this->sendError('BMS role not found', [], 0, 404);
            }

            if (!$role->is_active) {
                return $this->sendError('Cannot assign inactive role', [], 0, 422);
            }

            DB::beginTransaction();

            $existingRoleAssignment = BridgeEmployeeRole::byNationalId($user->nida)
                ->byRoleId($request->role_id)
                ->where('is_active', true)
                ->first();

            if ($existingRoleAssignment) {
                DB::rollBack();
                return $this->sendError('Role is already assigned to this employee', [], 0, 409);
            }

            $today = now()->toDateString();
            $fromDate = $request->filled('from_date') ? $request->date('from_date')?->toDateString() : $today;
            $toDate = $request->filled('to_date') ? $request->date('to_date')?->toDateString() : null;

            $isActive = true;
            if ($fromDate && $fromDate > $today) {
                $isActive = false;
            }
            if ($toDate && $toDate < $today) {
                $isActive = false;
            }

            $roleAssignmentData = [
                'national_id' => $user->nida,
                'role_id' => $request->role_id,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'is_active' => $isActive,
                'description' => $request->input('description', 'Role assigned'),
                'created_at' => now(),
                'modified_at' => now(),
            ];

            if (Auth::check()) {
                $roleAssignmentData['created_by'] = (string) Auth::id();
                $roleAssignmentData['modified_by'] = (string) Auth::id();
            }

            $roleAssignment = BridgeEmployeeRole::create($roleAssignmentData);

            DB::commit();

            Log::info('BMS role assigned to employee', [
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
                'role_id' => $request->role_id,
                'assignment_id' => $roleAssignment->id,
            ]);

            return $this->sendResponse([
                'assignment_id' => $roleAssignment->id,
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
                'role' => [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                    'role_description' => $role->role_description,
                ],
                'from_date' => $roleAssignment->from_date,
                'to_date' => $roleAssignment->to_date,
                'is_active' => $roleAssignment->is_active,
                'assigned_date' => $roleAssignment->created_at,
                'message' => 'BMS role assigned successfully',
            ], 'BMS role assigned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning BMS role to employee: ' . $e->getMessage());
            return $this->sendError('Failed to assign BMS role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Revoke a BMS role from a bridge user.
     *
     * bridge_employee_role.national_id is matched via auth_user.nida.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId  auth_user.nida (national ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeRole(Request $request, $nationalId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $user = AuthUser::where('nida', $nationalId)->where('status', 1)->first();

            if (!$user) {
                return $this->sendError('User not found or inactive', [], 0, 404);
            }

            $role = Role::find($request->role_id);
            if (!$role) {
                return $this->sendError('BMS role not found', [], 0, 404);
            }

            DB::beginTransaction();

            $existingRoleAssignment = BridgeEmployeeRole::byNationalId($user->nida)
                ->byRoleId($request->role_id)
                ->where('is_active', true)
                ->first();

            if (!$existingRoleAssignment) {
                DB::rollBack();
                return $this->sendError('Role is not assigned to this employee', [], 0, 404);
            }

            $existingRoleAssignment->is_active = false;
            $existingRoleAssignment->revoked_at = now();
            $existingRoleAssignment->modified_at = now();

            if ($request->filled('description')) {
                $existingRoleAssignment->description = $request->input('description');
            }

            if (Auth::check()) {
                $existingRoleAssignment->revoked_by = (string) Auth::id();
                $existingRoleAssignment->modified_by = (string) Auth::id();
            }

            $existingRoleAssignment->save();

            DB::commit();

            Log::info('BMS role revoked from employee', [
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
                'role_id' => $request->role_id,
                'assignment_id' => $existingRoleAssignment->id,
            ]);

            return $this->sendResponse([
                'assignment_id' => $existingRoleAssignment->id,
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
                'role' => [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                    'role_description' => $role->role_description,
                ],
                'revoked_at' => $existingRoleAssignment->revoked_at,
                'message' => 'BMS role revoked successfully',
            ], 'BMS role revoked successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error revoking BMS role from employee: ' . $e->getMessage());
            return $this->sendError('Failed to revoke BMS role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle bridge user active/inactive status.
     *
     * Deactivating (active → inactive) revokes all active BMS role assignments.
     * Activating (inactive → active) only re-enables the account; roles must be reassigned separately.
     *
     * @param  string  $nationalId  auth_user.nida (national ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleBridgeUserStatus(Request $request, $nationalId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            if (empty($nationalId) || $nationalId === 'undefined' || $nationalId === 'null') {
                return $this->sendError('Invalid National ID provided. Please provide a valid National ID.', [], 0, 400);
            }

            $user = AuthUser::where('nida', $nationalId)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 0, 404);
            }

            $isCurrentlyActive = (int) $user->status === 1;
            $now = now();
            $actorId = Auth::check() ? (string) Auth::id() : null;

            DB::beginTransaction();

            if ($isCurrentlyActive) {
                $user->status = 0;
                $user->save();

                $description = $request->input('description', 'User deactivated');
                $revokedAssignmentIds = [];

                $activeAssignments = BridgeEmployeeRole::byNationalId($user->nida)
                    ->where('is_active', true)
                    ->get();

                foreach ($activeAssignments as $assignment) {
                    $assignment->is_active = false;
                    $assignment->revoked_at = $now;
                    $assignment->modified_at = $now;
                    $assignment->description = $description;

                    if ($actorId !== null) {
                        $assignment->revoked_by = $actorId;
                        $assignment->modified_by = $actorId;
                    }

                    $assignment->save();
                    $revokedAssignmentIds[] = $assignment->id;
                }

                DB::commit();

                Log::info('Bridge user deactivated', [
                    'auth_user_id' => $user->id,
                    'national_id' => $user->nida,
                    'revoked_role_count' => count($revokedAssignmentIds),
                ]);

                return $this->sendResponse([
                    'auth_user_id' => $user->id,
                    'national_id' => $user->nida,
                    'status' => $user->status,
                    'status_text' => 'Inactive',
                    'revoked_roles_count' => count($revokedAssignmentIds),
                    'revoked_assignment_ids' => $revokedAssignmentIds,
                    'toggled_at' => $now->toIso8601String(),
                    'message' => 'User deactivated and active roles revoked successfully',
                ], 'User deactivated successfully');
            }

            $user->status = 1;
            $user->save();

            DB::commit();

            Log::info('Bridge user activated', [
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
            ]);

            return $this->sendResponse([
                'auth_user_id' => $user->id,
                'national_id' => $user->nida,
                'status' => $user->status,
                'status_text' => 'Active',
                'revoked_roles_count' => 0,
                'revoked_assignment_ids' => [],
                'toggled_at' => $now->toIso8601String(),
                'message' => 'User activated successfully. Assign BMS roles separately if required.',
            ], 'User activated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error toggling bridge user status: ' . $e->getMessage(), [
                'national_id' => $nationalId,
            ]);
            return $this->sendError('Failed to toggle user status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Fetch BMS roles for the currently logged-in user (auth_user.nida → bridge_employee_role).
     */
    public function fetchRoleForLoggedUser(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            if (empty($authUser->nida)) {
                return $this->sendResponse([
                    'has_bms_role' => false,
                    'roles' => [],
                    'employee' => null,
                    'message' => 'No national ID linked to this user account',
                ], 'User has no BMS roles');
            }

            $activeRoleAssignments = BridgeEmployeeRole::byNationalId($authUser->nida)
                ->active()
                ->with('role')
                ->orderBy('created_at', 'desc')
                ->get();

            $roles = $activeRoleAssignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'role_id' => $assignment->role_id,
                    'role_name' => $assignment->role ? $assignment->role->role_name : null,
                    'role_description' => $assignment->role ? $assignment->role->role_description : null,
                    'is_active' => $assignment->role ? $assignment->role->is_active : false,
                    'assigned_date' => $assignment->created_at,
                ];
            });

            return $this->sendResponse([
                'has_bms_role' => $roles->count() > 0,
                'roles' => $roles,
                'total_roles' => $roles->count(),
                'employee' => [
                    'national_id' => $authUser->nida,
                    'pfno' => $authUser->pf_number ?? null,
                    'fname' => $authUser->first_name,
                    'mname' => $authUser->middle_name,
                    'sname' => $authUser->surname,
                    'username' => $authUser->username,
                ],
            ], 'BMS roles retrieved successfully for logged-in user');
        } catch (\Exception $e) {
            Log::error('Error fetching BMS role for logged-in user: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to retrieve BMS role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function AddNewUser(Request $request)
    {
        try {
            $authUser = new AuthUser();
            $authUser->first_name = $request->input('first_name');
            $authUser->middle_name = $request->input('middle_name');
            $authUser->surname = $request->input('surname');
            $authUser->email = $request->input('email');
            $authUser->phone = $request->input('phone');
            $authUser->nida = $request->input('nida');
            $authUser->username = $request->input('first_name') . '.' . $request->input('surname');
            $authUser->status = 1;
            $authUser->password_hash = Hash::make($request->input('phone'));
            $authUser->must_change_password = true;

            if ($authUser->save()) {
                $success['username'] = $authUser->username;
                $success['phone'] = $authUser->phone;
                $success['email'] = $authUser->email;
                return $this->sendResponse($success, 'Successfully saved');
            } else {
                return $this->sendError('Something went wrong, contact administrator');
            }
        } catch (QueryException $exception) {

            if ($exception->getCode() == 23000) {
                return $this->sendError('Integrity constraint violation');
            } else {
                return $this->sendError('Something went wrong, contact administrator');
            }
        } catch (Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function getUser($id)
    {
        try {
            $user = AuthUser::with([
                'activeUserRoles.role:id,name,description,is_active',
            ])->find($id);

            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            $modulesByRoleId = $this->authRoleModuleService->getModulesGroupedByAuthRoleId(
                $user->activeUserRoles->pluck('role_id')->unique()->values()->all()
            );

            return $this->sendResponse(
                $this->formatUserWithRoles($user, $modulesByRoleId),
                'User retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve user: ' . $e->getMessage());
        }
    }

    public function getAuthRoleModules($id): JsonResponse
    {
        try {
            $role = AuthRole::find($id);
            if (!$role) {
                return $this->sendError('Role not found', [], 404, 404);
            }

            $modules = $this->authRoleModuleService->getModulesGroupedByAuthRoleId([(int) $id]);

            return $this->sendResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_active' => (int) $role->is_active,
                ],
                'modules' => $modules[(int) $id] ?? [],
            ], 'Role modules retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve role modules: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function assignModuleToAuthRole(Request $request, $id): JsonResponse
    {
        try {
            $role = AuthRole::find($id);
            if (!$role) {
                return $this->sendError('Role not found', [], 404, 404);
            }

            $validator = \Validator::make($request->all(), [
                'module_id' => 'required_without:module_ids|integer',
                'module_ids' => 'required_without:module_id|array',
                'module_ids.*' => 'integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 422, 422);
            }

            $moduleIds = $request->filled('module_ids')
                ? array_map('intval', $request->module_ids)
                : [(int) $request->module_id];

            $assigned = 0;
            foreach ($moduleIds as $moduleId) {
                $this->authRoleModuleService->assignModuleToAuthRole((int) $id, $moduleId, auth()->id());
                $assigned++;
            }

            $modules = $this->authRoleModuleService->getModulesGroupedByAuthRoleId([(int) $id]);

            return $this->sendResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                ],
                'modules' => $modules[(int) $id] ?? [],
                'assigned_count' => $assigned,
            ], 'Module(s) assigned to role successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422, 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to assign module to role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'surname' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'nida' => 'nullable|string|max:50'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors(), 422);
            }

            $user = AuthUser::find($id);
            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            // Check if email is already taken by another user
            $existingUser = AuthUser::where('email', $request->email)
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingUser) {
                return $this->sendError('Email address is already taken by another user');
            }

            // Update user data
            $user->first_name = $request->first_name;
            $user->middle_name = $request->middle_name;
            $user->surname = $request->surname;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->nida = $request->nida;
            $user->save();

            return $this->sendResponse([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'surname' => $user->surname,
                'email' => $user->email,
                'phone' => $user->phone,
                'nida' => $user->nida
            ], 'User updated successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to update user: ' . $e->getMessage());
        }
    }

    public function updateUserStatus(Request $request, $id)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'status' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors(), 422);
            }

            $user = AuthUser::find($id);
            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            $user->status = $request->status;
            $user->save();

            return $this->sendResponse([
                'id' => $user->id,
                'status' => $user->status,
                'status_text' => $user->status == 1 ? 'Active' : 'Inactive'
            ], 'User status updated successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to update user status: ' . $e->getMessage());
        }
    }

    public function getAvailableRoles()
    {
        try {
            $roles = DB::table('auth_role')
                ->select('id', 'name', 'description', 'is_active')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get();

            return $this->sendResponse($roles, 'Roles retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve roles: ' . $e->getMessage());
        }
    }

    public function updateUserRoles(Request $request, $id)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'role_ids' => 'required|array',
                'role_ids.*' => 'integer|exists:auth_role,id'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors(), 422);
            }

            $user = AuthUser::find($id);
            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            // Delete existing roles
            DB::table('auth_user_role')->where('user_id', $id)->delete();

            // Add new roles
            $roleData = [];
            foreach ($request->role_ids as $roleId) {
                $roleData[] = [
                    'user_id' => $id,
                    'role_id' => $roleId,
                    'is_active' => 1,
                    'start_date' => null,
                    'end_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($roleData)) {
                DB::table('auth_user_role')->insert($roleData);
            }

            return $this->sendResponse([], 'User roles updated successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to update user roles: ' . $e->getMessage());
        }
    }

    /**
     * Assign a role to a user (auth_user_role). Reactivates if previously revoked.
     */
    public function assignRoleToUser(Request $request, $id): JsonResponse
    {
        try {
            $validated = $this->validateRoleAssignmentRequest($request, [
                'role_id' => 'required|integer|exists:auth_role,id',
            ]);

            if ($validated instanceof JsonResponse) {
                return $validated;
            }

            return $this->assignRoleToUserRecord(
                (int) $id,
                (int) $request->role_id,
                $request->input('start_date'),
                $request->input('end_date')
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to assign role to user: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function grantRoleToUser(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRoleAssignmentRequest($request, [
                'user_id' => 'required|integer|exists:auth_user,id',
                'role_id' => 'required|integer|exists:auth_role,id',
            ]);

            if ($validated instanceof JsonResponse) {
                return $validated;
            }

            return $this->assignRoleToUserRecord(
                (int) $request->user_id,
                (int) $request->role_id,
                $request->input('start_date'),
                $request->input('end_date')
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to grant role to user: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * @param  array<string, string>  $rules
     * @return true|JsonResponse
     */
    private function validateRoleAssignmentRequest(Request $request, array $rules)
    {
        $validator = \Validator::make($request->all(), array_merge($rules, [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]));

        $validator->after(function ($v) use ($request) {
            if ($request->filled('start_date') && $request->filled('end_date')
                && $request->end_date < $request->start_date) {
                $v->errors()->add('end_date', 'End date must be on or after start date.');
            }
        });

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 422, 422);
        }

        return true;
    }

    private function assignRoleToUserRecord(
        int $userId,
        int $roleId,
        ?string $startDate = null,
        ?string $endDate = null
    ): JsonResponse
    {
        $user = AuthUser::find($userId);
        if (!$user) {
            return $this->sendError('User not found', [], 404, 404);
        }

        $role = AuthRole::find($roleId);
        if (!$role) {
            return $this->sendError('Role not found', [], 404, 404);
        }

        if ((int) $role->is_active !== 1) {
            return $this->sendError('Cannot assign an inactive role', [], 422, 422);
        }

        $mapping = AuthUserRole::query()
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->first();

        $message = 'Role assigned successfully';

        $assignmentData = [
            'is_active' => 1,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'updated_by' => auth()->id(),
        ];

        if ($mapping) {
            $wasRevoked = (int) $mapping->is_active === 0;
            $unchanged = !$wasRevoked
                && $mapping->start_date?->toDateString() === $startDate
                && $mapping->end_date?->toDateString() === $endDate;

            if ($unchanged) {
                $message = 'Role already assigned to user';
            } else {
                $mapping->fill($assignmentData);
                $mapping->save();
                $message = $wasRevoked
                    ? 'Role re-assigned successfully'
                    : 'Role assignment updated successfully';
            }
        } else {
            AuthUserRole::create(array_merge($assignmentData, [
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_by' => auth()->id(),
            ]));
        }

        $user->load(['activeUserRoles.role:id,name,description,is_active']);

        $modulesByRoleId = $this->authRoleModuleService->getModulesGroupedByAuthRoleId(
            $user->activeUserRoles->pluck('role_id')->unique()->values()->all()
        );

        return $this->sendResponse($this->formatUserWithRoles($user, $modulesByRoleId), $message);
    }

    public function revokeUserRole(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:auth_user,id',
                'role_id' => 'required|integer|exists:auth_role,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 422, 422);
            }

            $userId = (int) $request->user_id;
            $roleId = (int) $request->role_id;

            $user = AuthUser::find($userId);
            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            $role = DB::table('auth_role')->where('id', $roleId)->first();
            if (!$role) {
                return $this->sendError('Role not found', [], 404, 404);
            }

            $getCurrentRoles = static function (int $targetUserId) {
                return AuthUserRole::query()
                    ->currentlyEffective()
                    ->with('role:id,name')
                    ->where('user_id', $targetUserId)
                    ->get()
                    ->map(fn ($userRole) => [
                        'id' => $userRole->role->id,
                        'name' => $userRole->role->name,
                        'start_date' => $userRole->start_date?->format('Y-m-d'),
                        'end_date' => $userRole->end_date?->format('Y-m-d'),
                    ])
                    ->sortBy('name')
                    ->values();
            };

            $buildUserPayload = static function ($targetUser) {
                return [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username ?? null,
                    'email' => $targetUser->email ?? null,
                    'phone' => $targetUser->phone ?? null,
                    'full_name' => trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->middle_name ?? '') . ' ' . ($targetUser->surname ?? '')),
                ];
            };

            $mapping = DB::table('auth_user_role')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->first();

            if (!$mapping) {
                return $this->sendError('User role not found', [], 404, 404);
            }

            if ((int)($mapping->is_active) === 0) {
                $currentRoles = $getCurrentRoles($userId);

                return $this->sendResponse([
                    'user' => $buildUserPayload($user),
                    'revoked_roles' => [],
                    'current_roles' => $currentRoles,
                ], 'Role already revoked', 200);
            }

            DB::table('auth_user_role')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->update([
                    'is_active' => 0,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $currentRoles = $getCurrentRoles($userId);

            return $this->sendResponse([
                'user' => $buildUserPayload($user),
                'revoked_roles' => [
                    [
                        'id' => $role->id,
                        'name' => $role->name,
                    ]
                ],
                'current_roles' => $currentRoles,
            ], 'Role revoked successfully', 200);

        } catch (\Exception $e) {
            return $this->sendError('Failed to revoke role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all roles with pagination and filtering
     */
    public function getRoles(Request $request)
    {
        try {
            $query = DB::table('auth_role');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status !== '') {
                $query->where('is_active', $request->status);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $roles = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles->items(),
                    'pagination' => [
                        'current_page' => $roles->currentPage(),
                        'last_page' => $roles->lastPage(),
                        'per_page' => $roles->perPage(),
                        'total' => $roles->total(),
                        'from' => $roles->firstItem(),
                        'to' => $roles->lastItem()
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific role
     */
    public function getRole($id)
    {
        try {
            $role = DB::table('auth_role')->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $role
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:50|unique:auth_role,name',
                'description' => 'required|string',
                'is_active' => 'boolean'
            ]);

            $roleId = DB::table('auth_role')->insertGetId([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', 1),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $role = DB::table('auth_role')->find($roleId);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, $id)
    {
        try {
            $role = DB::table('auth_role')->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:50|unique:auth_role,name,' . $id,
                'description' => 'required|string',
                'is_active' => 'boolean'
            ]);

            DB::table('auth_role')->where('id', $id)->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', $role->is_active),
                'updated_by' => auth()->id(),
                'updated_at' => now()
            ]);

            $updatedRole = DB::table('auth_role')->find($id);

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $updatedRole
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBridgeUser(AuthUser $user): array
    {
        return [
            'id' => $user->id,
            'pf_number' => $user->pf_number,
            'nida' => $user->nida,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'surname' => $user->surname,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->bridgeRoles
                ->pluck('role')
                ->filter()
                ->unique('id')
                ->map(fn ($role) => [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $modulesByRoleId
     * @return array<string, mixed>
     */
    private function formatUserWithRoles(AuthUser $user, array $modulesByRoleId = []): array
    {
        if ($modulesByRoleId === [] && $user->relationLoaded('activeUserRoles')) {
            $modulesByRoleId = $this->authRoleModuleService->getModulesGroupedByAuthRoleId(
                $user->activeUserRoles->pluck('role_id')->unique()->values()->all()
            );
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'surname' => $user->surname,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'status_text' => (int) $user->status === 1 ? 'Active' : 'Inactive',
            'roles' => $user->activeUserRoles
                ->filter(fn ($userRole) => $userRole->role !== null)
                ->map(function ($userRole) use ($modulesByRoleId) {
                    $roleId = (int) $userRole->role->id;

                    return [
                        'user_role_id' => $userRole->id,
                        'id' => $roleId,
                        'name' => $userRole->role->name,
                        'description' => $userRole->role->description,
                        'is_active' => (int) $userRole->role->is_active,
                        'assignment_is_active' => (int) $userRole->is_active,
                        'start_date' => $userRole->start_date?->format('Y-m-d'),
                        'end_date' => $userRole->end_date?->format('Y-m-d'),
                        'is_currently_effective' => $userRole->isCurrentlyEffective(),
                        'modules' => $modulesByRoleId[$roleId] ?? [],
                    ];
                })
                ->values()
                ->all(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Toggle role status
     */
    public function toggleRoleStatus($id)
    {
        try {
            $role = DB::table('auth_role')->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            DB::table('auth_role')->where('id', $id)->update([
                'is_active' => !$role->is_active,
                'updated_by' => auth()->id(),
                'updated_at' => now()
            ]);

            $updatedRole = DB::table('auth_role')->find($id);

            return response()->json([
                'success' => true,
                'message' => 'Role status updated successfully',
                'data' => $updatedRole
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role status: ' . $e->getMessage()
            ], 500);
        }
    }


}
