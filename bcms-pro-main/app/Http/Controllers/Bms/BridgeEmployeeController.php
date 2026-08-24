<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\BridgeOffice;
use App\Models\Bms\Role;
use App\Models\AuthUser;
use App\Services\ReferralApprovalService;
use App\Constants\EmployeeStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\QueryException;

class BridgeEmployeeController extends BasicController
{
    protected $approvalService;

    public function __construct(ReferralApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }
    /**
     * Get all active employees.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveEmployees(Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'national_id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = BridgeEmployee::active()->with(['bank', 'employmentType', 'scheme', 'roles', 'department']);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pfno', 'like', "%{$search}%")
                        ->orWhere('fname', 'like', "%{$search}%")
                        ->orWhere('mname', 'like', "%{$search}%")
                        ->orWhere('sname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $employees = $query->paginate($perPage);

            // Transform employees to include bank name, employment type name, scheme name, and role names
            $transformedEmployees = $employees->getCollection()->map(function ($employee) {
                $employeeData = $employee->toArray();
                $employeeData['bank_name'] = $employee->bank ? $employee->bank->bank_name : null;
                $employeeData['emptype_name'] = $employee->employmentType ? $employee->employmentType->emptype_name : null;
                $employeeData['scheme_name'] = $employee->scheme ? $employee->scheme->scheme_name : null;
                // Include all role names from active roles as comma-separated string
                $roleNames = $employee->roles ? $employee->roles->pluck('role_name')->toArray() : [];
                $employeeData['role_name'] = !empty($roleNames) ? implode(', ', $roleNames) : null;
                return $employeeData;
            });

            return $this->sendResponse([
                'employees' => $transformedEmployees,
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                ]
            ], 'Active employees retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active employees: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve active employees: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'national_id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = BridgeEmployee::with(['bank', 'employmentType', 'scheme', 'roles', 'department']);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pfno', 'like', "%{$search}%")
                        ->orWhere('fname', 'like', "%{$search}%")
                        ->orWhere('mname', 'like', "%{$search}%")
                        ->orWhere('sname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $employees = $query->paginate($perPage);

            // Transform employees to include bank name, employment type name, scheme name, and role names
            $transformedEmployees = $employees->getCollection()->map(function ($employee) {
                $employeeData = $employee->toArray();
                $employeeData['bank_name'] = $employee->bank ? $employee->bank->bank_name : null;
                $employeeData['emptype_name'] = $employee->employmentType ? $employee->employmentType->emptype_name : null;
                $employeeData['scheme_name'] = $employee->scheme ? $employee->scheme->scheme_name : null;
                // Include all role names from active roles as comma-separated string
                $roleNames = $employee->roles ? $employee->roles->pluck('role_name')->toArray() : [];
                $employeeData['role_name'] = !empty($roleNames) ? implode(', ', $roleNames) : null;
                return $employeeData;
            });

            return $this->sendResponse([
                'employees' => $transformedEmployees,
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                ]
            ], 'Bridge employees retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge employees: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve bridge employees: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Register a new bridge employee.
     * Optionally creates auth_user and bridge_employment_details records if provided.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerBridgeEmployee(Request $request)
    {
        try {
            Log::info('Bridge employee registration request received', [
                'request_data' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Determine if we need to create auth_user (check for auth_user specific fields)
            $createAuthUser = $request->filled('first_name') && $request->filled('surname') && $request->filled('phone');
            // Create employment details if officeid is provided OR if other employment detail fields are provided
            $createEmployeeDetails = $request->filled('officeid') || $request->filled('contract_type') || $request->filled('empdate') || $request->filled('positionid');

            // Build validation rules dynamically
            $rules = [
                // Bridge employee required fields
                'fname' => 'required|string|max:100',
                'sname' => 'required|string|max:100',
                'national_id' => 'required|string|max:50|unique:bcmis2.bridge_employee,national_id',
                // Bridge employee optional fields
                'mname' => 'nullable|string|max:100',
                'pfno' => 'nullable|string|max:50',
                'gender' => 'nullable|string|max:20',
                'title' => 'nullable|string|max:50',
                'dob' => 'nullable|date',
                'maritalstatus' => 'nullable|string|max:20',
                'domicile' => 'nullable|int|max:100',
                'employment_place' => 'nullable|string|max:200',
                'nationality' => 'nullable|string|max:50',
                'passport_no' => 'nullable|string|max:50',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'educational_level_id' => 'nullable|integer|exists:bcmis2.educational_levels,id',
                'scheme_id' => 'nullable|integer',
                'emptype_id' => 'nullable|integer',
                'bank_id' => 'nullable|integer',
                'account_no' => 'nullable|string|max:50',
                'employee_status' => 'nullable|string|max:50',
                'district_id' => 'nullable|integer',
                'department_id' => 'nullable|integer|exists:bcmis2.departments,department_id',
                'ext_number' => 'nullable|string|max:20',
                'ssn' => 'nullable|string|max:50',
                'basicsalary' => 'nullable|numeric',
                'username' => 'nullable|string|max:50|unique:bcmis2.bridge_employee,username',
                'pwd' => 'nullable|string|max:255',
                'tin' => 'nullable|string|max:50',
                'current_qualification' => 'nullable|string|max:200',
                'last_promotion_date' => 'nullable|date',
                'promotion_due_date' => 'nullable|date',
                'health_insurance_id' => 'nullable|integer',
            ];

            // Add auth_user validation rules if creating auth_user
            if ($createAuthUser) {
                $rules = array_merge($rules, [
                    'first_name' => 'required|string|max:50',
                    'surname' => 'required|string|max:50',
                    'email' => 'nullable|email|max:100|unique:auth_user,email',
                    'phone' => 'required|string|max:50|unique:auth_user,phone',
                    'nida' => 'nullable|string|max:50',
                    'middle_name' => 'nullable|string|max:50',
                ]);
            }

            // Add bridge_employment_details validation rules if creating details
            if ($createEmployeeDetails) {
                $rules = array_merge($rules, [
                    'contract_type' => 'nullable|string|max:50',
                    'empdate' => 'nullable|date',
                    'enddate' => 'nullable|date',
                    'du_id' => 'nullable|integer',
                    'positionid' => 'nullable|integer',
                    'salscaleid' => 'nullable|integer',
                    'curbasicsal' => 'nullable|numeric',
                    'salrangeid' => 'nullable|integer',
                    'gradeid' => 'nullable|integer',
                    'report_tono1' => 'nullable|integer',
                    'jobtitleid' => 'nullable|integer',
                    'lpromodate' => 'nullable|date',
                    'confirmed' => 'nullable|string|max:20',
                    'dateconfirmed' => 'nullable|date',
                    'description' => 'nullable|string',
                    'officeid' => 'nullable|integer|exists:bcmis2.bridge_office,id',
                    'prob_enddate' => 'nullable|date',
                    'prob_descr' => 'nullable|string',
                    'report_tono2' => 'nullable|integer',
                    'transfer_date' => 'nullable|date',
                    'confirmedby' => 'nullable|string|max:50',
                    'confirmedbydate' => 'nullable|date',
                    'department_section' => 'nullable|string|max:200',
                    'date_of_first_appointment' => 'nullable|date',
                    'date_of_current_appointment' => 'nullable|date',
                ]);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                Log::error('Validation failed for bridge employee registration', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Validate PF number based on office configuration
            $office = null;
            if ($request->filled('officeid')) {
                $office = BridgeOffice::find($request->officeid);
                
                if (!$office) {
                    return $this->sendError('Invalid office selected', [], 0, 422);
                }

                if (!$office->auto_generate_pf) {
                    // Office requires manual PF entry
                    if (!$request->filled('pfno') || trim($request->pfno) === '') {
                        return $this->sendError('PF Number is required for ' . $office->office_name . ' office. This office does not auto-generate PF numbers.', [
                            'pfno' => ['PF Number is required for ' . $office->office_name . ' office. This office does not auto-generate PF numbers.']
                        ], 0, 422);
                    }

                    // Check for duplicate PFNO if provided
                    $existingEmployee = BridgeEmployee::byPfno($request->pfno)->first();
                    if ($existingEmployee) {
                        Log::warning('Duplicate PFNO detected', [
                            'pfno' => $request->pfno,
                            'existing_national_id' => $existingEmployee->national_id
                        ]);
                        return $this->sendError('Employee with this PFNO already exists', [], 0, 409);
                    }
                } else {
                    // Office auto-generates PF - clear any provided PF to avoid confusion
                    if ($request->filled('pfno')) {
                        Log::info('PF number provided for auto-generate office, will be ignored', [
                            'office_id' => $office->id,
                            'office_name' => $office->office_name,
                            'provided_pfno' => $request->pfno
                        ]);
                    }
                }
            }

            // Note: PF duplicate check for auto-generate offices is handled above
            // Only check for duplicates if office is not set or office allows manual entry
            if ($request->filled('pfno') && (!$office || !$office->auto_generate_pf)) {
                $existingEmployee = BridgeEmployee::byPfno($request->pfno)->first();
                if ($existingEmployee) {
                    Log::warning('Duplicate PFNO detected', [
                        'pfno' => $request->pfno,
                        'existing_national_id' => $existingEmployee->national_id
                    ]);
                    return $this->sendError('Employee with this PFNO already exists', [], 0, 409);
                }
            }

            // Check for duplicate email if provided
            if ($request->filled('email')) {
                $existingEmployee = BridgeEmployee::byEmail($request->email)->first();
                if ($existingEmployee) {
                    Log::warning('Duplicate email detected', [
                        'email' => $request->email,
                        'existing_national_id' => $existingEmployee->national_id
                    ]);
                    return $this->sendError('Employee with this email already exists', [], 0, 409);
                }
            }

            // Check for duplicate mobile if provided
            if ($request->filled('mobile')) {
                $existingEmployee = BridgeEmployee::byMobile($request->mobile)->first();
                if ($existingEmployee) {
                    Log::warning('Duplicate mobile detected', [
                        'mobile' => $request->mobile,
                        'existing_national_id' => $existingEmployee->national_id
                    ]);
                    return $this->sendError('Employee with this mobile number already exists', [], 0, 409);
                }
            }

            DB::beginTransaction();

            $authUser = null;
            $employeeDetailsId = null;

            // Step 1: Create auth_user record if needed
            if ($createAuthUser) {
                $authUser = new AuthUser();
                $authUser->first_name = $request->input('first_name');
                $authUser->middle_name = $request->input('middle_name');
                $authUser->surname = $request->input('surname');
                $authUser->email = $request->input('email');
                $authUser->phone = $request->input('phone');
                $authUser->nida = $request->input('nida') ?? $request->input('national_id');
                $authUser->username = $request->input('first_name') . '.' . $request->input('surname');
                $authUser->status = 1;
                $authUser->password_hash = Hash::make($request->input('phone'));
                $authUser->must_change_password = true;

                if (Auth::check()) {
                    $authUser->created_by = (string) Auth::id();
                }

                if (!$authUser->save()) {
                    DB::rollBack();
                    return $this->sendError('Failed to save user to auth_user table', [], 0, 500);
                }

                Log::info('Auth user created successfully', [
                    'auth_user_id' => $authUser->id,
                    'username' => $authUser->username
                ]);
            }

            // Step 2: Prepare bridge employee data
            // Use auth_user data if available, otherwise use request data
            $fname = $createAuthUser && $authUser ? $authUser->first_name : $request->fname;
            $mname = $createAuthUser && $authUser ? $authUser->middle_name : $request->mname;
            $sname = $createAuthUser && $authUser ? $authUser->surname : $request->sname;
            $mobile = $createAuthUser && $authUser ? $authUser->phone : $request->mobile;
            $email = $createAuthUser && $authUser ? $authUser->email : $request->email;
            $username = $createAuthUser && $authUser ? $authUser->username : $request->username;
            $nationalId = $request->national_id ?? ($createAuthUser && $authUser ? $authUser->nida : null);

            if (empty($nationalId)) {
                DB::rollBack();
                return $this->sendError('NATIONAL_ID is required. Please provide NATIONAL_ID in the request.', [], 0, 422);
            }

            // NOTE: PF number handling based on office configuration
            // - Auto-generate offices: pfno will be null (generated on approval)
            // - Manual-entry offices: pfno must be provided (validated above)
            $pfno = null;
            if ($office && !$office->auto_generate_pf) {
                // Manual-entry office: use provided PF
                $pfno = $request->pfno;
            } elseif (!$office && $request->filled('pfno')) {
                // No office specified but PF provided: allow it (backward compatibility)
                $pfno = $request->pfno;
            }
            // For auto-generate offices, pfno remains null

            $employeeData = [
                'fname' => $fname,
                'mname' => $mname,
                'sname' => $sname,
                'national_id' => $nationalId,
                'pfno' => $pfno, // Will be null for new employees until approved
                'gender' => $request->gender,
                'title' => $request->title,
                'dob' => $request->dob,
                'maritalstatus' => $request->maritalstatus,
                'domicile' => $request->domicile,
                'employment_place' => $request->employment_place,
                'nationality' => $request->nationality,
                'passport_no' => $request->passport_no,
                'mobile' => $mobile,
                'email' => $email,
                'educational_level_id' => $request->educational_level_id,
                'scheme_id' => $request->scheme_id,
                'emptype_id' => $request->emptype_id,
                'bank_id' => $request->bank_id,
                'account_no' => $request->account_no,
                'district_id' => $request->district_id,
                'department_id' => $request->department_id,
                'ext_number' => $request->ext_number,
                'ssn' => $request->ssn,
                'basicsalary' => $request->basicsalary,
                'username' => $username,
                'tin' => $request->tin,
                'current_qualification' => $request->current_qualification,
                'last_promotion_date' => $request->last_promotion_date,
                'promotion_due_date' => $request->promotion_due_date,
                'health_insurance_id' => $request->health_insurance_id,
                'cdate' => now(),
                'lcount' => 0,
                'is_first_time' => true,
                // account_status will be set to 'Active' only after approval
                // Do not set it here as employee is pending approval
            ];

            // Set password if provided, otherwise use default
            if ($request->filled('pwd')) {
                $employeeData['pwd'] = Hash::make($request->pwd);
            } else {
                // Default password: use mobile number
                $defaultPassword = $mobile ?? '12345';
                $employeeData['pwd'] = Hash::make($defaultPassword);
            }

            // Set created by if authenticated
            if (Auth::check()) {
                $employeeData['cby'] = Auth::id();
            } elseif ($request->filled('created_by')) {
                $employeeData['cby'] = $request->created_by;
            }

            // Create the bridge employee (status will be set by referral approval service)
            $employee = BridgeEmployee::create($employeeData);

            Log::info('Bridge employee created', [
                'national_id' => $employee->national_id,
                'pfno' => $employee->pfno,
                'username' => $employee->username
            ]);

            // Automatically assign "employee role" to the newly created user
            try {
                $employeeRole = Role::where('role_name', 'employee')
                    ->where('is_active', true)
                    ->first();

                if ($employeeRole) {
                    // Create role assignment
                    $roleAssignmentData = [
                        'national_id' => $employee->national_id,
                        'role_id' => $employeeRole->id,
                        'is_active' => true,
                        'description' => 'Default employee role assigned during registration',
                        'created_at' => now(),
                        'modified_at' => now(),
                    ];

                    if (Auth::check()) {
                        $roleAssignmentData['created_by'] = (string) Auth::id();
                        $roleAssignmentData['modified_by'] = (string) Auth::id();
                    }

                    BridgeEmployeeRole::create($roleAssignmentData);

                    Log::info('Default employee role assigned during registration', [
                        'national_id' => $employee->national_id,
                        'role_id' => $employeeRole->id,
                        'role_name' => $employeeRole->role_name
                    ]);
                } else {
                    Log::warning('Employee role not found or inactive, skipping default role assignment', [
                        'national_id' => $employee->national_id
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the registration if role assignment fails
                Log::error('Failed to assign default employee role during registration: ' . $e->getMessage(), [
                    'national_id' => $employee->national_id,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Submit for approval using referral approval service
            $result = $this->approvalService->submitEmployeeCreation(
                $employee,
                $request->input('approval_description', 'Employee creation submitted for approval')
            );

            // Step 3: Create bridge_employment_details record if needed
            if ($createEmployeeDetails) {
                $employeeDetailsData = [
                    'national_id' => $employee->national_id,
                    'emptype_id' => $request->input('emptype_id'),
                    'employment_place' => $request->input('employment_place'),
                    'contract_type' => $request->input('contract_type'),
                    'empdate' => $request->input('empdate'),
                    'enddate' => $request->input('enddate'),
                    'du_id' => $request->input('du_id'),
                    'positionid' => $request->input('positionid'),
                    'salscaleid' => $request->input('salscaleid'),
                    'curbasicsal' => $request->input('curbasicsal'),
                    'salrangeid' => $request->input('salrangeid'),
                    'gradeid' => $request->input('gradeid'),
                    'report_tono1' => $request->input('report_tono1'),
                    'jobtitleid' => $request->input('jobtitleid'),
                    'lpromodate' => $request->input('lpromodate'),
                    'confirmed' => $request->input('confirmed'),
                    'dateconfirmed' => $request->input('dateconfirmed'),
                    'description' => $request->input('description'),
                    'officeid' => $request->input('officeid'),
                    'prob_enddate' => $request->input('prob_enddate'),
                    'prob_descr' => $request->input('prob_descr'),
                    'report_tono2' => $request->input('report_tono2'),
                    'transfer_date' => $request->input('transfer_date'),
                    'confirmedby' => $request->input('confirmedby'),
                    'confirmedbydate' => $request->input('confirmedbydate'),
                    'department_section' => $request->input('department_section'),
                    'date_of_first_appointment' => $request->input('date_of_first_appointment'),
                    'date_of_current_appointment' => $request->input('date_of_current_appointment'),
                    'cdate' => now(),
                ];

                // Set created by if authenticated
                if (Auth::check()) {
                    $employeeDetailsData['cby'] = (string) Auth::id();
                } elseif ($request->filled('created_by')) {
                    $employeeDetailsData['cby'] = (string) $request->created_by;
                }

                // Insert into bridge_employment_details using DB facade (bcmis2 connection)
                $employeeDetailsId = DB::connection('bcmis2')->table('bridge_employment_details')->insertGetId($employeeDetailsData);

                Log::info('Bridge employee details created successfully', [
                    'employee_details_id' => $employeeDetailsId,
                    'national_id' => $employee->national_id
                ]);
            }

            DB::commit();

            // Load bank and department relationships
            $employee->load(['bank', 'department']);

            // Build response based on what was created
            $employeeData = $employee->toArray();
            $employeeData['bank_name'] = $employee->bank ? $employee->bank->bank_name : null;
            $employeeData['department_name'] = $employee->department ? $employee->department->department_name : null;

            $response = [
                'employee' => $employeeData,
                'national_id' => $employee->national_id,
            ];

            if ($authUser) {
                $response['auth_user'] = [
                    'id' => $authUser->id,
                    'username' => $authUser->username,
                    'email' => $authUser->email,
                    'phone' => $authUser->phone,
                ];
            }

            if ($employeeDetailsId) {
                $response['employee_details_id'] = $employeeDetailsId;
            }

            $message = 'Bridge employee registered successfully and submitted for approval';
            if ($authUser && $employeeDetailsId) {
                $message = 'User successfully registered in all tables and submitted for approval';
            } elseif ($authUser) {
                $message = 'User and bridge employee registered successfully and submitted for approval';
            } elseif ($employeeDetailsId) {
                $message = 'Bridge employee and details registered successfully and submitted for approval';
            }

            // Add referral request and approval status to response
            $response['referral_request'] = [
                'id' => $result['referral_request']->id,
                'module_code' => $result['referral_request']->module_code,
                'action_type' => $result['referral_request']->action_type,
                'status' => $result['referral_request']->status,
                'created_at' => $result['referral_request']->created_at,
            ];
            $response['approval_status'] = [
                'status_id' => $result['employee_status']->id,
                'status' => $result['employee_status']->employee_status,
                'message' => 'Awaiting approval',
            ];

            Log::info('Registration completed successfully', [
                'national_id' => $employee->national_id,
                'created_auth_user' => $createAuthUser,
                'created_employee_details' => $createEmployeeDetails
            ]);

            return $this->sendResponse($response, $message, 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Database error during bridge employee registration: ' . $e->getMessage(), [
                'error_code' => $e->getCode(),
                'request_data' => $request->all()
            ]);

            if ($e->getCode() == 23000) {
                return $this->sendError('Integrity constraint violation. Employee may already exist.', [], 0, 409);
            }

            return $this->sendError('Database error occurred: ' . $e->getMessage(), [], 0, 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error registering bridge employee: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Failed to register bridge employee: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($nationalId)
    {
        try {

            // Validate that NATIONAL_ID is provided
            if (empty($nationalId) || $nationalId === 'undefined' || $nationalId === 'null') {
                return $this->sendError('Invalid National ID provided. Please provide a valid National ID.', [], 0, 400);
            }

            $employee = BridgeEmployee::with(['bank', 'department'])->find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Add bank name and department name to response
            $employeeData = $employee->toArray();
            $employeeData['bank_name'] = $employee->bank ? $employee->bank->bank_name : null;
            $employeeData['department_name'] = $employee->department ? $employee->department->department_name : null;

            return $this->sendResponse($employeeData, 'Bridge employee retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge employee: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve bridge employee: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * Now submits changes for approval instead of updating directly.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $nationalId)
    {
        try {
            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Check if employee has any pending approval requests
            $pendingStatus = \App\Models\Bms\BridgeEmployeeStatus::byNationalId($nationalId)
                ->pendingApproval()
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($pendingStatus) {
                $statusMessage = match($pendingStatus->employee_status) {
                    EmployeeStatus::PENDING_APPROVAL => 'Employee creation is pending approval',
                    EmployeeStatus::PENDING_UPDATE => 'Employee has a pending update request',
                    EmployeeStatus::PENDING_TERMINATION => 'Employee has a pending termination request',
                    EmployeeStatus::PENDING_DELETION => 'Employee has a pending deletion request',
                    default => 'Employee has a pending approval request'
                };
                return $this->sendError($statusMessage . '. Please wait for approval or rejection.', [], 0, 409);
            }

            $validator = Validator::make($request->all(), [
                'fname' => 'sometimes|required|string|max:100',
                'mname' => 'nullable|string|max:100',
                'sname' => 'sometimes|required|string|max:100',
                'pfno' => 'nullable|string|max:50',
                'gender' => 'nullable|string|max:20',
                'title' => 'nullable|string|max:50',
                'dob' => 'nullable|date',
                'maritalstatus' => 'nullable|string|max:20',
                'domicile' => 'nullable|string|max:100',
                'employment_place' => 'nullable|string|max:200',
                'nationality' => 'nullable|string|max:50',
                'passport_no' => 'nullable|string|max:50',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'scheme_id' => 'nullable|integer',
                'emptype_id' => 'nullable|integer',
                'bank_id' => 'nullable|integer',
                'account_no' => 'nullable|string|max:50',
                'employee_status' => 'nullable|string|max:50',
                'district_id' => 'nullable|integer',
                'department_id' => 'nullable|integer|exists:bcmis2.departments,department_id',
                'ext_number' => 'nullable|string|max:20',
                'ssn' => 'nullable|string|max:50',
                'basicsalary' => 'nullable|numeric',
                'username' => 'nullable|string|max:50|unique:bcmis2.bridge_employee,username,' . $nationalId . ',national_id',
                'pwd' => 'nullable|string|max:255',
                'tin' => 'nullable|string|max:50',
                'current_qualification' => 'nullable|string|max:200',
                'last_promotion_date' => 'nullable|date',
                'promotion_due_date' => 'nullable|date',
                'health_insurance_id' => 'nullable|integer',
                'approval_description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Get only the fields that are being updated (non-null values)
            $proposedChanges = array_filter($request->only([
                'fname', 'mname', 'sname', 'pfno', 'gender', 'title',
                'dob', 'maritalstatus', 'domicile', 'employment_place', 'nationality',
                'passport_no', 'mobile', 'email', 'scheme_id', 'emptype_id', 'bank_id',
                'account_no', 'employee_status', 'district_id', 'department_id', 'ext_number', 'ssn',
                'basicsalary', 'username', 'pwd', 'tin', 'current_qualification',
                'last_promotion_date', 'promotion_due_date', 'health_insurance_id'
            ]), function ($value) {
                return $value !== null;
            });

            // Check if there are any changes
            if (empty($proposedChanges)) {
                return $this->sendError('No changes provided to update', [], 0, 422);
            }

            // Check for duplicate email if email is being changed
            if (isset($proposedChanges['email']) && $proposedChanges['email'] !== $employee->email) {
                $existingEmployee = BridgeEmployee::byEmail($proposedChanges['email'])->first();
                if ($existingEmployee && $existingEmployee->national_id !== $nationalId) {
                    return $this->sendError('Employee with this email already exists', [], 0, 409);
                }
            }

            // Check for duplicate mobile if mobile is being changed
            if (isset($proposedChanges['mobile']) && $proposedChanges['mobile'] !== $employee->mobile) {
                $existingEmployee = BridgeEmployee::byMobile($proposedChanges['mobile'])->first();
                if ($existingEmployee && $existingEmployee->national_id !== $nationalId) {
                    return $this->sendError('Employee with this mobile number already exists', [], 0, 409);
                }
            }

            // Check for duplicate PFNO if PFNO is being changed
            if (isset($proposedChanges['pfno']) && $proposedChanges['pfno'] !== $employee->pfno) {
                $existingEmployee = BridgeEmployee::byPfno($proposedChanges['pfno'])->first();
                if ($existingEmployee && $existingEmployee->national_id !== $nationalId) {
                    return $this->sendError('Employee with this PFNO already exists', [], 0, 409);
                }
            }

            // Submit for approval using referral approval service
            $result = $this->approvalService->submitEmployeeUpdate(
                $nationalId,
                $proposedChanges,
                $request->input('approval_description', 'Employee information update submitted for approval')
            );

            Log::info('Bridge employee update submitted for approval', [
                'national_id' => $employee->national_id,
                'referral_request_id' => $result['referral_request']->id,
                'employee_status_id' => $result['employee_status']->id,
                'changes_count' => count($proposedChanges),
                'submitted_by' => Auth::id()
            ]);

            return $this->sendResponse([
                'referral_request' => [
                    'id' => $result['referral_request']->id,
                    'module_code' => $result['referral_request']->module_code,
                    'action_type' => $result['referral_request']->action_type,
                    'status' => $result['referral_request']->status,
                    'created_at' => $result['referral_request']->created_at,
                ],
                'employee_status' => [
                    'status_id' => $result['employee_status']->id,
                    'status' => $result['employee_status']->employee_status,
                ],
                'proposed_changes' => $proposedChanges,
                'message' => 'Update request submitted for approval',
            ], 'Employee update submitted for approval successfully');

        } catch (\Exception $e) {
            Log::error('Error submitting employee update for approval: ' . $e->getMessage());
            return $this->sendError('Failed to submit update for approval: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Submit employee termination for approval
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitTermination(Request $request, $nationalId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason_id' => 'nullable|integer',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Check if employee has any pending approval requests
            $pendingStatus = \App\Models\Bms\BridgeEmployeeStatus::byNationalId($nationalId)
                ->pendingApproval()
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($pendingStatus) {
                $statusMessage = match($pendingStatus->employee_status) {
                    EmployeeStatus::PENDING_APPROVAL => 'Employee creation is pending approval',
                    EmployeeStatus::PENDING_UPDATE => 'Employee has a pending update request',
                    EmployeeStatus::PENDING_TERMINATION => 'Termination request already pending approval',
                    EmployeeStatus::PENDING_DELETION => 'Employee has a pending deletion request',
                    default => 'Employee has a pending approval request'
                };
                return $this->sendError($statusMessage . '. Please wait for approval or rejection.', [], 0, 409);
            }

            if (EmployeeStatus::isTerminated($employee->employee_status)) {
                return $this->sendError('Employee is already terminated', [], 0, 409);
            }

            $result = $this->approvalService->submitEmployeeTermination(
                $nationalId,
                $request->input('description', 'Employee termination submitted for approval')
            );

            return $this->sendResponse([
                'referral_request' => [
                    'id' => $result['referral_request']->id,
                    'module_code' => $result['referral_request']->module_code,
                    'action_type' => $result['referral_request']->action_type,
                    'status' => $result['referral_request']->status,
                    'created_at' => $result['referral_request']->created_at,
                ],
                'employee_status' => [
                    'status_id' => $result['employee_status']->id,
                    'status' => $result['employee_status']->employee_status,
                ],
                'message' => 'Termination request submitted for approval',
            ], 'Termination request submitted successfully');

        } catch (\Exception $e) {
            Log::error('Error submitting termination request: ' . $e->getMessage());
            return $this->sendError('Failed to submit termination request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Submit employee deletion for approval
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitDeletion(Request $request, $nationalId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason_id' => 'nullable|integer',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Check if employee has any pending approval requests
            $pendingStatus = \App\Models\Bms\BridgeEmployeeStatus::byNationalId($nationalId)
                ->pendingApproval()
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($pendingStatus) {
                $statusMessage = match($pendingStatus->employee_status) {
                    EmployeeStatus::PENDING_APPROVAL => 'Employee creation is pending approval',
                    EmployeeStatus::PENDING_UPDATE => 'Employee has a pending update request',
                    EmployeeStatus::PENDING_TERMINATION => 'Employee has a pending termination request',
                    EmployeeStatus::PENDING_DELETION => 'Deletion request already pending approval',
                    default => 'Employee has a pending approval request'
                };
                return $this->sendError($statusMessage . '. Please wait for approval or rejection.', [], 0, 409);
            }

            $result = $this->approvalService->submitEmployeeDeletion(
                $nationalId,
                $request->input('description', 'Employee deletion submitted for approval')
            );

            return $this->sendResponse([
                'referral_request' => [
                    'id' => $result['referral_request']->id,
                    'module_code' => $result['referral_request']->module_code,
                    'action_type' => $result['referral_request']->action_type,
                    'status' => $result['referral_request']->status,
                    'created_at' => $result['referral_request']->created_at,
                ],
                'employee_status' => [
                    'status_id' => $result['employee_status']->id,
                    'status' => $result['employee_status']->employee_status,
                ],
                'message' => 'Deletion request submitted for approval',
            ], 'Deletion request submitted successfully');

        } catch (\Exception $e) {
            Log::error('Error submitting deletion request: ' . $e->getMessage());
            return $this->sendError('Failed to submit deletion request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * NOTE: This method is kept for backward compatibility but should use submitDeletion instead
     *
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($nationalId)
    {
        try {
            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Use maker-checker workflow
            $result = $this->approvalService->submitEmployeeDeletion(
                $nationalId,
                'Deletion requested via destroy endpoint'
            );

            return $this->sendResponse([
                'referral_request' => [
                    'id' => $result['referral_request']->id,
                    'module_code' => $result['referral_request']->module_code,
                    'action_type' => $result['referral_request']->action_type,
                    'status' => $result['referral_request']->status,
                    'created_at' => $result['referral_request']->created_at,
                ],
                'employee_status' => [
                    'status_id' => $result['employee_status']->id,
                    'status' => $result['employee_status']->employee_status,
                ],
                'message' => 'Deletion request submitted for approval',
            ], 'Deletion request submitted successfully. Awaiting approval.');

        } catch (\Exception $e) {
            Log::error('Error submitting deletion request: ' . $e->getMessage());
            return $this->sendError('Failed to submit deletion request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Search for employees by various criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pfno' => 'nullable|string',
                'national_id' => 'nullable|string',
                'username' => 'nullable|string',
                'email' => 'nullable|email',
                'mobile' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $query = BridgeEmployee::with(['bank', 'department']);

            if ($request->filled('pfno')) {
                $query->byPfno($request->pfno);
            }

            if ($request->filled('national_id')) {
                $query->byNationalId($request->national_id);
            }

            if ($request->filled('username')) {
                $query->byUsername($request->username);
            }

            if ($request->filled('email')) {
                $query->byEmail($request->email);
            }

            if ($request->filled('mobile')) {
                $query->byMobile($request->mobile);
            }

            $employees = $query->get();

            // Transform employees to include bank name and department name
            $transformedEmployees = $employees->map(function ($employee) {
                $employeeData = $employee->toArray();
                $employeeData['bank_name'] = $employee->bank ? $employee->bank->bank_name : null;
                $employeeData['department_name'] = $employee->department ? $employee->department->department_name : null;
                return $employeeData;
            });

            return $this->sendResponse($transformedEmployees, 'Search completed successfully');

        } catch (\Exception $e) {
            Log::error('Error searching bridge employees: ' . $e->getMessage());
            return $this->sendError('Failed to search bridge employees: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Generate a unique PFNO with pattern NB0001, NB0002, etc.
     *
     * @return string
     */
    private function generatePFNO(): string
    {
        $prefix = 'NB';
        $maxAttempts = 100;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // Get all existing PFNOs that start with 'NB' and extract their numeric parts
            $existingPFNOs = BridgeEmployee::whereNotNull('pfno')
                ->where('pfno', 'like', $prefix . '%')
                ->pluck('pfno')
                ->map(function ($pfno) use ($prefix) {
                    // Extract numeric part after prefix
                    $numericPart = substr($pfno, strlen($prefix));
                    // Only return if it's a valid number
                    return is_numeric($numericPart) ? (int) $numericPart : null;
                })
                ->filter()
                ->toArray();

            // Also check PFNO2 field
            $existingPFNO2s = BridgeEmployee::whereNotNull('pfno2')
                ->where('pfno2', 'like', $prefix . '%')
                ->pluck('pfno2')
                ->map(function ($pfno) use ($prefix) {
                    $numericPart = substr($pfno, strlen($prefix));
                    return is_numeric($numericPart) ? (int) $numericPart : null;
                })
                ->filter()
                ->toArray();

            // Merge and get the maximum number
            $allNumbers = array_merge($existingPFNOs, $existingPFNO2s);
            $nextNumber = !empty($allNumbers) ? max($allNumbers) + 1 : 1;

            // Format as NB + 4-digit zero-padded number
            $newPFNO = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Check if this PFNO already exists (in case of race condition)
            $exists = BridgeEmployee::where('pfno', $newPFNO)
                ->orWhere('pfno2', $newPFNO)
                ->exists();

            if (!$exists) {
                Log::info('Generated unique PFNO', [
                    'pfno' => $newPFNO,
                    'next_number' => $nextNumber,
                    'attempt' => $attempts + 1
                ]);
                return $newPFNO;
            }

            $attempts++;
            Log::warning('PFNO collision detected, retrying', [
                'pfno' => $newPFNO,
                'attempt' => $attempts
            ]);
        }

        // Fallback: use timestamp-based PFNO if max attempts reached
        $fallbackPFNO = $prefix . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
        Log::warning('Max attempts reached for PFNO generation, using fallback', [
            'fallback_pfno' => $fallbackPFNO
        ]);
        return $fallbackPFNO;
    }

    /**
     * Get all active roles assigned to an employee
     *
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployeeRoles($nationalId)
    {
        try {
            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Get all active role assignments for this employee
            $activeRoleAssignments = BridgeEmployeeRole::byNationalId($nationalId)
                ->active()
                ->with('role')
                ->orderBy('created_at', 'desc')
                ->get();

            // Map the role assignments to response format
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
                'national_id' => $nationalId,
                'roles' => $roles,
                'total_roles' => $roles->count(),
            ], 'Employee roles retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error retrieving employee roles: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve employee roles: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle the status of a bridge employee role assignment
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nationalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleRoleStatus(Request $request, $nationalId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_role_id' => 'required|integer|exists:bcmis2.bridge_employee_role,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $employee = BridgeEmployee::find($nationalId);

            if (!$employee) {
                return $this->sendError('Bridge employee not found', [], 0, 404);
            }

            // Find the employee role assignment
            $employeeRole = BridgeEmployeeRole::find($request->employee_role_id);

            if (!$employeeRole) {
                return $this->sendError('Employee role assignment not found', [], 0, 404);
            }

            // Verify that the role assignment belongs to this employee
            if ($employeeRole->national_id !== $nationalId) {
                return $this->sendError('Employee role assignment does not belong to this employee', [], 0, 403);
            }

            DB::beginTransaction();

            // Toggle the is_active status
            $employeeRole->is_active = !$employeeRole->is_active;
            $employeeRole->modified_at = now();

            // If deactivating, set revoked_at and revoked_by
            if (!$employeeRole->is_active) {
                $employeeRole->revoked_at = now();
                if (Auth::check()) {
                    $employeeRole->revoked_by = (string) Auth::id();
                }
            } else {
                // If reactivating, clear revoked fields
                $employeeRole->revoked_at = null;
                $employeeRole->revoked_by = null;
            }

            if (Auth::check()) {
                $employeeRole->modified_by = (string) Auth::id();
            }

            $employeeRole->save();

            DB::commit();

            // Load the role relationship
            $employeeRole->load('role');

            Log::info('Bridge employee role status toggled', [
                'national_id' => $nationalId,
                'employee_role_id' => $employeeRole->id,
                'new_status' => $employeeRole->is_active ? 'active' : 'inactive'
            ]);

            return $this->sendResponse([
                'employee_role_id' => $employeeRole->id,
                'national_id' => $nationalId,
                'role' => $employeeRole->role ? [
                    'id' => $employeeRole->role->id,
                    'role_name' => $employeeRole->role->role_name,
                    'role_description' => $employeeRole->role->role_description,
                ] : null,
                'is_active' => $employeeRole->is_active,
                'status' => $employeeRole->is_active ? 'active' : 'inactive',
                'revoked_at' => $employeeRole->revoked_at,
                'modified_at' => $employeeRole->modified_at,
                'message' => 'Employee role status toggled successfully',
            ], 'Employee role status toggled successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error toggling employee role status: ' . $e->getMessage());
            return $this->sendError('Failed to toggle employee role status: ' . $e->getMessage(), [], 0, 500);
        }
    }

}

