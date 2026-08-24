<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BridgeInterfaceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class BridgeStatusController extends Controller
{
    private BridgeInterfaceStatusService $interfaceStatusService;

    public function __construct(BridgeInterfaceStatusService $interfaceStatusService)
    {
        $this->interfaceStatusService = $interfaceStatusService;
    }

    public function bridgeStatus(Request $request, ?string $function = null): JsonResponse
    {
        $function = $function ?? $request->input('function');

        if ($function === 'service') {
            return response()->json($this->serviceStatus());
        }

        if ($function === 'interface') {
            return response()->json($this->interfaceStatusService->getCached());
        }

        return response()->json([
            [
                'status' => 0,
                'message' => 'Specified function ' . ($function ?? '') . '  is not defined',
            ],
        ]);
    }

    private function serviceStatus(): array
    {
        $database = $this->checkDatabase();
        $bridgeApp = $this->checkEndpoint(config('bridge_status.frontend_url'));
        $bridgeCore = $this->checkEndpoint(config('bridge_status.backend_url'));

        $statuses = [$database['status'], $bridgeApp['status'], $bridgeCore['status']];

        if (in_array(3, $statuses, true)) {
            $status = 3;
            $message = 'Error';
        } elseif (in_array(2, $statuses, true)) {
            $status = 2;
            $message = 'Warning';
        } else {
            $status = 1;
            $message = 'Success';
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => [
                ['name' => 'Database Service', 'type' => 'Internal', 'status' => $database['status'], 'message' => $database['message']],
                ['name' => 'Frontend Application', 'type' => 'Internal', 'status' => $bridgeApp['status'], 'message' => $bridgeApp['message']],
                ['name' => 'Backend Application', 'type' => 'Internal', 'status' => $bridgeCore['status'], 'message' => $bridgeCore['message']],
            ],
        ];
    }

    private function checkDatabase(): array
    {
        try {
            Transaction::query()->limit(1)->exists();

            return ['status' => 1, 'message' => 'Listening...'];
        } catch (Throwable $exception) {
            return ['status' => 2, 'message' => $exception->getMessage()];
        }
    }

    private function checkEndpoint(?string $url): array
    {
        if (empty($url)) {
            return ['status' => 2, 'message' => 'URL not configured'];
        }

        try {
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                return ['status' => 1, 'message' => 'Listening...'];
            }

            return ['status' => 2, 'message' => 'HTTP ' . $response->status()];
        } catch (Throwable $exception) {
            return ['status' => 3, 'message' => $exception->getMessage()];
        }
    }
}
