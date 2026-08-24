<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class NssfReceiptController extends Controller
{
    /**
     * Display the NSSF receipt with dummy data.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showReceipt()
    {
        // Dummy receipt data
        $receipt = (object)[
            'receipt_number' => 'NSSF-' . rand(100000, 999999),
            'receipt_date' => date('Y-m-d'),
            'bank_receipt_no' => 'BR-' . rand(10000, 99999),
            'paid_amount' => rand(100000, 999999),
            'amount_in_words' => 'Seven hundred and fifty thousand shillings only',
            'description' => 'Payment for social security contributions for period January 2023',
            'mode_of_payment' => 'Bank Transfer',
            'payment_type' => 'Contribution',
            'member_id' => 'M-' . rand(10000, 99999),
            'payer_name' => 'John Doe',
            'employer_id' => 'E-' . rand(10000, 99999),
            'plate_no' => 'ABC123',
            'payer_id' => 'A-' . rand(10000, 99999)
        ];

        return response()->view('nssf', compact('receipt'))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Generate PDF receipt with dummy data
     * 
     * @return \Illuminate\Http\Response
     */
    public function generatePdf()
    {
        // Dummy receipt data
        $receipt = (object)[
            'receipt_number' => 'NSSF-' . rand(100000, 999999),
            'receipt_date' => date('Y-m-d'),
            'bank_receipt_no' => 'BR-' . rand(10000, 99999),
            'paid_amount' => rand(100000, 999999),
            'amount_in_words' => 'Seven hundred and fifty thousand shillings only',
            'description' => 'Payment for social security contributions for period January 2023',
            'mode_of_payment' => 'Bank Transfer',
            'payment_type' => 'Contribution',
            'member_id' => 'M-' . rand(10000, 99999),
            'payer_name' => 'John Doe',
            'employer_id' => 'E-' . rand(10000, 99999),
            'plate_no' => 'ABC123',
            'payer_id' => 'A-' . rand(10000, 99999),
            'payer_cell' => '255' . rand(10000000, 99999999),
            'payer_email' => 'john.doe@example.com'
        ];

        // Generate HTML from blade template
        $html = View::make('nssf', compact('receipt'))->render();

        // Setup mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 0,
            'margin_footer' => 0,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);

        // Generate PDF
        $mpdf->WriteHTML($html);
        
        // Output PDF (download or inline)
        return response($mpdf->Output('nssf_receipt.pdf', 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="nssf_receipt.pdf"'
        ]);
    }

    /**
     * Generate PDF receipt with provided data
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateCustomPdf(Request $request)
    {
        // Get data from request or use dummy data as fallback
        $receipt = (object)[
            'receipt_number' => $request->input('receipt_number', 'NSSF-' . rand(100000, 999999)),
            'receipt_date' => $request->input('receipt_date', date('Y-m-d')),
            'bank_receipt_no' => $request->input('bank_receipt_no', 'BR-' . rand(10000, 99999)),
            'paid_amount' => $request->input('paid_amount', rand(100000, 999999)),
            'amount_in_words' => $request->input('amount_in_words', 'Seven hundred and fifty thousand shillings only'),
            'description' => $request->input('description', 'Payment for social security contributions'),
            'mode_of_payment' => $request->input('mode_of_payment', 'Bank Transfer'),
            'payment_type' => $request->input('payment_type', 'Contribution'),
            'member_id' => $request->input('member_id', 'M-' . rand(10000, 99999)),
            'payer_name' => $request->input('payer_name', 'John Doe')
        ];

        // Generate HTML from blade template
        $html = View::make('nssf', compact('receipt'))->render();

        // Setup mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 0,
            'margin_footer' => 0,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);

        // Generate PDF
        $mpdf->WriteHTML($html);
        
        // Determine output type (inline or download)
        $outputMode = $request->input('download', false) ? 'D' : 'I';
        $filename = $request->input('filename', 'nssf_receipt.pdf');
        
        // Output PDF
        return response($mpdf->Output($filename, $outputMode), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($outputMode === 'D' ? 'attachment' : 'inline') . '; filename="' . $filename . '"'
        ]);
    }

    /**
     * Render receipt with provided data (for API use)
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function renderReceipt(Request $request)
    {
        // Get data from request or use dummy data as fallback
        $receipt = (object)[
            'receipt_number' => $request->input('receipt_number', 'NSSF-' . rand(100000, 999999)),
            'receipt_date' => $request->input('receipt_date', date('Y-m-d')),
            'bank_receipt_no' => $request->input('bank_receipt_no', 'BR-' . rand(10000, 99999)),
            'paid_amount' => $request->input('paid_amount', rand(100000, 999999)),
            'amount_in_words' => $request->input('amount_in_words', 'Seven hundred and fifty thousand shillings only'),
            'description' => $request->input('description', 'Payment for social security contributions'),
            'mode_of_payment' => $request->input('mode_of_payment', 'Bank Transfer'),
            'payment_type' => $request->input('payment_type', 'Contribution'),
            'member_id' => $request->input('member_id', 'M-' . rand(10000, 99999)),
            'payer_name' => $request->input('payer_name', 'John Doe')
        ];

        return view('nssf', compact('receipt'));
    }

    /**
     * Return dummy receipt data as JSON for API consumers
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDummyReceiptData()
    {
        $receipt = [
            'receipt_number' => 'NSSF-' . rand(100000, 999999),
            'receipt_date' => date('Y-m-d'),
            'bank_receipt_no' => 'BR-' . rand(10000, 99999),
            'paid_amount' => rand(100000, 999999),
            'amount_in_words' => 'Seven hundred and fifty thousand shillings only',
            'description' => 'Payment for social security contributions for period January 2023',
            'mode_of_payment' => 'Bank Transfer',
            'payment_type' => 'Contribution',
            'member_id' => 'M-' . rand(10000, 99999),
            'payer_name' => 'John Doe'
        ];

        return response()->json([
            'success' => true,
            'data' => $receipt,
            'message' => 'Dummy receipt data retrieved successfully'
        ]);
    }

    /**
     * Verify receipt authenticity
     * 
     * @param string $receipt_number
     * @return \Illuminate\Contracts\View\View
     */
    public function verifyReceipt($receipt_number)
    {
        // In a real application, you would query your database here
        // For now, we'll just check if it matches our dummy format
        $isValid = preg_match('/^NSSF-\d{6}$/', $receipt_number);
        
        return response()->view('receipt-verification', [
            'receipt_number' => $receipt_number,
            'is_valid' => $isValid,
            'verification_date' => now()->format('Y-m-d H:i:s')
        ])->header('Content-Type', 'text/html; charset=UTF-8');
    }
} 