<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Business\Customer;


class CustomerApiController extends Controller
{
   public function getBranchCustomers(Request $request)
   {
    $branch_id = $request->query('branch_id');

    $customers = Customer::query()
    ->where('branch_id', $branch_id)
    ->get();

     return response()->json($customers);
   }
}
