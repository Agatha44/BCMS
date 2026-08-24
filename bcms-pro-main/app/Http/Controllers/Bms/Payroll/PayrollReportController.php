<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollReportService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PayrollReportController extends Controller
{
    public function listReportTypes(PayrollReportService $reportService): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Payroll report types retrieved successfully',
                'data' => $reportService->listReportTypes(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll report types: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Read-only report data for on-screen display (POST body, not persisted).
     */
    public function show(Request $request, PayrollReportService $reportService): JsonResponse
    {
        $validator = $this->makeReportValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $reportService->generateReport(
                (string) $request->input('report_type'),
                (string) $request->input('month'),
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 15),
                $this->resolveBankInput($request),
                $this->resolveLoanTypeIdInput($request),
            );

            return response()->json([
                'success' => true,
                'message' => 'Payroll report generated successfully',
                'data' => $data,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payroll report: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * PDF download via PayrollDocumentService (same templates as payroll documents).
     */
    public function download(Request $request, PayrollReportService $reportService): JsonResponse|Response
    {
        $validator = $this->makeReportValidator($request, includeFormat: true);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $format = $request->input('format');
        if ($format !== null && ! in_array($format, ['download', 'base64'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['format' => ['format must be download or base64.']],
            ], 422);
        }

        try {
            $result = $reportService->downloadDocument(
                (string) $request->input('report_type'),
                (string) $request->input('month'),
                $this->resolveBankInput($request),
                is_string($format) ? $format : null,
                $request->wantsJson(),
                $request->expectsJson(),
                $this->resolveLoanTypeIdInput($request),
            );

            return $this->documentResponse($result);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download payroll report: '.$e->getMessage(),
            ], 500);
        }
    }

    private function makeReportValidator(Request $request, bool $includeFormat = false): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [
            'report_type' => 'required|string',
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:200',
            'bank' => 'sometimes|nullable|string',
            'bank_id' => 'sometimes|nullable',
            'bank_bucket' => 'sometimes|nullable|string',
            'filters' => 'sometimes|array',
            'filters.bank' => 'sometimes|nullable|string',
            'filters.bank_id' => 'sometimes|nullable',
            'filters.bucket' => 'sometimes|nullable|string',
            'loan_type_id' => 'sometimes|nullable|integer|min:1',
            'filters.loan_type_id' => 'sometimes|nullable|integer|min:1',
        ];

        if ($includeFormat) {
            $rules['format'] = 'sometimes|string|in:download,base64';
        }

        return Validator::make($request->all(), $rules);
    }

    /**
     * @param  array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}  $result
     */
    private function documentResponse(array $result): JsonResponse|Response
    {
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to generate payroll document',
            ], (int) ($result['http'] ?? 500));
        }

        if (isset($result['response'])) {
            return $result['response'];
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Payroll document generated successfully',
            'data' => $result['data'] ?? [],
        ]);
    }

    private function resolveBankInput(Request $request): ?string
    {
        foreach (['bank_id', 'bank', 'bank_bucket'] as $key) {
            $value = $request->input($key);
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        $filters = $request->input('filters');
        if (! is_array($filters)) {
            return null;
        }

        foreach (['bank_id', 'bank', 'bank_bucket'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function resolveLoanTypeIdInput(Request $request): ?int
    {
        foreach (['loan_type_id'] as $key) {
            $value = $request->input($key);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        $filters = $request->input('filters');
        if (! is_array($filters)) {
            return null;
        }

        $value = $filters['loan_type_id'] ?? null;
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return null;
    }
}
