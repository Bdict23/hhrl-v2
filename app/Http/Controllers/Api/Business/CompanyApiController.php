<?php

namespace App\Http\Controllers\Api\Business;

use App\Models\Business\Branch;
use Illuminate\Http\Request;

class CompanyApiController
{
    public function myBranches(Request $request)
    {

        $result = Branch::whereIn('id', $request->query('branches', []))
            ->where('branch_status', 'ACTIVE')
            ->get()->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'label' => $branch->branch_name,
                    'description' => $branch->branch_address,
                ];
            });
        return response()->json($result);
    }
}
