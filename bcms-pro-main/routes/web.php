<?php

use App\Models\BodyType;
use App\Models\Lane;
use App\Models\TollTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\Receipts\NssfReceiptController;
use App\Http\Controllers\IncidentFine\IncidentFineController;
use App\Http\Controllers\EventPayment\EventPaymentController;
use Mpdf\Mpdf;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/receipt/{id}', function ($id) {
    $tra_details = DB::table('vfd_registration')->orderBy('id', 'DESC')->first();

    if ($id == 'test') {
        $qrcode = QrCode::format('svg')->size(200)->generate('Test QR Code');

        $mpdf = new Mpdf();
        $mpdf->WriteHTML(view('test-receipt', compact(
            'qrcode', 'tra_details'
        )));
        
        // Output PDF (download or inline)
        // return response($mpdf->Output('receipt.pdf', 'I'), 200, [
        //     'Content-Type' => 'application/pdf',
        //     'Content-Disposition' => 'inline; filename="receipt.pdf"'
        // ]);

        //encode the pdf to base64
        $pdf_base64 = base64_encode($mpdf->Output('receipt.pdf', 'S'));
        return response([
            'success' => true,
            'data' => [
                'pdf_base64' => $pdf_base64
            ]
        ], 200);
    }

    $toll_transaction = TollTransaction::where('id', $id)->first();

    if ($toll_transaction != null) {
        $transaction = Transaction::where('receipt_num', $toll_transaction->receipt_num)->first();
        if ($transaction != null) {
            $plate_no = $toll_transaction->plate_no;
            $body_type = BodyType::where('id', $toll_transaction->body_type_id)->first()->name;
            $amount = $toll_transaction->charged_amount;
            $lane = Lane::where('id', $toll_transaction->lane_id)->first()->lane_no;

            $dc = $transaction->dc;
            $desc = $transaction->trans_desc;
            $net_amount = round($transaction->charged_amount / 1.18, 2);
            $tax_amount = round($transaction->charged_amount - $net_amount, 2);
            $tax_exclusive_amount = round($transaction->charged_amount / 1.18, 2);

            $split_d_t = explode(' ', $transaction->created_at);
            $trans_date = $split_d_t[0];
            $trans_time = $split_d_t[1];

            $ver_code = 'https://verify.tra.go.tz/' . $tra_details->receiptcode . $transaction->gc . '_' . str_replace(':', '', $split_d_t[1]);
            $qrcode = QrCode::format('svg')->size(200)->generate($ver_code);

            // mpdf
            $mpdf = new Mpdf();
            $mpdf->WriteHTML(view('receipt', compact(
                'qrcode', 'body_type', 'amount', 'lane', 'plate_no', 'tra_details',
                'trans_date', 'dc', 'trans_time', 'tax_amount', 'tax_exclusive_amount', 'net_amount', 'desc'
            )));

            //encode the pdf to base64 
            $pdf_base64 = base64_encode($mpdf->Output('receipt.pdf', 'I'));
            return response([
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdf_base64
                ]
            ], 200);
            
            // Output PDF (download or inline)
            // return response($mpdf->Output('receipt.pdf', 'I'), 200, [
            //     'Content-Type' => 'application/pdf',
            //     'Content-Disposition' => 'inline; filename="nssf_receipt.pdf"'
            // ]);

            // return view('receipt', compact(
            //     'qrcode', 'body_type', 'amount', 'lane', 'plate_no', 'tra_details',
            //     'trans_date', 'dc', 'trans_time', 'tax_amount', 'tax_exclusive_amount', 'net_amount', 'desc'
            // ));
        }
    }

    return view('not_found');


});

Route::get('/qrcode', function () {
    return QrCode::size(300)->generate('A basic example of QR code!');
});

// NSSF Receipt Route for browser viewing
Route::get('/api/nssf-receipt-preview', [NssfReceiptController::class, 'showReceipt']);
Route::get('/api/nssf-receipt-pdf', [NssfReceiptController::class, 'generatePdf']);
Route::get('/api/verify-receipt/{receipt_number}', [NssfReceiptController::class, 'verifyReceipt']);

// Printer Routes (for PDF generation - no auth required for direct access)
Route::get('/printer/print-receipt', function (\Illuminate\Http\Request $request) {
    $id = $request->query('id');
    if (!$id) {
        return response('Receipt ID is required', 400);
    }
    return app(IncidentFineController::class)->printReceipt($id);
})->name('printer.print-receipt');

Route::get('/printer/print-bill', function (\Illuminate\Http\Request $request) {
    $id = $request->query('id');
    if (!$id) {
        return response('Bill ID is required', 400);
    }
    return app(IncidentFineController::class)->printBill($id);
})->name('printer.print-bill');

// Event Payment Printer Routes
Route::get('/printer/print-event-receipt', function (\Illuminate\Http\Request $request) {
    $id = $request->query('id');
    if (!$id) {
        return response('Receipt ID is required', 400);
    }
    return app(EventPaymentController::class)->printReceipt($id);
})->name('printer.print-event-receipt');

Route::get('/printer/print-fine-charge-receipt', function (\Illuminate\Http\Request $request) {
    $id = $request->query('id');
    if (!$id) {
        return response('Receipt ID is required', 400);
    }
    return app(\App\Http\Controllers\FineCharge\FineChargeController::class)->printReceipt($id);
})->name('printer.print-fine-charge-receipt');

