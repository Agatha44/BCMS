<?php

namespace App\Http\Controllers\BodyTypesController;

use App\Http\Controllers\Configurations\ConfigurationController;
use \Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BodyTypesController extends ConfigurationController
{

    public function listBodyTypes(): array | Collection
    {
        $model = DB::table('body_type')
            ->selectRaw('body_type.id, body_type.name,body_type.description, price_list.amount')
            ->join('price_list', 'body_type.id', '=', 'price_list.body_type_id')
            ->get();
        if ($model != null) {
            return $model;
        } else {
            return ['status' => 0];
        }
    }

    public function listExemptedBodyTypes()
    {
        return DB::table('exempted')->get();
    }
}
