<?php

namespace App\Http\Controllers\Lane;
use Illuminate\Http\Request;
use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Lane;
use \Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LaneController extends ConfigurationController
{

    public function listLanes(): Collection | array
    {
        return Lane::all();
    }

    public function openTollGate(Request $request): array
    {
        $lane = Lane::where('id', $request->lane_id)->first();
        if ($lane != null && $lane->gate_ip != null) {
            $response = Http::get($lane->gate_ip);
            if ($response->ok()) {
                DB::table('open_gate')->insert([
                    'lane_id' => $request->lane_id,
                    'user_id' => $request->user_id,
                    'reason' => $request->reason
                ]);
                return ['status' => true, 'message' => 'Gate opened successfully'];
            }
        }
        return ['status' => false, 'message' => 'Failed to open gate'];
    }
}
