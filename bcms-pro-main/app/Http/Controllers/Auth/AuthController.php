<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use App\Models\AuthUserRole;
use App\Models\Notifications\Notifications;
use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends ConfigurationController
{
    public const STATUS_INVALID_USERNAME = 'AUTH_001';
    public const STATUS_INVALID_PASSWORD = 'AUTH_002';
    public const STATUS_INACTIVE_USER = 'AUTH_003';

    public function mobileAuthentication(Request $request)
    {
        $userData = User::where('phone_number', $request->phone_number)->first();

        #check if OTP is available and valid
        if ($request->otp != null) {
            if ($userData==null){

                return $this->sendError('OTP is invalid or has expired');
            }

            if (Auth::attempt(['email' => $userData->email, 'password' => $userData->phone_number])) {
                $success['username']=$userData->name;
                $authUser = Auth::user();
                $otp = $request->otp;
                $otpCode = DB::table('otp_codes')->where('user_id', $userData->id)
                    ->where('code', $otp)
                    ->where('expires_at', '>', Carbon::now())
                    ->first();
                if ($otpCode) {
                    $success['token'] = $authUser->createToken($authUser->id)->plainTextToken;

                    # OTP is valid, delete it from the database
                    DB::table('otp_codes')->where('id', $otpCode->id)->delete();
                    return $this->sendResponse($success, 'OTP Verified successfully');
                }else {
                    # OTP is invalid or has expired
                    return $this->sendError('OTP is invalid or has expired');
                }
            }
            return $this->sendError('Invalid user or Phone number is not associated to any account');
        }
        $phone = $request->phone_number;

        #receive phone number and validate if user is valid
        $account_details = DB::table('account')
            ->where('phone', '=', "$phone")
            ->select('phone', 'account_no', 'account_balance', 'first_name', 'middle_name', 'surname', 'phone', 'email')
            ->get();

        if ($account_details->count() > 0) {

            #check if user is available
            $user = User::where('phone_number', $account_details[0]->phone)->first();

            if ($user == null) {
                $input = [
                    'name' => $account_details[0]->first_name . ' ' . $account_details[0]->middle_name . ' ' . $account_details[0]->surname,
                    'email' => $account_details[0]->email,
                    'password' => bcrypt($account_details[0]->phone),
                    'email_verified_at' => NOW(),
                    'phone_number' => $account_details[0]->phone
                ];

                $user = User::create($input);

                $success['name'] = $user->name;
                $success['phone_number'] = $user->phone_number;
                $success['email'] = $user->email;


                $otp = mt_rand(100000, 999999);
                $expiresAt = Carbon::now()->addMinutes(5);
                DB::table('otp_codes')->insert([
                    'user_id' => $user->id,
                    'code' => $otp,
                    'expires_at' => $expiresAt,
                    'created_at' => Carbon::now()
                ]);
                $success['otp'] = $otp;
                #send OTP to the phone
                $sms_body =
                    'OTP: ' . $success['otp'] . "\n";
                $payer_cell = $success['phone_number'];
                Notifications::pushSmsNotification($payer_cell, $sms_body, "Bridge_OTP");

                return $this->sendResponse($success, 'OTP generated successfully');
            } else {
                if (Auth::attempt(['email' => $user->email, 'password' => $user->phone_number])) {
                    $success['name'] = $user->name;
                    $success['phone_number'] = $user->phone_number;
                    $success['email'] = $user->email;

                    #check if OTP has Expired
                    $otp = OtpCode::where('user_id', $user->id)
                        ->where('expires_at', '<', now())
                        ->first();

                    if ($otp) {
                        $newOtp = mt_rand(100000, 999999);
                        $otp->code = $newOtp;
                        $otp->expires_at = now()->addMinutes(5); // Set a new expiry time for the OTP
                        $otp->save();
                        $success['otp'] = $newOtp;
                    } else {
                        $otp = OtpCode::where('user_id', $user->id)
                            ->first();
                        if ($otp==null){
                            $newOtpCode=new OtpCode();
                            $newOtp = mt_rand(100000, 999999);
                            $newOtpCode->user_id = $user->id;
                            $newOtpCode->code = $newOtp;
                            $newOtpCode->expires_at = now()->addMinutes(5); // Set a new expiry time for the OTP
                            $newOtpCode->save();
                            $success['otp'] = $newOtpCode->code;
                        }else{
                            $success['otp'] = $otp->code;
                        }

                    }
                    $sms_body =
                        'OTP: ' . $success['otp'] . "\n";
                    $payer_cell = $success['phone_number'];
                    Notifications::pushSmsNotification($payer_cell, $sms_body, "Bridge_OTP");
                    return $this->sendResponse($success, 'OTP generated successfully');
                }
            }
        } else {

            return $this->sendError('The phone number is not related to any account');
        }
    }

    public function boothLogin(Request $request)
    {
        $username = $request->username;
        $password = $request->password;


        $user = AuthUser::where('username', $username)->first();

        if ($user != null && Hash::check($password, $user->password_hash)) {
            #check if user is available
            // $user = User::query()->where('email', $email)->first();

            //get user role
            $user_role_id = DB::table('auth_user_role')->where('user_id', $user->id)->first();
            $user_role_name = DB::table('auth_role')->where('id', $user_role_id->role_id)->first();

            //todo: check if account is active, if not return status "2"

            return array('status' => 1, 'message' => 'Authenticated', 'user_role_name' => $user_role_name->name, 'role_id' => $user_role_id->role_id, 'data' => $user);

        }

        return ['status' => 0, 'message' => 'Failed to Authenticate'];

    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return ['status' => 0, 'message' => 'Validation failed', 'errors' => $validator->errors()];
        }

        $validated = $validator->validated();

        $user = AuthUser::where('username', $validated['username'])->first();

        $status_code = AuthController::STATUS_INVALID_USERNAME;

        if (!is_null($user)) {
            $status_code = AuthController::STATUS_INVALID_PASSWORD;
            if (Hash::check($validated['password'], $user->password_hash)) {
                if ((int) $user->status !== 1) {
                    return $this->sendError(
                        'Your account is inactive. Contact the administrator.',
                        [],
                        self::STATUS_INACTIVE_USER,
                        403
                    );
                }

                $roles = AuthUserRole::with('role')
                    ->currentlyEffective()
                    ->where('user_id', $user->id)
                    ->whereHas('role', fn ($query) => $query->where('is_active', 1))
                    ->get();

                $mustChangePassword = (bool) $user->must_change_password;

                // Only record last_login after the user has completed the initial password change.
                if (! $mustChangePassword) {
                    $user->last_login = Carbon::now();
                    $user->save();
                }

                Auth::guard('staff')->login($user);
                $authenticatedUser = Auth::guard('staff')->user();

                // Revoke only staff/web tokens before issuing a new one.
                $authenticatedUser->tokens()->where('name', 'auth_token')->delete();
                $token = $authenticatedUser->createToken('auth_token')->plainTextToken;

                $rolesWithNames = $roles->map(function ($userRole) {
                    return [
                        'id' => $userRole->id,
                        'user_id' => $userRole->user_id,
                        'role_id' => $userRole->role_id,
                        'role_name' => $userRole->role ? $userRole->role->name : null,
                        'role_description' => $userRole->role ? $userRole->role->description : null,
                        'start_date' => $userRole->start_date?->format('Y-m-d'),
                        'end_date' => $userRole->end_date?->format('Y-m-d'),
                    ];
                });

                return $this->sendResponse([
                    'token' => $token,
                    'user' => $authenticatedUser,
                    'roles' => $rolesWithNames,
                ], 'Login successful');
            }
        }

        return $this->sendError('Invalid username or password', ['user' => $user, 'validated' => $validated], $status_code, 401);
    }

    public function updatePassword(Request $request)
    {
        $user = AuthUser::where('username', $request->username)->first();

        if (!is_null($user)) {
            $user->password_hash = Hash::make($request->new_pass);
            $user->must_change_password = false;
            $user->last_login = Carbon::now();
            if ($user->save()) {
                return $this->sendResponse([
                    'status' => 1,
                    'must_change_password' => false,
                    'message' => 'Password Successfully updated',
                ], 'Password Successfully updated');
            }
            return $this->sendError('Failed to update password');
        }

        return $this->sendError('Invalid Username or Password.');
    }

    public function logout(Request $request)
    {
        try {
            // Revoke the current user's token
            $request->user()->currentAccessToken()->delete();
            
            // Logout from the session
            Auth::guard('staff')->logout();
            
            return $this->sendResponse([], 'Logged out successfully');
        } catch (\Exception $e) {
            return $this->sendError('Logout failed: ' . $e->getMessage());
        }
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user();
            $roles = AuthUserRole::with('role')
                ->currentlyEffective()
                ->where('user_id', $user->id)
                ->get();

            $rolesWithNames = $roles->map(function ($userRole) {
                return [
                    'id' => $userRole->id,
                    'user_id' => $userRole->user_id,
                    'role_id' => $userRole->role_id,
                    'role_name' => $userRole->role ? $userRole->role->name : null,
                    'role_description' => $userRole->role ? $userRole->role->description : null,
                    'start_date' => $userRole->start_date?->format('Y-m-d'),
                    'end_date' => $userRole->end_date?->format('Y-m-d'),
                ];
            });
            
            return $this->sendResponse([
                'user' => $user,
                'roles' => $rolesWithNames,
                'must_change_password' => (bool) $user->must_change_password,
            ], 'User information retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve user information: ' . $e->getMessage());
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $user = AuthUser::where('username', $request->username)->first();

            if (!$user) {
                return $this->sendError('User not found with this username', [], 404);
            }

            // Generate OTP
            $otp = mt_rand(100000, 999999);
            $expiresAt = Carbon::now()->addMinutes(5);

            // Delete any existing OTP for this user
            OtpCode::where('user_id', $user->id)->delete();

            // Create new OTP
            OtpCode::create([
                'user_id' => $user->id,
                'code' => $otp,
                'expires_at' => $expiresAt,
            ]);

            // Send OTP via SMS (if phone number exists)
            if ($user->phone) {
                $phone = $user->phone;
                if (str_starts_with($phone, '0')) {
                    $phone = '255' . substr($phone, 1);
                }
                $sms_body = 'Password Reset OTP: ' . $otp . "\n";
                Notifications::pushSmsNotification($phone, $sms_body, "BMS", 5);
            }

            return $this->sendResponse([
                'message' => 'OTP sent successfully',
                'otp' => $otp, // Remove this in production - only for testing
            ], 'OTP sent to your registered phone number');

        } catch (\Exception $e) {
            return $this->sendError('Failed to send OTP: ' . $e->getMessage(), [], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $user = AuthUser::where('username', $request->username)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            $otpCode = OtpCode::where('user_id', $user->id)
                ->where('code', $request->otp)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$otpCode) {
                return $this->sendError('Invalid or expired OTP', [], 400);
            }

            // Delete the OTP after successful verification
            $otpCode->delete();

            return $this->sendResponse([
                'message' => 'OTP verified successfully',
                'username' => $user->username,
            ], 'OTP verified successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to verify OTP: ' . $e->getMessage(), [], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'otp' => 'required|string|size:6',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $user = AuthUser::where('username', $request->username)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            // Verify OTP again before password reset
            $otpCode = OtpCode::where('user_id', $user->id)
                ->where('code', $request->otp)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$otpCode) {
                return $this->sendError('Invalid or expired OTP', [], 400);
            }

            // Update password
            $user->password_hash = Hash::make($request->new_password);
            $user->must_change_password = false;
            $user->last_login = Carbon::now();
            $user->save();

            // Delete the OTP after successful password reset
            $otpCode->delete();

            return $this->sendResponse([
                'message' => 'Password reset successfully',
            ], 'Password has been reset successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to reset password: ' . $e->getMessage(), [], 500);
        }
    }
}
