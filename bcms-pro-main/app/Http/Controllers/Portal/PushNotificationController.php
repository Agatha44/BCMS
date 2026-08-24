<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\FirebaseHelper;

class PushNotificationController extends ConfigurationController
{
    /**
     * Register FCM token for the authenticated user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function registerToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 0);
        }

        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // Update user's FCM token
            DB::table('auth_user')
                ->where('id', $user->id)
                ->update([
                    'fcm_token' => $request->fcm_token,
                    'device_type' => $request->device_type ?? 'mobile',
                    'fcm_token_updated_at' => now(),
                ]);

            Log::info('FCM token registered', [
                'user_id' => $user->id,
                'device_type' => $request->device_type ?? 'mobile',
            ]);

            return $this->sendResponse([
                'user_id' => $user->id,
                'fcm_token_registered' => true,
            ], 'FCM token registered successfully');

        } catch (\Exception $e) {
            Log::error('Error registering FCM token', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->sendError('Failed to register FCM token: ' . $e->getMessage(), [], 0);
        }
    }

    /**
     * Unregister FCM token (when user logs out)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function unregisterToken(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // Remove FCM token
            DB::table('auth_user')
                ->where('id', $user->id)
                ->update([
                    'fcm_token' => null,
                    'device_type' => null,
                    'fcm_token_updated_at' => null,
                ]);

            Log::info('FCM token unregistered', [
                'user_id' => $user->id,
            ]);

            return $this->sendResponse([
                'user_id' => $user->id,
                'fcm_token_unregistered' => true,
            ], 'FCM token unregistered successfully');

        } catch (\Exception $e) {
            Log::error('Error unregistering FCM token', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->sendError('Failed to unregister FCM token: ' . $e->getMessage(), [], 0);
        }
    }

    /**
     * Send push notification to a user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:auth_user,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string',
            'route' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 0);
        }

        try {
            // Get user's FCM token
            $user = DB::table('auth_user')
                ->where('id', $request->user_id)
                ->select('fcm_token', 'device_type', 'first_name', 'surname')
                ->first();

            if (!$user || !$user->fcm_token) {
                return $this->sendError('User not found or FCM token not registered', [], 0);
            }

            // Send notification via Firebase
            $result = $this->sendFirebaseNotification(
                $user->fcm_token,
                $request->title,
                $request->body,
                $request->type,
                $request->route,
                $request->data ?? []
            );

            if ($result['success']) {
                Log::info('Push notification sent', [
                    'user_id' => $request->user_id,
                    'title' => $request->title,
                    'type' => $request->type,
                ]);

                return $this->sendResponse([
                    'user_id' => $request->user_id,
                    'notification_sent' => true,
                    'message_id' => $result['message_id'] ?? null,
                ], 'Notification sent successfully');
            } else {
                return $this->sendError('Failed to send notification: ' . $result['error'], [], 0);
            }

        } catch (\Exception $e) {
            Log::error('Error sending push notification', [
                'error' => $e->getMessage(),
                'user_id' => $request->user_id,
            ]);

            return $this->sendError('Failed to send notification: ' . $e->getMessage(), [], 0);
        }
    }

    /**
     * Send push notification to multiple users
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendBulkNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:auth_user,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string',
            'route' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 0);
        }

        try {
            $userIds = $request->user_ids;
            $successCount = 0;
            $failureCount = 0;
            $results = [];

            // Get all users' FCM tokens
            $users = DB::table('auth_user')
                ->whereIn('id', $userIds)
                ->whereNotNull('fcm_token')
                ->select('id', 'fcm_token', 'device_type')
                ->get();

            foreach ($users as $user) {
                $result = $this->sendFirebaseNotification(
                    $user->fcm_token,
                    $request->title,
                    $request->body,
                    $request->type,
                    $request->route,
                    $request->data ?? []
                );

                if ($result['success']) {
                    $successCount++;
                    $results[] = [
                        'user_id' => $user->id,
                        'status' => 'sent',
                    ];
                } else {
                    $failureCount++;
                    $results[] = [
                        'user_id' => $user->id,
                        'status' => 'failed',
                        'error' => $result['error'],
                    ];
                }
            }

            Log::info('Bulk push notification sent', [
                'total_users' => count($userIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            return $this->sendResponse([
                'total_users' => count($userIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results,
            ], "Notifications sent: {$successCount} successful, {$failureCount} failed");

        } catch (\Exception $e) {
            Log::error('Error sending bulk push notification', [
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to send bulk notifications: ' . $e->getMessage(), [], 0);
        }
    }


    /**
     * Send notification via Firebase Cloud Messaging HTTP v1 API
     * 
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param string|null $type
     * @param string|null $route
     * @param array $data
     * @return array
     */
    private function sendFirebaseNotification(
        string $fcmToken,
        string $title,
        string $body,
        ?string $type = null,
        ?string $route = null,
        array $data = []
    ): array {
        try {
            $firebaseConfig = config('params.firebase');
            $projectId = $firebaseConfig['project_id'] ?? 'nssf-app-77430';

            // Get OAuth 2.0 access token
            $accessToken = FirebaseHelper::getAccessToken();

            if (empty($accessToken)) {
                return [
                    'success' => false,
                    'error' => 'Failed to obtain Firebase access token. Please check service account configuration.',
                ];
            }

            // Build message payload for HTTP v1 API
            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_merge([
                        'type' => $type ?? '',
                        'route' => $route ?? '',
                    ], array_map('strval', $data)), // Convert all data values to strings
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];

            // Send via FCM HTTP v1 API
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'message_id' => $responseData['name'] ?? null,
                ];
            } else {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? 'Unknown error';
                
                Log::error('FCM HTTP v1 API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $errorBody,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error sending Firebase notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

