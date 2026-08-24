<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BasicController extends Controller
{
        /**
     * success response method.
     *
     * @param $result
     * @param $message
     * @return JsonResponse
     */
    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'success' => true,
            'status_code' => 1,
            'data'    => $result,
            'message' => $message,
        ];
        return response()->json($response);
    }

    /**
     * return error response.
     *
     * @param $error
     * @param array $errorMessages
     * @param int $code
     * @return JsonResponse
     */
    public function sendError($error, array $errorMessages = [], $error_code = 0, $http_status_code = 200): JsonResponse
    {
        $response = [
            'success' => false,
            'status_code' => $error_code,
            'message' => $error,
        ];
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        return response()->json($response)->setStatusCode($http_status_code);
    }
}
