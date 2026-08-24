<?php

namespace App\Services\Overtime;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class OvertimeInvoiceService
{
    public function generateInvoiceDocument(string $batchNumber, string $flag = 'overtime'): string
    {
        try {
            $invoiceData = $this->getInvoiceDetails($batchNumber, $flag);

            if (empty($invoiceData)) {
                throw new \Exception("No invoice details found for batch number: {$batchNumber}");
            }

            $batchInfo = $invoiceData[0] ?? null;
            if (!$batchInfo) {
                throw new \Exception("Invalid invoice data structure for batch number: {$batchNumber}");
            }

            $tempDir = storage_path('app/tmp/mpdf');
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                    throw new \Exception("Failed to create temp directory: {$tempDir}");
                }
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 10,
                'margin_footer' => 10,
                'tempDir' => $tempDir,
            ]);

            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';

            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                $mpdf->SetWatermarkImage($logoPath, 0.04, [65, 65], 'P');
                $mpdf->showWatermarkImage = true;
            }

            $html = view('overtime.documents.invoice_document', [
                'batchInfo' => $batchInfo,
                'employees' => $invoiceData,
                'logoBase64' => $logoBase64,
            ])->render();

            $mpdf->WriteHTML($html);

            return base64_encode($mpdf->Output('', 'S'));
        } catch (\Exception $e) {
            Log::error('Error generating invoice document PDF', [
                'batch_number' => $batchNumber,
                'flag' => $flag,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getInvoiceDocumentEndpoint(string $batchNumber, string $flag, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        try {
            if (!$format) {
                $format = ($wantsJson || $expectsJson) ? 'base64' : 'download';
            }

            $pdfBase64 = $this->generateInvoiceDocument($batchNumber, $flag);

            if (!$pdfBase64) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate invoice document',
                    'http' => 500,
                ];
            }

            if ($format === 'download') {
                return [
                    'success' => true,
                    'response' => response(base64_decode($pdfBase64), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="Invoice_' . $batchNumber . '.pdf"',
                    ]),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'batch_number' => $batchNumber,
                    'file_name' => 'Invoice_' . $batchNumber . '.pdf',
                ],
                'message' => 'Invoice document generated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Error in getInvoiceDocumentEndpoint', [
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate invoice document: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    public function generateOvertimeDocument(string $batchNumber): string
    {
        try {
            $calculationData = $this->getInvoiceDetails($batchNumber);

            if (empty($calculationData)) {
                throw new \Exception("No overtime calculation details found for batch number: {$batchNumber}");
            }

            $batchInfo = $calculationData[0] ?? null;
            if (!$batchInfo) {
                throw new \Exception("Invalid calculation data structure for batch number: {$batchNumber}");
            }

            $tempDir = storage_path('app/tmp/mpdf');
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                    throw new \Exception("Failed to create temp directory: {$tempDir}");
                }
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_header' => 10,
                'margin_footer' => 10,
                'tempDir' => $tempDir,
            ]);

            $logoPath = public_path('images/nssf-log1.png');
            $coatPath = public_path('images/Tanzania Coat of Arms_.png');
            $logoImageSrc = '';
            $coatImageSrc = '';

            if (file_exists($logoPath)) {
                $logoImageSrc = str_replace('\\', '/', $logoPath);
                $mpdf->SetWatermarkImage($logoPath, 0.04, [65, 65], 'P');
                $mpdf->showWatermarkImage = true;
            }

            if (file_exists($coatPath)) {
                $coatImageSrc = str_replace('\\', '/', $coatPath);
            }

            $html = view('overtime.documents.overtime_calculation_breakdown', [
                'batchInfo' => $batchInfo,
                'employees' => $calculationData,
                'logoImageSrc' => $logoImageSrc,
                'coatImageSrc' => $coatImageSrc,
            ])->render();

            $mpdf->WriteHTML($html);

            return base64_encode($mpdf->Output('', 'S'));
        } catch (\Exception $e) {
            Log::error('Error generating overtime calculation sheet PDF', [
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getOvertimeDocumentEndpoint(string $batchNumber, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        try {
            if (!$format) {
                $format = ($wantsJson || $expectsJson) ? 'base64' : 'download';
            }

            $pdfBase64 = $this->generateOvertimeDocument($batchNumber);

            if (!$pdfBase64) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate overtime calculation sheet',
                    'http' => 500,
                ];
            }

            $fileName = 'Overtime_Calculation_Sheet_' . $batchNumber . '.pdf';

            if ($format === 'download') {
                return [
                    'success' => true,
                    'response' => response(base64_decode($pdfBase64), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                    ]),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'batch_number' => $batchNumber,
                    'file_name' => $fileName,
                ],
                'message' => 'Overtime calculation sheet generated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Error in getCalculationSheetEndpoint', [
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate overtime calculation sheet: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    private function getInvoiceDetails(string $batchNumber, string $flag = 'overtime')
    {
        try {
            $query = "SELECT
                        ob.batch_number,
                        r.pf_number,
                        r.month,
                        r.total_overtime_hours,
                        r.total_days,
                        r.total_amount,
                        r.gross_pay,
                        r.tax,
                        r.net_pay,
                        be.fname,
                        be.mname,
                        be.sname,
                        be.basicsalary,
                        ob.created_at,
                        ob.total_amount AS total_batch_amount,
                        ord.daily_rate,
                        CONCAT(a.first_name, ' ', a.middle_name, ' ', a.surname) AS reviewed_by,
                        CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS full_name
                    FROM bcmis2.overtime_batches ob
                    JOIN bcmis2.overtime_requests r
                        ON r.batch_id = ob.id
                    JOIN bcmis2.bridge_employee be
                        ON be.pfno = r.pf_number
                    JOIN bcmis.auth_user a
                        ON a.id = ob.created_by
                    LEFT JOIN (
                        SELECT
                            ord_sub.overtime_request_id,
                            r_sub.pf_number,
                            MAX(ord_sub.daily_rate) AS daily_rate
                        FROM bcmis2.overtime_request_days ord_sub
                        INNER JOIN bcmis2.overtime_requests r_sub
                            ON r_sub.id = ord_sub.overtime_request_id
                        GROUP BY ord_sub.overtime_request_id, r_sub.pf_number
                    ) ord
                        ON ord.overtime_request_id = r.id
                        AND ord.pf_number = r.pf_number
                    WHERE ob.batch_number = ?";

            return DB::connection('bcmis2')->select($query, [$batchNumber]);
        } catch (\Exception $e) {
            Log::error('Error retrieving invoice details', [
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
