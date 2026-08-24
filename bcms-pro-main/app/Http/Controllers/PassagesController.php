<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mpdf\Mpdf;

class PassagesController extends Controller
{
    /**
     * Fetch bundle passages and return as base64 PDF
     */
    public function fetchBundlePassagesPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'nullable|string|exists:vehicle,plate_no',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 400);
        }

        $user = Auth::user();
        $account = Account::where('id', $user->id)->first();

        $passages = DB::table('bundle_subscription_passage as bsp')
            ->select([
                'bsp.id',
                'l.lane_no',
                'v.plate_no',
                'bt.name as body_type_name',
                'bsp.arrival_time as pass_date',
                'bsp.clearance_time'
            ])
            ->join('lane as l', 'l.id', '=', 'bsp.lane_id')
            ->join('vehicle as v', 'v.card_number', '=', 'bsp.card_number')
            ->join('account_vehicle as av', 'av.vehicle_id', '=', 'v.id')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->where('av.account_id', '=', $account->account_no);

        if ($request->plate_no != null) {
            $passages->where('v.plate_no', $request->plate_no);
        }

        $passages = $passages->get();

        // Generate PDF using the passages blade template
        $mpdf = new Mpdf();
        $mpdf->WriteHTML(view('passages', compact('passages')));

        // Encode PDF to base64
        $pdf_base64 = base64_encode($mpdf->Output('bundle_passages.pdf', 'S'));

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_base64' => $pdf_base64,
                'passages_count' => $passages->count()
            ]
        ], 200);
    }

    /**
     * Fetch normal passages and return as base64 PDF
     */
    public function fetchNormalPassagesPdf()
    {
        $user = Auth::user();
        $account = Account::where('id', $user->id)->first();

        $passages = DB::table('toll_transaction as tt')
            ->select([
                'tt.id',
                'tt.charged_amount',
                'tt.plate_no',
                'tt.receipt_num',
                'tt.trans_type',
                'tt.created_at as pass_date',
                'bt.name as body_type_name',
                'l.lane_no'
            ])
            ->join('lane as l', 'l.id', '=', 'tt.lane_id')
            ->join('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->where('tt.account_no', '=', $account->account_no)
            ->get();

        // Generate PDF using the passages blade template
        $mpdf = new Mpdf();
        $mpdf->WriteHTML(view('passages', compact('passages')));

        // Encode PDF to base64
        $pdf_base64 = base64_encode($mpdf->Output('normal_passages.pdf', 'S'));

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_base64' => $pdf_base64,
                'passages_count' => $passages->count()
            ]
        ], 200);
    }

    /**
     * Fetch all passages (bundle + normal) and return as base64 PDF
     */
    public function fetchAllPassagesPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'nullable|string|exists:vehicle,plate_no',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 400);
        }

        $user = Auth::user();
        $account = Account::where('id', $user->id)->first();

        // Fetch bundle passages
        $bundlePassages = DB::table('bundle_subscription_passage as bsp')
            ->select([
                'bsp.id',
                'l.lane_no',
                'v.plate_no',
                'bt.name as body_type_name',
                'bsp.arrival_time as pass_date',
                'bsp.clearance_time'
            ])
            ->join('lane as l', 'l.id', '=', 'bsp.lane_id')
            ->join('vehicle as v', 'v.card_number', '=', 'bsp.card_number')
            ->join('account_vehicle as av', 'av.vehicle_id', '=', 'v.id')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->where('av.account_id', '=', $account->account_no);

        if ($request->plate_no != null) {
            $bundlePassages->where('v.plate_no', $request->plate_no);
        }

        $bundlePassages = $bundlePassages->get();

        // Fetch normal passages
        $normalPassages = DB::table('toll_transaction as tt')
            ->select([
                'tt.id',
                'tt.charged_amount',
                'tt.plate_no',
                'tt.receipt_num',
                'tt.trans_type',
                'tt.created_at as pass_date',
                'bt.name as body_type_name',
                'l.lane_no'
            ])
            ->join('lane as l', 'l.id', '=', 'tt.lane_id')
            ->join('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->where('tt.account_no', '=', $account->account_no);

        if ($request->plate_no != null) {
            $normalPassages->where('tt.plate_no', $request->plate_no);
        }

        $normalPassages = $normalPassages->get();

        // Combine both collections
        $allPassages = $bundlePassages->concat($normalPassages);

        // Generate PDF using the passages blade template
        $passages = $allPassages;
        $mpdf = new Mpdf();
        $mpdf->WriteHTML(view('passages', compact('passages')));

        // Encode PDF to base64
        $pdf_base64 = base64_encode($mpdf->Output('all_passages.pdf', 'S'));

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_base64' => $pdf_base64,
                'bundle_passages_count' => $bundlePassages->count(),
                'normal_passages_count' => $normalPassages->count(),
                'total_passages_count' => $allPassages->count()
            ]
        ], 200);
    }
}
