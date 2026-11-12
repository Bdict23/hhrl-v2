<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountTitle;
use Illuminate\Support\Facades\DB;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\TemplateName;

class AccountingService
{
    protected $accountTitle;
    protected $accountType;
    protected $templateName;

    public function __construct(AccountTitle $accountTitle, AccountType $accountType, TemplateName $templateName)
    {
        $this->accountTitle = $accountTitle;
        $this->accountType = $accountType;
        $this->templateName = $templateName;
    }

    public function getAccountTitles($searchTitle, $quantityTitle)
    {
        return AccountTitle::orderBy('account_code')
            ->when($searchTitle, function ($query) use ($searchTitle) {
                $query->where('account_title', 'like', '%' . $searchTitle . '%')
                    ->orWhere('account_code', 'like', '%' . $searchTitle . '%');
            })
            ->with('businessUnit')
            ->paginate($quantityTitle);
    }

    public function getAccountTypes($searchType, $quantityType)
    {
        return AccountType::orderBy('type_name')
            ->when($searchType, function ($query) use ($searchType) {
                $query->where('type_name', 'like', '%' . $searchType . '%');
            })
            ->with('businessUnit')
            ->paginate($quantityType);
    }

    public function createTemplateName($data)
    {

        return DB::transaction(function () use ($data) {
            $this->templateName = new TemplateName();
            $this->templateName->company_id = $data['company_id'];
            $this->templateName->template_name = $data['template_name'];
            $this->templateName->created_by = $data['created_by'];
            $this->templateName->is_active = 1;
            $this->templateName->save();

            return $this->templateName;
        });
    }

    public function createAccountType($data)
    {
        return DB::transaction(function () use ($data) {
            $this->accountType = new AccountType();
            $this->accountType->company_id = $data['company_id'];
            $this->accountType->type_name = $data['type_name'];
            $this->accountType->description = $data['description'];
            $this->accountType->created_by = $data['created_by'];
            $this->accountType->is_active = 1;
            $this->accountType->save();

            return $this->accountType;
        });
    }

    public function createTitle($data)
    {
        return DB::transaction(function () use ($data) {
            $this->accountTitle = new AccountTitle();
            $this->accountTitle->company_id = $data['company_id'];
            $this->accountTitle->parent_id = $data['parent_id'];
            $this->accountTitle->description = $data['description'];
            $this->accountTitle->created_by = $data['created_by'];
            $this->accountTitle->account_code = $data['account_code'];
            $this->accountTitle->transaction_type = $data['transaction_type'];
            $this->accountTitle->account_title = $data['account_title'];
            $this->accountTitle->normal_balance = $data['normal_balance'];
            $this->accountTitle->is_active = 1;
            $this->accountTitle->save();

            return $this->accountType;
        });
    }

    public static function getGeneratedCode($coa_id)
    {
        // 1. Find the parent account using the passed $coa_id parameter
        $parent = AccountTitle::findOrFail($coa_id);

        // 2. Get the parent's account code
        $parentCode = $parent->account_code;

        // 3. Count existing children for this parent and add 1 for the new sequence
        $childCount = AccountTitle::where('parent_id', $coa_id)->count() + 1;

        // 4. Return the generated code (using addition if numeric, or concatenation if formatted like '1000-01')
        return $parentCode . '-' . str_pad($childCount, 2, '0', STR_PAD_LEFT);
        ;
    }
}
