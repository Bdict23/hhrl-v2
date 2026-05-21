<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Settings\SystemParameter;
use Illuminate\Routing\Controller;


class SystemParameterApiController extends Controller
{
    public function pcvType(Request $request){
        $branch_id = $request->query('branch_id');
        $types = SystemParameter::where('module_id', 68)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'pcv_type%')->get();

        return response()->json($types);
    }
}
