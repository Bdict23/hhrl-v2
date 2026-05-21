<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Accounting\TransactionTemplate;
use App\Models\Accounting\AccountType;
use Illuminate\Routing\Controller;
use App\Models\Business\Employee;
use App\Models\Business\Customer;



class AccountingApiController extends Controller
{

    public function selectedTransactionTemplate(Request $request)
    {
        $company_id = $request->query('company_id');
        $trans_type_id = $request->query('transaction_type_id');
        $transactions = TransactionTemplate::
        where('transaction_type', $trans_type_id)->
        where('is_active', true)->where('company_id', $company_id)
        ->with('templateName')
        ->get()
        ->map(function($template){
                return [
                    'id'=> $template->id,
                    'template_name'=> $template->templateName->template_name,
                    'description'=> $template->description,
                ];
            });

        return response()->json($transactions);


    }
    public function activeAccountType(Request $request)
    {
        $company_id = $request->query('company_id');
        $type = AccountType::where('company_id', $company_id)->where('is_active', true)->get();
        return response()->json($type);

    }

    public function pcvPayeeEmployee(Request $request){

        $branch_id = $request->query('branch_id');
        $payee = Employee::where('branch_id', $branch_id)->where('status', 'ACTIVE')->get()->map(function($employee){
                return [
                    'id'=> $employee->id,
                    'name'=> $employee->full_name,
                    'description'=> $employee->position->position_name,
                ];
            });

        return response()->json($payee);

    }

    public function pcvPayeeCustomer(Request $request)
    {
         $branch_id = $request->query('branch_id');
          $payee = Customer::where('branch_id', $branch_id)->get()->map(function($customer){

                return [
                    'id'=> $customer->id,
                    'name'=> $customer->full_name,
                    'description'=> '(Customer)',
                ];
            });
        return response()->json($payee);

    }


}
