<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Accounting\TransactionTemplate;
use App\Models\Accounting\AccountTitle;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\TemplateName;
use TallStackUi\Traits\Interactions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\Accounting\AccountingService;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;
    use Interactions;

    public int $tabNum = 1;
    public array $form = [];
    public bool $createCategoryModal = false;
    public bool $createAmenityModal = false;
    public bool $editAmenityModal = false;
    public ?int $amenityId = null;
    public string $categoryName = '';
    public string $categorySlug = '';
    public string $categoryDescription = '';
    public int $categorySortOrder = 0;
    public bool $categoryIsActive = true;
    public string $amenityName = '';
    public $tab = 'TEMPLATE SUMMARY';
    public $templateName = false;

    //create template name
    public $tnCompany_id,$template_name;
    public $search='',  $quantity= 15, $quantityTitle=15, $searchTitle='', $quantityType=15, $searchType='', $searchTemplateName='', $quantityTemplateName=15;

    //Account tile variables
    public $accountTitleModal = false, $account_title, $account_code, $normal_balance, $titleCompany_id, $titleDescription , $parentTitleId, $title_transaction_type;

    // Account type variables
    public $accountTypeModal = false, $type_name, $atCompany_id, $atDescription;

    // Edit Template Name variables
    public bool $editTemplateNameModal = false;
    public ?int $editTnId = null;
    public string $edit_template_name = '';
    public ?int $edit_tnCompany_id = null;

    // Edit Account Title variables
    public bool $editAccountTitleModal = false;
    public ?int $editTitleId = null;
    public string $edit_account_title = '';
    public string $edit_account_code = '';
    public string $edit_normal_balance = 'debit';
    public ?int $edit_titleCompany_id = null;
    public ?string $edit_titleDescription = '';
    public ?int $edit_parentTitleId = null;
    public ?int $edit_title_transaction_type = null;

    // Edit Account Type variables
    public bool $editAccountTypeModal = false;
    public ?int $editTypeId = null;
    public string $edit_type_name = '';
    public ?string $edit_atDescription = '';
    public ?int $edit_atCompany_id = null;

    // View particulars modal
    public bool $viewParticularsModal = false;
    public ?TransactionTemplate $selectedTemplate = null;

    public function toggleActive($id)
    {
        $template = TransactionTemplate::findOrFail($id);
        $template->is_active = !$template->is_active;
        $template->save();

        $state = $template->is_active ? 'Active (available in Petty Cash Vouchers)' : 'Inactive (hidden from Petty Cash Vouchers)';
        $this->toast()->success('Status Updated', "Template is now {$state}.")->send();
    }

    public function approveTemplate($id)
    {
        $template = TransactionTemplate::with('transactionDetails')->findOrFail($id);

        if ($template->transactionDetails->isEmpty()) {
            $this->toast()->error('Approval Failed', 'Cannot approve a template without account particulars. Please edit the template first.')->send();
            return;
        }

        $template->status = 'FINAL';
        $template->approved_by = auth()->user()->emp_id ?? auth()->id();
        $template->approved_date = now();
        $template->is_active = 1;
        $template->save();

        $this->toast()->success('Approved', 'Template approved successfully and activated for Petty Cash Vouchers.')->send();
    }

    public function revertToDraft($id)
    {
        $template = TransactionTemplate::findOrFail($id);
        $template->status = 'DRAFT';
        $template->save();

        $this->toast()->warning('Reverted', 'Template reverted to Draft status.')->send();
    }

    public function openViewParticulars($id)
    {
        $this->selectedTemplate = TransactionTemplate::with([
            'businessUnit',
            'templateName',
            'transactionType',
            'transactionDetails.accountTitle',
            'approver'
        ])->findOrFail($id);

        $this->viewParticularsModal = true;
    }

    public function confirmDeleteTemplate(int $id)
    {
        $template = TransactionTemplate::withExists('pettyCashVouchers')->findOrFail($id);

        if ($template->petty_cash_vouchers_exists) {
            $this->toast()->error('Cannot Delete', 'This template is in use by Petty Cash Vouchers.')->send();
            return;
        }

        $name = $template->templateName?->template_name ?? 'Template #' . $template->id;

        $this->dialog()
            ->question(
                title: 'Delete Template?',
                description: "Are you sure you want to delete \"{$name}\"? All configured particulars will be removed. This cannot be undone."
            )
            ->confirm(
                text: 'Yes, Delete',
                method: 'deleteTemplate',
                params: $id
            )
            ->cancel('Cancel')
            ->send();
    }

    public function deleteTemplate(int $id)
    {
        $template = TransactionTemplate::withExists('pettyCashVouchers')->findOrFail($id);

        if ($template->petty_cash_vouchers_exists) {
            $this->toast()->error('Cannot Delete', 'This template is in use by Petty Cash Vouchers.')->send();
            return;
        }

        \App\Models\Accounting\TemplateDetail::where('template_id', $id)->delete();
        $template->delete();

        $this->toast()->success('Deleted', 'Template deleted successfully.')->send();
    }

    // ==========================================
    // TEMPLATE NAME METHODS
    // ==========================================

    public function openEditTemplateName(int $id)
    {
        $tn = TemplateName::findOrFail($id);
        $this->editTnId = $tn->id;
        $this->edit_template_name = $tn->template_name;
        $this->edit_tnCompany_id = $tn->company_id;
        $this->editTemplateNameModal = true;
    }

    public function updateTemplateName()
    {
        $this->validate([
            'edit_template_name' => ['required', 'string', 'max:255'],
            'edit_tnCompany_id'  => ['required', 'exists:companies,id'],
        ]);

        $tn = TemplateName::findOrFail($this->editTnId);
        $tn->update([
            'template_name' => trim($this->edit_template_name),
            'company_id'    => $this->edit_tnCompany_id,
        ]);

        $this->toast()->success('Updated', 'Template name updated successfully.')->send();
        $this->editTemplateNameModal = false;
    }

    public function toggleTemplateNameActive(int $id)
    {
        $tn = TemplateName::findOrFail($id);
        $tn->is_active = !$tn->is_active;
        $tn->save();

        $status = $tn->is_active ? 'activated' : 'deactivated';
        $this->toast()->success('Status Updated', "Template name {$status} successfully.")->send();
    }

    public function confirmDeleteTemplateName(int $id)
    {
        $tn = TemplateName::withExists('pettyCashVouchers')->findOrFail($id);
        if ($tn->petty_cash_vouchers_exists) {
            $this->toast()->error('Cannot Delete', 'This template name is in use by Petty Cash Vouchers.')->send();
            return;
        }

        $this->dialog()
            ->question(
                title: 'Delete Template Name?',
                description: "Are you sure you want to delete \"{$tn->template_name}\"? Any unused template configurations with this name will also be removed."
            )
            ->confirm(
                text: 'Yes, Delete',
                method: 'deleteTemplateName',
                params: $id
            )
            ->cancel('Cancel')
            ->send();
    }

    public function deleteTemplateName(int $id)
    {
        $tn = TemplateName::withExists('pettyCashVouchers')->findOrFail($id);
        if ($tn->petty_cash_vouchers_exists) {
            $this->toast()->error('Cannot Delete', 'This template name is in use by Petty Cash Vouchers.')->send();
            return;
        }

        $templates = TransactionTemplate::where('template_name_id', $id)->get();
        foreach ($templates as $t) {
            \App\Models\Accounting\TemplateDetail::where('template_id', $t->id)->delete();
            $t->delete();
        }
        $tn->delete();

        $this->toast()->success('Deleted', 'Template name deleted successfully.')->send();
    }

    // ==========================================
    // ACCOUNT TITLE METHODS
    // ==========================================

    public function openEditAccountTitle(int $id)
    {
        $title = AccountTitle::findOrFail($id);
        $this->editTitleId = $title->id;
        $this->edit_account_title = $title->account_title;
        $this->edit_account_code = $title->account_code;
        $this->edit_normal_balance = $title->normal_balance;
        $this->edit_titleCompany_id = $title->company_id;
        $this->edit_titleDescription = $title->description ?? '';
        $this->edit_parentTitleId = $title->parent_id;
        $this->edit_title_transaction_type = $title->transaction_type;
        $this->editAccountTitleModal = true;
    }

    public function updatedEditParentTitleId($value)
    {
        if ($value) {
            $service = AccountingService::getGeneratedCode($value);
            $this->edit_account_code = $service;
        }
    }

    public function updateAccountTitle()
    {
        $this->validate([
            'edit_account_title'          => ['required', 'string', 'max:155'],
            'edit_titleCompany_id'        => ['required', 'exists:companies,id'],
            'edit_parentTitleId'          => ['nullable', 'exists:actng_chart_of_accounts,id'],
            'edit_titleDescription'       => ['nullable', 'string', 'max:150'],
            'edit_normal_balance'         => ['required', 'in:debit,credit'],
            'edit_account_code'           => ['required', 'string', Rule::unique('actng_chart_of_accounts', 'account_code')->ignore($this->editTitleId)],
            'edit_title_transaction_type' => ['required', 'exists:actng_account_types,id']
        ]);

        $title = AccountTitle::findOrFail($this->editTitleId);
        $title->update([
            'account_title'    => trim($this->edit_account_title),
            'parent_id'        => $this->edit_parentTitleId,
            'account_code'     => trim($this->edit_account_code),
            'transaction_type' => $this->edit_title_transaction_type,
            'company_id'       => $this->edit_titleCompany_id,
            'normal_balance'   => $this->edit_normal_balance,
            'description'      => $this->edit_titleDescription,
        ]);

        $this->toast()->success('Updated', 'Account title updated successfully.')->send();
        $this->editAccountTitleModal = false;
    }

    public function toggleAccountTitleActive(int $id)
    {
        $title = AccountTitle::findOrFail($id);
        $title->is_active = !$title->is_active;
        $title->save();

        $status = $title->is_active ? 'activated' : 'deactivated';
        $this->toast()->success('Status Updated', "Account title {$status} successfully.")->send();
    }

    public function confirmDeleteAccountTitle(int $id)
    {
        $title = AccountTitle::withExists('pcvDetails')->withCount('children')->findOrFail($id);

        if ($title->pcv_details_exists) {
            $this->toast()->error('Cannot Delete', 'This account title is in use by Petty Cash Vouchers.')->send();
            return;
        }

        if ($title->children_count > 0) {
            $this->toast()->error('Cannot Delete', "Cannot delete because {$title->children_count} sub-accounts are linked to this title. Please reassign or delete them first.")->send();
            return;
        }

        $this->dialog()
            ->question(
                title: 'Delete Account Title?',
                description: "Are you sure you want to delete \"{$title->account_title} ({$title->account_code})\"? This action cannot be undone."
            )
            ->confirm(
                text: 'Yes, Delete',
                method: 'deleteAccountTitle',
                params: $id
            )
            ->cancel('Cancel')
            ->send();
    }

    public function deleteAccountTitle(int $id)
    {
        $title = AccountTitle::withExists('pcvDetails')->withCount('children')->findOrFail($id);

        if ($title->pcv_details_exists || $title->children_count > 0) {
            $this->toast()->error('Cannot Delete', 'This title cannot be deleted as it is in use or has child titles.')->send();
            return;
        }

        \App\Models\Accounting\TemplateDetail::where('account_title_id', $id)->delete();
        $title->delete();

        $this->toast()->success('Deleted', 'Account title deleted successfully.')->send();
    }

    // ==========================================
    // ACCOUNT TYPE METHODS
    // ==========================================

    public function openEditAccountType(int $id)
    {
        $type = AccountType::findOrFail($id);
        $this->editTypeId = $type->id;
        $this->edit_type_name = $type->type_name;
        $this->edit_atDescription = $type->description ?? '';
        $this->edit_atCompany_id = $type->company_id;
        $this->editAccountTypeModal = true;
    }

    public function updateAccountType()
    {
        $this->validate([
            'edit_type_name'    => ['required', 'string', 'max:255'],
            'edit_atCompany_id' => ['required', 'exists:companies,id'],
        ]);

        $type = AccountType::findOrFail($this->editTypeId);
        $type->update([
            'type_name'   => trim($this->edit_type_name),
            'company_id'  => $this->edit_atCompany_id,
            'description' => $this->edit_atDescription,
        ]);

        $this->toast()->success('Updated', 'Account type updated successfully.')->send();
        $this->editAccountTypeModal = false;
    }

    public function toggleAccountTypeActive(int $id)
    {
        $type = AccountType::findOrFail($id);
        $type->is_active = !$type->is_active;
        $type->save();

        $status = $type->is_active ? 'activated' : 'deactivated';
        $this->toast()->success('Status Updated', "Account type {$status} successfully.")->send();
    }

    public function confirmDeleteAccountType(int $id)
    {
        $type = AccountType::withExists([
            'pettyCashVouchers',
            'templates as templates_in_pcv_exists' => function ($q) {
                $q->whereHas('pettyCashVouchers');
            }
        ])->withCount('accountTitles')->findOrFail($id);

        if ($type->petty_cash_vouchers_exists || $type->templates_in_pcv_exists) {
            $this->toast()->error('Cannot Delete', 'This account type is in use by Petty Cash Vouchers.')->send();
            return;
        }

        if ($type->account_titles_count > 0) {
            $this->toast()->error('Cannot Delete', "Cannot delete because {$type->account_titles_count} account titles are assigned to this type. Please reassign them first.")->send();
            return;
        }

        $this->dialog()
            ->question(
                title: 'Delete Account Type?',
                description: "Are you sure you want to delete \"{$type->type_name}\"? This action cannot be undone."
            )
            ->confirm(
                text: 'Yes, Delete',
                method: 'deleteAccountType',
                params: $id
            )
            ->cancel('Cancel')
            ->send();
    }

    public function deleteAccountType(int $id)
    {
        $type = AccountType::withExists([
            'pettyCashVouchers',
            'templates as templates_in_pcv_exists' => function ($q) {
                $q->whereHas('pettyCashVouchers');
            }
        ])->withCount('accountTitles')->findOrFail($id);

        if ($type->petty_cash_vouchers_exists || $type->templates_in_pcv_exists || $type->account_titles_count > 0) {
            $this->toast()->error('Cannot Delete', 'This account type is in use and cannot be deleted.')->send();
            return;
        }

        $templates = TransactionTemplate::where('transaction_type', $id)->get();
        foreach ($templates as $t) {
            \App\Models\Accounting\TemplateDetail::where('template_id', $t->id)->delete();
            $t->delete();
        }
        $type->delete();

        $this->toast()->success('Deleted', 'Account type deleted successfully.')->send();
    }


    public function saveTemplateName(AccountingService $service)
    {
        $this->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'tnCompany_id' => ['required', 'exists:companies,id'],
        ]);

        try {
           $result = $service->createTemplateName([
                'template_name' => $this->template_name,
                'company_id' => $this->tnCompany_id,
                'is_active' => true,
                'created_by' => auth()->user()->emp_id,
            ]);

            $this->toast()->success('Success', 'Template name created successfully.')->send();
            $this->templateName = false;
            $this->reset('form');
        } catch (QueryException $e) {
            Log::error('Error creating template name: ' . $e->getMessage());
            $this->toast()->error('Error', 'Failed to create template name. Please try again.')->send();
        }
    }

    public function saveAccountType(AccountingService $service)
    {
        $this->validate([
            'type_name' => ['required', 'string', 'max:255'],
            'atCompany_id' => ['required', 'exists:companies,id'],
        ]);

        try {
           $result = $service->createAccountType([
                'type_name' => $this->type_name,
                'company_id' => $this->atCompany_id,
                'description' => $this->atDescription,
                'is_active' => true,
                'created_by' => auth()->user()->emp_id,
            ]);

            $this->toast()->success('Success', 'Account type created successfully.')->send();
            $this->accountTypeModal = false;
            $this->reset('form');
        } catch (QueryException $e) {
            Log::error('Error creating account type: ' . $e->getMessage());
            $this->toast()->error('Error', 'Failed to create account type. Please try again.')->send();
        }
    }

    public function saveAccountTitle(AccountingService $service)
{
    $this->validate([
        'account_title'                 => ['required', 'string', 'max:155'],
        'titleCompany_id'               => ['required', 'exists:companies,id'],
        'parentTitleId'                 => ['nullable', 'exists:actng_chart_of_accounts,id'],
        'titleDescription'              => ['nullable', 'string', 'max:150'], // Matches your Livewire property name
        'normal_balance'                => ['required', 'in:debit,credit'],
        'account_code'                  => ['required', 'string', 'unique:actng_chart_of_accounts,account_code'],
        'title_transaction_type'        => ['required', 'exists:actng_account_types,id']
    ]);

    try {
        $result = $service->createTitle([
            'account_title'    => $this->account_title,
            'parent_id'        => $this->parentTitleId,
            'account_code'     => $this->account_code,
            'transaction_type' => $this->title_transaction_type,
            'company_id'       => $this->titleCompany_id,
            'normal_balance'   => $this->normal_balance,
            'description'      => $this->titleDescription, // Maps property value to the service database field
            'created_by'       => Auth::user()->emp_id,
        ]);
        
        $this->toast()->success('Success', 'Account type created successfully.')->send();
        $this->accountTitleModal = false;

    } catch (\Exception $e) {
        $this->accountTitleModal = false;

        $this->toast()->error('Error', 'Failed to take action. Please try again. error : ' . $e)->send();
    }
}

    public function updatedParentTitleId($value)
    {
        $service = AccountingService::getGeneratedCode($value);
        $this->account_code  = $service;
    }

    // 1. Static headers defined as protected/public properties or config methods
    public array $accountTemplateHeaders = [
        ['index' => 'is_active', 'label' => 'Status', 'sortable' => false],
        ['index' => 'status', 'label' => 'Approval', 'sortable' => false],
        ['index' => 'template_name_id', 'label' => 'Template Name'],
        ['index' => 'module_type', 'label' => 'Module'],
        ['index' => 'transaction_type', 'label' => 'Transaction Type', 'sortable' => false],
        ['index' => 'company_id', 'label' => 'Business Unit'],
        ['index' => 'action', 'label' => 'Action', 'sortable' => false],
    ];

    public array $templateNameHeaders = [
        ['index' => 'is_active', 'label' => 'status'],
        ['index' => 'template_name', 'label' => 'template name'],
        ['index' => 'company_id', 'label' => 'Business Unit'],
        ['index' => 'action', 'label' => 'Action', 'sortable' => false],
    ];

    public array $accountTitleHeaders = [
        ['index' => 'is_active', 'label' => 'status'],
        ['index' => 'account_code', 'label' => 'account code'],
        ['index' => 'account_title', 'label' => 'title name'],
        ['index' => 'normal_balance', 'label' => 'normal balance'],
        ['index' => 'company_id', 'label' => 'Business Unit'],
        ['index' => 'action', 'label' => 'Action', 'sortable' => false],
    ];

    public array $accountTypeHeaders = [
        ['index' => 'is_active', 'label' => 'status'],
        ['index' => 'type_name', 'label' => 'type name'],
        ['index' => 'company_id', 'label' => 'Business Unit'],
        ['index' => 'action', 'label' => 'Action', 'sortable' => false],
    ];

    // 2. Computed properties for lazy-loading and caching data per tab
    #[Computed]
    public function accountTemplateRows()
    {
        if ($this->tabNum != 1) return null;

        return TransactionTemplate::query()
            ->with(['businessUnit', 'templateName', 'transactionType', 'transactionDetails.accountTitle', 'approver'])
            ->withExists('pettyCashVouchers')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('templateName', function ($sub) {
                        $sub->where('template_name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('module_type', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($this->quantity, pageName: 'template_page')
            ->withQueryString();
    }

    #[Computed]
    public function templateNameRows()
    {
        if ($this->tabNum != 1) return null;

        return TemplateName::query()
            ->orderBy('template_name')
            ->with('businessUnit')
            ->withExists('pettyCashVouchers')
            ->when($this->searchTemplateName, function ($query) {
                $query->where('template_name', 'like', '%' . $this->searchTemplateName . '%');
            })
            ->paginate($this->quantityTemplateName, pageName: 'name_page')
            ->withQueryString();
    }

    #[Computed]
    public function accountTitleRows()
    {
        if ($this->tabNum != 2) return null;

        return AccountTitle::query()
            ->orderBy('account_code')
            ->with(['businessUnit', 'accountType'])
            ->withExists('pcvDetails')
            ->when($this->searchTitle, function ($query) {
                $query->where('account_title', 'like', '%' . $this->searchTitle . '%')
                      ->orWhere('account_code', 'like', '%' . $this->searchTitle . '%');
            })
            ->paginate($this->quantityTitle, pageName: 'title_page')
            ->withQueryString();
    }

    #[Computed]
    public function accountTypeRows()
    {
        if ($this->tabNum != 3) return null;

        return AccountType::query()
            ->orderBy('type_name')
            ->with('businessUnit')
            ->withExists([
                'pettyCashVouchers',
                'templates as templates_in_pcv_exists' => function ($q) {
                    $q->whereHas('pettyCashVouchers');
                }
            ])
            ->when($this->searchType, function ($query) {
                $query->where('type_name', 'like', '%' . $this->searchType . '%');
            })
            ->paginate($this->quantityType, pageName: 'type_page')
            ->withQueryString();
    }


};
?>

<div class="flex h-[calc(90vh-4rem)] overflow-hidden bg-slate-50 dark:bg-slate-900">
    {{-- Sidebar / Tab List --}}
    <aside class="hidden md:flex w-72 flex-shrink-0 flex-col border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Chart of Account Management</h2>
        </div>
        
        <div class="flex-1 overflow-y-auto py-3 px-3 space-y-1">
            {{-- Tab 1: Template --}}
            <div wire:click="$set('tabNum', 1)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 1
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>Accounting Template</span>
            </div>

            {{-- Tab 2: Room Account title --}}
            <div wire:click="$set('tabNum', 2)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 2
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Account Titles</span>
            </div>

            {{-- Tab 3: Account types --}}
            <div wire:click="$set('tabNum', 3)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 3
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                <span>Account Types</span>
            </div>
        </div>
    </aside>

    {{-- Main Content Area --}}
    <div class="flex-1 flex flex-col min-w-0 bg-white dark:bg-slate-950 overflow-y-auto">
        <div class=" w-full mx-auto px-6 py-8 space-y-6">
            @if($tabNum === 1)
                <x-ts-card shadowless >
                    <x-ts-tab wire:model="tab">
                        
                        <x-ts-tab.items tab="TEMPLATE SUMMARY">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Transaction & PCV Templates</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Configure debit and credit particulars used for Petty Cash Vouchers.</p>
                                    </div>
                                    <x-ts-button icon="plus" :href="route('accounting.template-create')">
                                        New PCV Template
                                    </x-ts-button>
                                </div>
                                <x-ts-table :headers="$accountTemplateHeaders" :rows="$this->accountTemplateRows" compact paginate loading striped expandable filter > 
                                @interact('column_is_active', $row)
                                    @if($row->is_active)
                                        <x-ts-badge text="ACTIVE" color="green" light />
                                    @else
                                        <x-ts-badge text="INACTIVE" color="rose" light />
                                    @endif
                                @endinteract
                                @interact('column_status', $row)
                                    @if($row->status === 'FINAL')
                                        <x-ts-badge text="APPROVED" color="emerald" icon="check-circle" light />
                                    @else
                                        <x-ts-badge text="DRAFT" color="amber" icon="document-text" light />
                                    @endif
                                @endinteract
                                @interact('column_template_name_id', $row)
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ $row->templateName?->template_name ?? 'Unknown' }}
                                    </div>
                                    @if($row->description)
                                        <div class="text-xs text-slate-400 truncate max-w-xs">{{ $row->description }}</div>
                                    @endif
                                @endinteract
                                @interact('column_company_id', $row)
                                    {{ $row->businessUnit?->company_name ?? 'Unknown' }}
                                @endinteract
                                @interact('column_module_type', $row)
                                    <x-ts-badge :text="$row->module_type ?? 'PCV'" color="blue" light />
                                @endinteract
                                @interact('column_transaction_type', $row)
                                    {{ $row->transactionType?->type_name ?? 'Unknown' }}
                                @endinteract
                                @interact('column_action', $row)
                                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                    <x-ts-dropdown.items text="View Particulars" icon="eye" wire:click="openViewParticulars({{ $row->id }})" />
                                    <x-ts-dropdown.items text="Edit Template" icon="pencil-square" :href="route('accounting.template-edit', $row->id)" />
                                    @if($row->is_active)
                                        <x-ts-dropdown.items text="Set Inactive" icon="power" color="rose" separator wire:click="toggleActive({{ $row->id }})" />
                                    @else
                                        <x-ts-dropdown.items text="Set Active (Enable for PCV)" icon="power" color="green" separator wire:click="toggleActive({{ $row->id }})" />
                                    @endif
                                    @if($row->status !== 'FINAL')
                                        <x-ts-dropdown.items text="Approve Template" icon="check-badge" color="emerald" separator wire:click="approveTemplate({{ $row->id }})" />
                                    @else
                                        <x-ts-dropdown.items text="Revert to Draft" icon="arrow-uturn-left" color="amber" separator wire:click="revertToDraft({{ $row->id }})" />
                                    @endif
                                    @if(!$row->petty_cash_vouchers_exists)
                                        <x-ts-dropdown.items text="Delete" icon="trash" color="rose" separator wire:click="confirmDeleteTemplate({{ $row->id }})" />
                                    @endif
                                </x-ts-dropdown>
                                @endinteract
                                @interact('sub_table', $row)
                                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl space-y-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 border-b border-slate-200 dark:border-slate-800 pb-2">
                                            <div>
                                                <span class="font-medium text-slate-700 dark:text-slate-300">Description:</span>
                                                {{ $row->description ?: 'No description provided.' }}
                                            </div>
                                            @if($row->approved_by)
                                                <div>
                                                    <span class="font-medium text-slate-700 dark:text-slate-300">Approved by:</span>
                                                    {{ $row->approver?->full_name ?? 'Employee #' . $row->approved_by }}
                                                    @if($row->approved_date)
                                                        ({{ \Carbon\Carbon::parse($row->approved_date)->format('M d, Y h:i A') }})
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <h5 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Particulars ({{ $row->transactionDetails->count() }})</h5>
                                                <div class="flex gap-2">
                                                    <x-ts-badge text="Debit: {{ $row->transactionDetails->where('type', 'DEBIT')->count() }}" color="green" light />
                                                    <x-ts-badge text="Credit: {{ $row->transactionDetails->where('type', 'CREDIT')->count() }}" color="blue" light />
                                                </div>
                                            </div>
                                            @if($row->transactionDetails->isEmpty())
                                                <p class="text-xs text-slate-400 italic">No particulars configured yet. Edit template to add debit and credit titles.</p>
                                            @else
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div class="p-2.5 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40">
                                                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-1.5 flex items-center gap-1.5">
                                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                            Debit Particulars
                                                        </div>
                                                        <div class="space-y-1">
                                                            @forelse($row->transactionDetails->where('type', 'DEBIT') as $detail)
                                                                <div class="text-xs flex items-center justify-between text-slate-700 dark:text-slate-300">
                                                                    <span>{{ $detail->accountTitle?->account_title ?? 'Title #' . $detail->account_title_id }}</span>
                                                                    <span class="font-mono text-emerald-700 dark:text-emerald-400 text-[11px]">{{ $detail->accountTitle?->account_code ?? '' }}</span>
                                                                </div>
                                                            @empty
                                                                <div class="text-xs text-slate-400 italic">No debit particulars</div>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                    <div class="p-2.5 rounded-lg bg-blue-50/60 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40">
                                                        <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 mb-1.5 flex items-center gap-1.5">
                                                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                            Credit Particulars
                                                        </div>
                                                        <div class="space-y-1">
                                                            @forelse($row->transactionDetails->where('type', 'CREDIT') as $detail)
                                                                <div class="text-xs flex items-center justify-between text-slate-700 dark:text-slate-300">
                                                                    <span>{{ $detail->accountTitle?->account_title ?? 'Title #' . $detail->account_title_id }}</span>
                                                                    <span class="font-mono text-blue-700 dark:text-blue-400 text-[11px]">{{ $detail->accountTitle?->account_code ?? '' }}</span>
                                                                </div>
                                                            @empty
                                                                <div class="text-xs text-slate-400 italic">No credit particulars</div>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endinteract
                                </x-ts-table>
                        </x-ts-tab.items>
                        <x-ts-tab.items tab="TEMPLATE NAMES">
                            <div class="flex justify-end mb-3">
                                <x-ts-button icon="plus" wire:click="$toggle('templateName')">Add tamplate Name</x-ts-button>
                            </div>
                            <x-ts-table :headers="$templateNameHeaders" :rows="$this->templateNameRows" compact paginate loading striped expandable :filter="['quantity' => 'quantityTemplateName', 'search' => 'searchTemplateName']">
                                @interact('column_is_active', $row)
                                    @if($row->is_active)
                                        <x-ts-badge text="ACTIVE" color="green" />
                                    @else
                                        <x-ts-badge text="INACTIVE" color="rose" />
                                    @endif
                                @endinteract
                                @interact('column_company_id', $row)
                                    {{ $row->businessUnit?->company_name ?? 'Unknown' }}
                                @endinteract
                                @interact('column_action', $row)
                                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                        <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="openEditTemplateName({{ $row->id }})" />
                                        @if($row->is_active)
                                            <x-ts-dropdown.items text="Deactivate" icon="power" color="rose" separator wire:click="toggleTemplateNameActive({{ $row->id }})" />
                                        @else
                                            <x-ts-dropdown.items text="Activate" icon="power" color="green" separator wire:click="toggleTemplateNameActive({{ $row->id }})" />
                                        @endif
                                        @if(!$row->petty_cash_vouchers_exists)
                                            <x-ts-dropdown.items text="Delete" icon="trash" color="rose" separator wire:click="confirmDeleteTemplateName({{ $row->id }})" />
                                        @endif
                                    </x-ts-dropdown>
                                @endinteract
                            </x-ts-table>
                        </x-ts-tab.items>
                    </x-ts-tab>
                </x-ts-card>
            @elseif($tabNum === 2)
               <x-ts-card shadowless borderless>
                 <div class="flex justify-end mb-3">
                     <x-ts-button sm icon="plus" position="left" wire:click="$toggle('accountTitleModal')">Add Title</x-ts-button>
                 </div>
                 <x-ts-table :headers="$accountTitleHeaders" :rows="$this->accountTitleRows" compact paginate loading striped expandable :filter="['quantity' => 'quantityTitle', 'search' => 'searchTitle']"  >
                    @interact('column_is_active', $row)
                        @if($row->is_active == 1)
                            <x-ts-badge text="ACTIVE" color="green" />
                        @else
                            <x-ts-badge text="INACTIVE" color="rose" />
                        @endif
                    @endinteract
                    @interact('column_company_id', $row)
                        {{ $row->businessUnit?->company_name ?? 'Unknown' }}
                    @endinteract
                    @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="openEditAccountTitle({{ $row->id }})" />
                        @if($row->is_active)
                            <x-ts-dropdown.items text="Deactivate" icon="power" color="rose" separator wire:click="toggleAccountTitleActive({{ $row->id }})" />
                        @else
                            <x-ts-dropdown.items text="Activate" icon="power" color="green" separator wire:click="toggleAccountTitleActive({{ $row->id }})" />
                        @endif
                        @if(!$row->pcv_details_exists)
                            <x-ts-dropdown.items text="Delete" icon="trash" color="rose" separator wire:click="confirmDeleteAccountTitle({{ $row->id }})" />
                        @endif
                    </x-ts-dropdown>
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-textarea resize-auto value="{{ $row->description }}" readonly/>
                        @endinteract
                    </x-ts-table>
                </x-ts-card>
            @elseif($tabNum === 3)
                <x-ts-card shadowless borderless>
                    <div class="flex justify-end mb-3">
                        <x-ts-button sm icon="plus" position="left" wire:click="$toggle('accountTypeModal')">Add Account Type</x-ts-button>
                    </div>
                        <x-ts-table :headers="$accountTypeHeaders" :rows="$this->accountTypeRows" compact paginate loading striped expandable :filter="['quantity' => 'quantityType', 'search' => 'searchType']"  >
                            @interact('column_is_active', $row)
                                @if($row->is_active)
                                    <x-ts-badge text="ACTIVE" color="green" />
                                @else
                                    <x-ts-badge text="INACTIVE" color="rose" />
                                @endif
                            @endinteract
                            @interact('column_company_id', $row)
                                {{ $row->businessUnit?->company_name ?? 'Unknown' }}
                            @endinteract
                            @interact('column_action', $row)
                            @php
                                $typeUsedInPcv = $row->petty_cash_vouchers_exists || $row->templates_in_pcv_exists;
                            @endphp
                            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="openEditAccountType({{ $row->id }})" />
                                @if($row->is_active)
                                    <x-ts-dropdown.items text="Deactivate" icon="power" color="rose" separator wire:click="toggleAccountTypeActive({{ $row->id }})" />
                                @else
                                    <x-ts-dropdown.items text="Activate" icon="power" color="green" separator wire:click="toggleAccountTypeActive({{ $row->id }})" />
                                @endif
                                @if(!$typeUsedInPcv)
                                    <x-ts-dropdown.items text="Delete" icon="trash" color="rose" separator wire:click="confirmDeleteAccountType({{ $row->id }})" />
                                @endif
                            </x-ts-dropdown>
                            @endinteract
                            @interact('sub_table', $row)
                                <x-ts-textarea resize-auto value="{{ $row->description }}" readonly/>
                            @endinteract
                        </x-ts-table>
                </x-ts-card>
            @endif

            <x-ts-modal wire="templateName" center title="New Template Name">
                <form wire:submit.prevent="saveTemplateName">
                        <x-ts-input id="template_name" wire:model.defer="template_name" required label="Template Name"/>
                        <x-ts-select.styled label="Business Unit" id="company_id" wire:model.defer="tnCompany_id" 
                            :request="route('api.company.active')"
                             select="value:id"

                        required />
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('templateName', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="saveTemplateName">Save</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>


            <x-ts-modal wire="accountTitleModal" center title="New Account Title">
                <form wire:submit.prevent="saveAccountTitle">
                    
                        <div class="grid grid-cols-1 gap-3">
                            <div wire:key="titleCompanySelect">
                                <x-ts-select.styled label="Business Unit" id="company_id" wire:model.live="titleCompany_id" 
                                        :request="route('api.company.active')"
                                         select="value:id"
                                    required />
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <x-ts-input id="account_title" wire:model.defer="account_title" required label="Account Title"/>
                                <div wire:key="title-{{ $titleCompany_id}}">
                                    <x-ts-select.styled label="Parent Title" id="parentTitleId" wire:model.live="parentTitleId" 
                                        :request="route('api-accounting.active-titles', ['company_id' => $titleCompany_id])"
                                         select="value:id"
                                    />
                                </div>
                                <x-ts-input id="account_code" wire:model.defer="account_code" required label="Account Code"/>
                                <x-ts-select.styled label="Normal Balance" id="normal_balance" wire:model="normal_balance" 
                                    :options="[ ['name' => 'Debit', 'id' => 'debit'], ['name' => 'Credit', 'id' => 'credit'] ]"
                                     select="label:name|value:id"
                                required />
                            </div>
                            <div wire:key="transaction-type-{{ $titleCompany_id}}">
                                    <x-ts-select.styled label="Transaction Type" wire:model="title_transaction_type" 
                                        :request="route('api.get.active-account-type', ['company_id' => $titleCompany_id])"
                                         select="value:id|label:type_name"
                                    />
                                </div>
                            <x-ts-textarea id="description" wire:model.defer="titleDescription" label="Description"/>
                        </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('accountTitleModal', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="saveAccountTitle">Save</x-ts-button>
                    </div>
                </form>

            </x-ts-modal>


            <x-ts-modal wire="accountTypeModal" center title="New Account Type">
                <form wire:submit.prevent="saveAccountType">
                        <div class="grid grid-cols-1 gap-4">
                            <x-ts-input id="type_name" wire:model.defer="type_name" required label="Account Type Name"/>
                            <x-ts-textarea id="description" wire:model.defer="atDescription" label="Description"/>
                            <x-ts-select.styled label="Business Unit" id="company_id" wire:model.defer="atCompany_id" 
                                :request="route('api.company.active')"
                                 select="value:id"
                            required />
                        </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('accountTypeModal', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="saveAccountType">Save</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>

            {{-- VIEW TEMPLATE PARTICULARS MODAL --}}
            <x-ts-modal wire="viewParticularsModal" title="Petty Cash Voucher Template Particulars" size="3xl">
                @if($selectedTemplate)
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-xs">
                            <div>
                                <span class="text-slate-400 block font-medium">Template Name</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $selectedTemplate->templateName?->template_name ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Business Unit</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $selectedTemplate->businessUnit?->company_name ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Module / Type</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $selectedTemplate->module_type ?? 'PCV' }} / {{ $selectedTemplate->transactionType?->type_name ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Status & PCV Visibility</span>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <x-ts-badge :text="$selectedTemplate->status" :color="$selectedTemplate->status === 'FINAL' ? 'emerald' : 'amber'" light />
                                    <x-ts-badge :text="$selectedTemplate->is_active ? 'ACTIVE' : 'INACTIVE'" :color="$selectedTemplate->is_active ? 'green' : 'rose'" light />
                                </div>
                            </div>
                        </div>

                        @if($selectedTemplate->description)
                            <div class="text-xs text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800/60 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Description: </span>
                                {{ $selectedTemplate->description }}
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Configured Particulars</h4>
                                <div class="flex gap-2">
                                    <x-ts-badge text="Debit: {{ $selectedTemplate->transactionDetails->where('type', 'DEBIT')->count() }}" color="green" light />
                                    <x-ts-badge text="Credit: {{ $selectedTemplate->transactionDetails->where('type', 'CREDIT')->count() }}" color="blue" light />
                                </div>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Entry Type</th>
                                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Account Code</th>
                                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Account Title</th>
                                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Normal Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                                        @forelse($selectedTemplate->transactionDetails as $detail)
                                            <tr>
                                                <td class="px-4 py-2.5">
                                                    @if($detail->type === 'DEBIT')
                                                        <x-ts-badge text="DEBIT" color="green" light />
                                                    @else
                                                        <x-ts-badge text="CREDIT" color="blue" light />
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 font-mono text-slate-800 dark:text-slate-200 font-semibold">
                                                    {{ $detail->accountTitle?->account_code ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-slate-800 dark:text-slate-200">
                                                    {{ $detail->accountTitle?->account_title ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-2.5 uppercase text-slate-500">
                                                    {{ $detail->accountTitle?->normal_balance ?? 'N/A' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-6 text-center text-slate-400 italic">No particulars configured for this template.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-2">
                            <div>
                                @if(!$selectedTemplate->is_active)
                                    <span class="text-xs text-rose-500 font-medium">⚠️ This template is inactive and hidden from Petty Cash Voucher particulars.</span>
                                @else
                                    <span class="text-xs text-emerald-600 font-medium">✓ Active and available when creating Petty Cash Vouchers.</span>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <x-ts-button color="gray" wire:click="$set('viewParticularsModal', false)">Close</x-ts-button>
                                <x-ts-button color="green" icon="pencil-square" :href="route('accounting.template-edit', $selectedTemplate->id)">Edit Template</x-ts-button>
                            </div>
                        </div>
                    </div>
                @endif
            </x-ts-modal>

            {{-- EDIT TEMPLATE NAME MODAL --}}
            <x-ts-modal wire="editTemplateNameModal" center title="Edit Template Name">
                <form wire:submit.prevent="updateTemplateName">
                    <div class="space-y-4">
                        <x-ts-input id="edit_template_name" wire:model.defer="edit_template_name" required label="Template Name"/>
                        <div wire:key="edit-tn-company-{{ $editTnId }}">
                            <x-ts-select.styled label="Business Unit" id="edit_tn_company_id" wire:model.defer="edit_tnCompany_id" 
                                :request="route('api.company.active')"
                                select="value:id"
                                required />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('editTemplateNameModal', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="updateTemplateName">Save Changes</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>

            {{-- EDIT ACCOUNT TITLE MODAL --}}
            <x-ts-modal wire="editAccountTitleModal" center title="Edit Account Title">
                <form wire:submit.prevent="updateAccountTitle">
                    <div class="grid grid-cols-1 gap-3">
                        <div wire:key="edit-titleCompanySelect-{{ $editTitleId }}">
                            <x-ts-select.styled label="Business Unit" id="edit_company_id" wire:model.live="edit_titleCompany_id" 
                                    :request="route('api.company.active')"
                                    select="value:id"
                                required />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <x-ts-input id="edit_account_title" wire:model.defer="edit_account_title" required label="Account Title"/>
                            <div wire:key="edit-title-{{ $edit_titleCompany_id }}-{{ $editTitleId }}">
                                <x-ts-select.styled label="Parent Title" id="edit_parentTitleId" wire:model.live="edit_parentTitleId" 
                                    :request="route('api-accounting.active-titles', ['company_id' => $edit_titleCompany_id])"
                                    select="value:id"
                                />
                            </div>
                            <x-ts-input id="edit_account_code" wire:model.defer="edit_account_code" required label="Account Code"/>
                            <x-ts-select.styled label="Normal Balance" id="edit_normal_balance" wire:model="edit_normal_balance" 
                                :options="[ ['name' => 'Debit', 'id' => 'debit'], ['name' => 'Credit', 'id' => 'credit'] ]"
                                select="label:name|value:id"
                            required />
                        </div>
                        <div wire:key="edit-transaction-type-{{ $edit_titleCompany_id }}-{{ $editTitleId }}">
                            <x-ts-select.styled label="Transaction Type" wire:model="edit_title_transaction_type" 
                                :request="route('api.get.active-account-type', ['company_id' => $edit_titleCompany_id])"
                                select="value:id|label:type_name"
                                required
                            />
                        </div>
                        <x-ts-textarea id="edit_description" wire:model.defer="edit_titleDescription" label="Description"/>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('editAccountTitleModal', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="updateAccountTitle">Save Changes</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>

            {{-- EDIT ACCOUNT TYPE MODAL --}}
            <x-ts-modal wire="editAccountTypeModal" center title="Edit Account Type">
                <form wire:submit.prevent="updateAccountType">
                    <div class="grid grid-cols-1 gap-4">
                        <x-ts-input id="edit_type_name" wire:model.defer="edit_type_name" required label="Account Type Name"/>
                        <x-ts-textarea id="edit_atDescription" wire:model.defer="edit_atDescription" label="Description"/>
                        <div wire:key="edit-at-company-{{ $editTypeId }}">
                            <x-ts-select.styled label="Business Unit" id="edit_atCompany_id" wire:model.defer="edit_atCompany_id" 
                                :request="route('api.company.active')"
                                select="value:id"
                                required />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-ts-button type="button" color="gray" wire:click="$set('editAccountTypeModal', false)">Cancel</x-ts-button>
                        <x-ts-button type="submit" color="green" loading="updateAccountType">Save Changes</x-ts-button>
                    </div>
                </form>
            </x-ts-modal>


        </div>
    </div>
</div>