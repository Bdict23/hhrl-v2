<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Accounting\AccountTitle;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\TemplateName;
use App\Models\Accounting\TransactionTemplate;
use App\Models\Accounting\TemplateDetail;
use App\Models\Business\Company;
use App\Models\Business\Employee;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    use WithPagination;
    use Interactions;

    // Template identity & mode
    public ?int $templateId = null;
    public string $mode = 'create'; // 'create' | 'edit'
    public string $status = 'DRAFT'; // 'DRAFT' | 'FINAL'
    public ?string $approved_date = null;
    public ?string $approver_name = null;

    // Form fields
    public ?int $company_id = null;
    public ?int $transaction_type = null;
    public ?int $template_name_id = null;
    public ?int $approver = null;
    public string $description = '';
    public string $module_type = 'PCV'; // Default PCV / PCV
    public bool $for_requisition = true;
    public bool $is_active = true;

    // Modals
    public bool $accountTitlesModal = false;
    public bool $selectCompanyModal = false;
    public bool $createTemplateNameModal = false;
    public string $new_template_name = '';

    // Account title filters
    public string $searchTitle = '';
    public ?string $filterAccountType = null;
    public string $filterNormalBalance = 'all'; // 'all', 'debit', 'credit'
    public int $quantityTitle = 10;

    // Selected particulars
    // Each item: ['account_title_id', 'account_title', 'account_code', 'normal_balance', 'type' => 'DEBIT'|'CREDIT']
    public array $particularRows = [];

    /**
     * Mount lifecycle hook - handles both create and edit.
     */
    public function mount($id = null)
    {
        $id = $id ?? request()->route('id') ?? request()->query('id');

        if ($id) {
            $this->loadTemplate((int) $id);
        }
    }

    /**
     * Load an existing template for editing.
     */
    public function loadTemplate(int $id)
    {
        $template = TransactionTemplate::with([
            'businessUnit',
            'templateName',
            'transactionType',
            'transactionDetails.accountTitle',
            'approver',
        ])->findOrFail($id);

        $this->templateId = $template->id;
        $this->mode = 'edit';
        $this->company_id = $template->company_id;
        $this->transaction_type = $template->transaction_type;
        $this->template_name_id = $template->template_name_id;
        $this->approver = $template->approved_by;
        $this->description = $template->description ?? '';
        $this->module_type = $template->module_type ?? 'PCV';
        $this->for_requisition = (bool) $template->for_requisition;
        $this->is_active = (bool) $template->is_active;
        $this->status = $template->status ?? 'DRAFT';
        $this->approved_date = $template->approved_date ? \Carbon\Carbon::parse($template->approved_date)->format('M d, Y h:i A') : null;
        $this->approver_name = $template->approver?->full_name;

        $this->particularRows = [];
        foreach ($template->transactionDetails as $detail) {
            $this->particularRows[] = [
                'account_title_id' => $detail->account_title_id,
                'account_title'    => $detail->accountTitle?->account_title ?? 'Unknown Title',
                'account_code'     => $detail->accountTitle?->account_code ?? 'N/A',
                'normal_balance'   => strtolower($detail->accountTitle?->normal_balance ?? 'debit'),
                'type'             => strtoupper($detail->type ?? 'DEBIT'),
            ];
        }
    }

    /**
     * Reset dependent fields when company changes.
     */
    public function updatedCompanyId()
    {
        if ($this->mode === 'create') {
            $this->transaction_type = null;
            $this->template_name_id = null;
            $this->approver = null;
            $this->particularRows = [];
        }

        $this->resetPage('title_page');
    }

    public function updatedSearchTitle()
    {
        $this->resetPage('title_page');
    }

    public function updatedFilterAccountType()
    {
        $this->resetPage('title_page');
    }

    public function updatedFilterNormalBalance()
    {
        $this->resetPage('title_page');
    }

    public function updatedQuantityTitle()
    {
        $this->resetPage('title_page');
    }

    /**
     * Open account title selection.
     */
    public function selectTitle()
    {
        if (empty($this->company_id)) {
            $this->selectCompanyModal = true;
            return;
        }

        $this->accountTitlesModal = true;
    }

    /**
     * Proceed after selecting a company from prompt modal.
     */
    public function proceed()
    {
        if (empty($this->company_id)) {
            $this->addError('company_id', 'Please select a business unit first.');
            return;
        }

        $this->resetErrorBag('company_id');
        $this->selectCompanyModal = false;
        $this->accountTitlesModal = true;
    }

    /**
     * Get account types for the current company (filter in modal).
     */
    #[Computed]
    public function companyAccountTypes()
    {
        if (!$this->company_id) {
            return collect();
        }

        return AccountType::where('company_id', $this->company_id)
            ->where('is_active', true)
            ->orderBy('type_name')
            ->get();
    }

    /**
     * Query account titles with filters.
     */
    #[Computed]
    public function accountTitleRows()
    {
        if (!$this->company_id) {
            return null;
        }

        return AccountTitle::query()
            ->where('company_id', $this->company_id)
            ->where('is_active', true)
            ->when(filled($this->searchTitle), function ($query) {
                $term = '%' . trim($this->searchTitle) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('account_title', 'like', $term)
                        ->orWhere('account_code', 'like', $term);
                });
            })
            ->when(filled($this->filterAccountType), function ($query) {
                $query->where('transaction_type', $this->filterAccountType);
            })
            ->when(filled($this->filterNormalBalance) && $this->filterNormalBalance !== 'all', function ($query) {
                $query->where('normal_balance', strtolower($this->filterNormalBalance));
            })
            ->with(['accountType'])
            ->orderBy('account_code')
            ->paginate(max(5, min(100, (int) $this->quantityTitle)), ['*'], 'title_page');
    }

    /**
     * Add or update an account title as particular (DEBIT or CREDIT).
     */
    public function addParticular($id, $type = 'DEBIT')
    {
        $title = AccountTitle::query()
            ->where('company_id', $this->company_id)
            ->findOrFail($id);

        $type = strtoupper($type);

        // Check if already in particulars
        foreach ($this->particularRows as $index => $row) {
            if ($row['account_title_id'] == $title->id) {
                $this->particularRows[$index]['type'] = $type;
                $this->toast()->info('Updated', "{$title->account_title} type switched to {$type}.")->send();
                return;
            }
        }

        $this->particularRows[] = [
            'account_title_id' => $title->id,
            'account_title'    => $title->account_title,
            'account_code'     => $title->account_code,
            'normal_balance'   => strtolower($title->normal_balance ?? 'debit'),
            'type'             => $type,
        ];

        $this->toast()->success('Particular Added', "Added {$title->account_title} as {$type}.")->send();
    }

    /**
     * Set type of an already selected particular.
     */
    public function setParticularType($index, $type)
    {
        if (isset($this->particularRows[$index])) {
            $this->particularRows[$index]['type'] = strtoupper($type);
        }
    }

    /**
     * Remove selected particular.
     */
    public function removeParticular($index)
    {
        if (isset($this->particularRows[$index])) {
            $removed = $this->particularRows[$index]['account_title'] ?? 'Particular';
            unset($this->particularRows[$index]);
            $this->particularRows = array_values($this->particularRows);
            $this->toast()->success('Removed', "{$removed} removed from particulars.")->send();
        }
    }

    /**
     * Clear all selected particulars.
     */
    public function clearAllParticulars()
    {
        $this->particularRows = [];
        $this->toast()->info('Cleared', 'All particulars removed.')->send();
    }

    /**
     * Quick create template name.
     */
    public function saveNewTemplateName()
    {
        if (!$this->company_id) {
            $this->toast()->error('Error', 'Please select a Business Unit first.')->send();
            return;
        }

        $this->validate([
            'new_template_name' => ['required', 'string', 'max:255'],
        ]);

        $created = TemplateName::create([
            'template_name' => trim($this->new_template_name),
            'company_id'    => $this->company_id,
            'created_by'    => auth()->user()->emp_id ?? auth()->id(),
            'is_active'     => true,
        ]);

        $this->template_name_id = $created->id;
        $this->new_template_name = '';
        $this->createTemplateNameModal = false;

        $this->toast()->success('Created', 'New template name added and selected.')->send();
    }

    /**
     * Validate form data.
     */
    protected function validateTemplate(bool $isApprove = false): bool
    {
        $this->validate([
            'company_id'       => ['required', 'exists:companies,id'],
            'transaction_type' => ['required', 'exists:actng_account_types,id'],
            'template_name_id' => ['required', 'exists:actng_template_names,id'],
            'description'      => ['nullable', 'string', 'max:255'],
            'module_type'      => ['required', 'string', 'max:50'],
            'particularRows'   => ['required', 'array', 'min:1'],
            'particularRows.*.account_title_id' => ['required', 'exists:actng_chart_of_accounts,id'],
            'particularRows.*.type'             => ['required', 'in:DEBIT,CREDIT'],
        ], [
            'company_id.required'       => 'Please select a Business Unit.',
            'transaction_type.required' => 'Please select a Transaction Type.',
            'template_name_id.required' => 'Please select a Template Name.',
            'particularRows.min'        => 'Please add at least one account title particular.',
        ]);

        if ($isApprove) {
            $debitCount = collect($this->particularRows)->where('type', 'DEBIT')->count();
            $creditCount = collect($this->particularRows)->where('type', 'CREDIT')->count();

            if ($debitCount === 0 || $creditCount === 0) {
                $this->toast()->error(
                    'Approval Requirement',
                    'A valid PCV template must have at least one DEBIT and one CREDIT particular before approval.'
                )->send();
                return false;
            }
        }

        return true;
    }

    /**
     * Save template as DRAFT.
     */
    public function saveDraft()
    {
        if (!$this->validateTemplate(false)) {
            return;
        }

        try {
            DB::transaction(function () {
                $template = $this->templateId ? TransactionTemplate::findOrFail($this->templateId) : new TransactionTemplate();

                $template->company_id       = $this->company_id;
                $template->template_name_id = $this->template_name_id;
                $template->transaction_type = $this->transaction_type;
                $template->description      = $this->description;
                $template->module_type      = $this->module_type ?: 'PCV';
                $template->for_requisition  = $this->for_requisition ? 1 : 0;
                $template->is_active        = $this->is_active ? 1 : 0;
                $template->status           = 'DRAFT';

                if (!$template->exists) {
                    $template->created_by = auth()->user()->emp_id ?? auth()->id();
                }

                if ($this->approver) {
                    $template->approved_by = $this->approver;
                }

                $template->save();

                $this->templateId = $template->id;
                $this->mode = 'edit';
                $this->status = 'DRAFT';

                // Sync particulars
                TemplateDetail::where('template_id', $template->id)->delete();
                foreach ($this->particularRows as $row) {
                    TemplateDetail::create([
                        'template_id'      => $template->id,
                        'account_title_id' => $row['account_title_id'],
                        'type'             => strtoupper($row['type']),
                    ]);
                }
            });

            $this->toast()->success('Saved as Draft', 'Template draft saved successfully. You can approve it when ready.')->send();
        } catch (\Exception $e) {
            Log::error('Error saving template draft: ' . $e->getMessage());
            $this->toast()->error('Error', 'Failed to save template: ' . $e->getMessage())->send();
        }
    }

    /**
     * Save template and immediately APPROVE it (makes it active & available for PCV).
     */
    public function saveAndApprove()
    {
        if (!$this->validateTemplate(true)) {
            return;
        }

        try {
            DB::transaction(function () {
                $template = $this->templateId ? TransactionTemplate::findOrFail($this->templateId) : new TransactionTemplate();

                $template->company_id       = $this->company_id;
                $template->template_name_id = $this->template_name_id;
                $template->transaction_type = $this->transaction_type;
                $template->description      = $this->description;
                $template->module_type      = $this->module_type ?: 'PCV';
                $template->for_requisition  = $this->for_requisition ? 1 : 0;
                $template->is_active        = 1; // Always activate on approval
                $template->status           = 'FINAL';
                $template->approved_by      = $this->approver ?? auth()->user()->emp_id ?? auth()->id();
                $template->approved_date    = now();

                if (!$template->exists) {
                    $template->created_by = auth()->user()->emp_id ?? auth()->id();
                }

                $template->save();

                $this->templateId = $template->id;
                $this->mode = 'edit';
                $this->status = 'FINAL';
                $this->is_active = true;
                $this->approved_date = now()->format('M d, Y h:i A');

                // Sync particulars
                TemplateDetail::where('template_id', $template->id)->delete();
                foreach ($this->particularRows as $row) {
                    TemplateDetail::create([
                        'template_id'      => $template->id,
                        'account_title_id' => $row['account_title_id'],
                        'type'             => strtoupper($row['type']),
                    ]);
                }
            });

            $this->toast()->success(
                'Template Approved!',
                'PACV template has been approved and activated. It is now immediately available in Petty Cash Voucher particulars.'
            )->send();

            return redirect()->route('accounting.coa-management');
        } catch (\Exception $e) {
            Log::error('Error approving template: ' . $e->getMessage());
            $this->toast()->error('Approval Error', 'Failed to approve template: ' . $e->getMessage())->send();
        }
    }

    /**
     * Toggle active status directly.
     */
    public function toggleActive()
    {
        $this->is_active = !$this->is_active;

        if ($this->templateId) {
            TransactionTemplate::where('id', $this->templateId)->update([
                'is_active' => $this->is_active ? 1 : 0
            ]);

            $state = $this->is_active ? 'Active (visible in Petty Cash Vouchers)' : 'Inactive (hidden from Petty Cash Vouchers)';
            $this->toast()->success('Visibility Updated', "Template is now {$state}.")->send();
        }
    }
};
?>

<div class="p-4 md:p-6 space-y-6 max-w-7xl mx-auto">
    {{-- BREADCRUMBS & HEADER --}}
    <div class="flex flex-col gap-4">
        <x-ts-breadcrumbs
            separator="icon:chevron-right"
            :items="[
                ['label' => 'Accounting', 'link' => route('accounting.coa-management'), 'icon' => 'calculator'],
                ['label' => 'COA Management', 'link' => route('accounting.coa-management'), 'icon' => 'chart-bar'],
                ['label' => $mode === 'edit' ? 'Edit PCV Template' : 'New PCV Template', 'icon' => 'document-duplicate'],
            ]"
        />

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ $mode === 'edit' ? 'Edit PCV Template' : 'Create PCV Template' }}
                    </h1>
                    <x-ts-badge :text="$module_type" color="blue" light />
                    @if($status === 'FINAL')
                        <x-ts-badge text="APPROVED" color="emerald" icon="check-circle" light />
                    @else
                        <x-ts-badge text="DRAFT" color="amber" icon="document-text" light />
                    @endif
                    @if($is_active)
                        <x-ts-badge text="ACTIVE" color="green" light />
                    @else
                        <x-ts-badge text="INACTIVE" color="rose" light />
                    @endif
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Define debit and credit account titles that automatically populate Petty Cash Voucher particulars.
                    @if($approved_date)
                        <span class="text-slate-400">Approved on {{ $approved_date }}</span>
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ts-button
                    color="gray"
                    outline
                    icon="arrow-left"
                    :href="route('accounting.coa-management')"
                >
                    Back to Summary
                </x-ts-button>

                <x-ts-button
                    color="gray"
                    icon="document-text"
                    wire:click="saveDraft"
                    loading="saveDraft"
                >
                    Save Draft
                </x-ts-button>

                <x-ts-button
                    color="green"
                    icon="check-circle"
                    wire:click="saveAndApprove"
                    loading="saveAndApprove"
                >
                    {{ $mode === 'edit' && $status === 'FINAL' ? 'Update & Keep Approved' : 'Save & Approve' }}
                </x-ts-button>
            </div>
        </div>
    </div>

    {{-- MAIN 2-COLUMN GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- LEFT COLUMN: TEMPLATE DETAILS --}}
        <div class="lg:col-span-5 space-y-6">
            <x-ts-card header="Template Configuration">
                <div class="space-y-4">

                    {{-- BUSINESS UNIT --}}
                    <div wire:key="company-select-container">
                        <x-ts-select.styled
                            label="Business Unit *"
                            wire:model.live="company_id"
                            :request="route('api.company.active')"
                            select="value:id"
                            hint="Changing business unit updates available titles and types"
                            required
                        />
                    </div>

                    {{-- TRANSACTION TYPE --}}
                    <div wire:key="transaction-type-{{ $company_id }}">
                        <x-ts-select.styled
                            label="Transaction Type *"
                            wire:model="transaction_type"
                            :request="route('api.get.active-account-type', ['company_id' => $company_id ?? 0])"
                            select="value:id|label:type_name"
                            hint="Accounting transaction category"
                            required
                        />
                    </div>

                    {{-- TEMPLATE NAME WITH QUICK CREATE --}}
                    <div wire:key="template-name-{{ $company_id }}">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Template Name *
                            </label>
                            @if($company_id)
                                <button
                                    type="button"
                                    wire:click="$set('createTemplateNameModal', true)"
                                    class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 flex items-center gap-1 transition"
                                >
                                    <x-ts-icon name="plus" class="w-3 h-3" />
                                    New Name
                                </button>
                            @endif
                        </div>
                        <x-ts-select.styled
                            wire:model="template_name_id"
                            :request="route('api-accounting.active-tempplate-name', ['company_id' => $company_id ?? 0])"
                            select="value:id|label:template_name"
                            required
                        />
                    </div>

                    {{-- MODULE TYPE & REQUISITION REQUIREMENT --}}
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Module</label>
                            <x-ts-badge text="PCV (Petty Cash)" color="indigo" light />
                        </div>
                        <div>
                            <x-ts-toggle
                                label="Requires Requisition"
                                wire:model="for_requisition"
                                color="green"
                                hint="Requires Purchase Order"
                            />
                        </div>
                    </div>

                    {{-- ACTIVE STATUS TOGGLE --}}
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">Active for PCV</div>
                            <div class="text-[11px] text-slate-400">Controls visibility in Petty Cash Voucher transaction dropdown</div>
                        </div>
                        <x-ts-toggle
                            wire:model="is_active"
                            color="green"
                        />
                    </div>

                    {{-- APPROVER --}}
                    <div wire:key="approver-{{ $company_id }}">
                        <x-ts-select.styled
                            label="Designated Approver"
                            wire:model="approver"
                            :request="route('api-accounting.active-tempplate-approver', ['company_id' => $company_id ?? 0])"
                            select="value:id|label:fullName|description:position"
                            hint="Authorized signatory for this template"
                        />
                    </div>

                    {{-- DESCRIPTION --}}
                    <x-ts-textarea
                        resize-auto
                        label="Description / Purpose"
                        placeholder="Explain when this template should be used in petty cash vouchers..."
                        wire:model="description"
                    />

                </div>
            </x-ts-card>
        </div>

        {{-- RIGHT COLUMN: PARTICULARS CONFIGURATION --}}
        <div class="lg:col-span-7 space-y-6">
            <x-ts-card>
                <x-slot:header>
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 w-full">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Petty Cash Voucher Particulars</h2>
                            <p class="text-xs text-slate-400">Account titles that populate voucher debit and credit rows</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ts-button
                                icon="plus"
                                color="green"
                                wire:click="selectTitle"
                            >
                                Add Account Title
                            </x-ts-button>
                        </div>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    {{-- SUMMARY STATUS BAR --}}
                    @php
                        $debitCount = collect($particularRows)->where('type', 'DEBIT')->count();
                        $creditCount = collect($particularRows)->where('type', 'CREDIT')->count();
                        $totalCount = count($particularRows);
                        $isBalanced = $debitCount > 0 && $creditCount > 0;
                    @endphp

                    <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-600 dark:text-slate-400">Structure:</span>
                            <x-ts-badge text="Debit: {{ $debitCount }}" color="green" light />
                            <x-ts-badge text="Credit: {{ $creditCount }}" color="blue" light />
                            <span class="text-slate-300 dark:text-slate-700">|</span>
                            <span class="text-slate-500">Total: {{ $totalCount }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            @if($isBalanced)
                                <x-ts-badge text="Ready for Approval (Has Debit & Credit)" color="emerald" icon="check" light />
                            @else
                                <x-ts-badge text="Requires at least 1 Debit & 1 Credit" color="amber" icon="exclamation-circle" light />
                            @endif

                            @if($totalCount > 0)
                                <button
                                    type="button"
                                    wire:click="clearAllParticulars"
                                    class="text-xs text-rose-500 hover:text-rose-600 underline font-medium transition"
                                >
                                    Clear all
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- PARTICULARS LIST --}}
                    @if(empty($particularRows))
                        <div class="text-center py-12 px-4 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
                            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center text-emerald-600">
                                <x-ts-icon name="clipboard-document-list" class="w-6 h-6" />
                            </div>
                            <div class="space-y-1">
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">No particulars configured</h4>
                                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                    Click "Add Account Title" to select account codes from your Chart of Accounts and classify each as Debit or Credit.
                                </p>
                            </div>
                            <x-ts-button sm color="green" icon="plus" wire:click="selectTitle">
                                Add Account Title
                            </x-ts-button>
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Account Particular</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Account Code</th>
                                        <th class="px-4 py-3 text-center font-semibold text-slate-600 dark:text-slate-300">Voucher Entry Type</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                                    @foreach($particularRows as $index => $row)
                                        <tr wire:key="particular-row-{{ $row['account_title_id'] }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ $row['account_title'] }}
                                                </div>
                                                <div class="text-[11px] text-slate-400 uppercase">
                                                    Normal: {{ $row['normal_balance'] ?? 'debit' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 font-mono font-semibold text-slate-700 dark:text-slate-300">
                                                {{ $row['account_code'] }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg w-fit mx-auto">
                                                    <button
                                                        type="button"
                                                        wire:click="setParticularType({{ $index }}, 'DEBIT')"
                                                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition {{ $row['type'] === 'DEBIT' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                                                    >
                                                        DEBIT
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="setParticularType({{ $index }}, 'CREDIT')"
                                                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition {{ $row['type'] === 'CREDIT' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
                                                    >
                                                        CREDIT
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <x-ts-button.circle
                                                    sm
                                                    color="rose"
                                                    outline
                                                    icon="trash"
                                                    wire:click="removeParticular({{ $index }})"
                                                    loading="removeParticular({{ $index }})"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </x-ts-card>
        </div>

    </div>

    {{-- ACCOUNT TITLES FILTER & SELECTION MODAL --}}
    <x-ts-modal
        wire="accountTitlesModal"
        title="Add Account Title as PCV Particular"
        size="6xl"
    >
        <div class="space-y-4">
            {{-- MODAL FILTERS --}}
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                <div class="md:col-span-5">
                    <x-ts-input
                        wire:model.live.debounce.300ms="searchTitle"
                        placeholder="Search account title or code..."
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                <div class="md:col-span-3">
                    <select
                        wire:model.live="filterAccountType"
                        class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-2"
                    >
                        <option value="">All Account Types</option>
                        @foreach($this->companyAccountTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <select
                        wire:model.live="filterNormalBalance"
                        class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-2"
                    >
                        <option value="all">All Balances</option>
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <select
                        wire:model.live="quantityTitle"
                        class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 py-2"
                    >
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
            </div>

            {{-- TITLES TABLE --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 max-h-[50vh] overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Code</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Account Title</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Type</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600 dark:text-slate-300">Normal Balance</th>
                            <th class="px-4 py-2.5 text-right font-semibold text-slate-600 dark:text-slate-300">Add to Template</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                        @if($this->accountTitleRows && $this->accountTitleRows->count() > 0)
                            @foreach($this->accountTitleRows as $title)
                                @php
                                    $addedParticular = collect($particularRows)->firstWhere('account_title_id', $title->id);
                                @endphp
                                <tr wire:key="title-pick-{{ $title->id }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $title->account_code }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-900 dark:text-slate-100 font-medium">
                                        {{ $title->account_title }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500">
                                        {{ $title->accountType?->type_name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-ts-badge
                                            :text="strtoupper($title->normal_balance ?? 'DEBIT')"
                                            :color="strtolower($title->normal_balance) === 'credit' ? 'blue' : 'green'"
                                            light
                                        />
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @if($addedParticular)
                                            <div class="inline-flex items-center gap-1.5">
                                                <x-ts-badge
                                                    :text="'Added (' . $addedParticular['type'] . ')'"
                                                    :color="$addedParticular['type'] === 'DEBIT' ? 'green' : 'blue'"
                                                    light
                                                />
                                                <button
                                                    type="button"
                                                    wire:click="addParticular({{ $title->id }}, '{{ $addedParticular['type'] === 'DEBIT' ? 'CREDIT' : 'DEBIT' }}')"
                                                    class="text-[11px] text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 underline"
                                                >
                                                    Switch to {{ $addedParticular['type'] === 'DEBIT' ? 'CREDIT' : 'DEBIT' }}
                                                </button>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1.5">
                                                <x-ts-button
                                                    xs
                                                    color="green"
                                                    outline
                                                    wire:click="addParticular({{ $title->id }}, 'DEBIT')"
                                                >
                                                    + Debit
                                                </x-ts-button>
                                                <x-ts-button
                                                    xs
                                                    color="blue"
                                                    outline
                                                    wire:click="addParticular({{ $title->id }}, 'CREDIT')"
                                                >
                                                    + Credit
                                                </x-ts-button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400 italic">
                                    No account titles match your search or filters.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            @if($this->accountTitleRows)
                <div class="pt-2">
                    {{ $this->accountTitleRows->links() }}
                </div>
            @endif

            <div class="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-slate-800">
                <div class="text-xs text-slate-400">
                    Selected particulars: <span class="font-bold text-slate-700 dark:text-slate-300">{{ count($particularRows) }}</span>
                </div>
                <x-ts-button color="gray" wire:click="$set('accountTitlesModal', false)">
                    Done
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>

    {{-- SELECT BUSINESS UNIT MODAL (FALLBACK) --}}
    <x-ts-modal
        wire="selectCompanyModal"
        title="Select Business Unit First"
    >
        <div class="space-y-4 py-2">
            <p class="text-xs text-slate-500">
                Please select a Business Unit so the appropriate Chart of Accounts and account titles can be loaded.
            </p>

            <div wire:key="modal-company-select">
                <x-ts-select.styled
                    label="Business Unit"
                    wire:model.live="company_id"
                    :request="route('api.company.active')"
                    select="value:id"
                    required
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-ts-button color="gray" wire:click="$set('selectCompanyModal', false)">
                    Cancel
                </x-ts-button>
                <x-ts-button
                    icon="arrow-long-right"
                    wire:click="proceed"
                >
                    Proceed to Titles
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>

    {{-- QUICK NEW TEMPLATE NAME MODAL --}}
    <x-ts-modal
        wire="createTemplateNameModal"
        title="Quick Add Template Name"
        center
    >
        <form wire:submit.prevent="saveNewTemplateName" class="space-y-4">
            <x-ts-input
                label="New Template Name *"
                placeholder="e.g. Office Supplies, Travel Expenses..."
                wire:model.defer="new_template_name"
                required
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ts-button type="button" color="gray" wire:click="$set('createTemplateNameModal', false)">
                    Cancel
                </x-ts-button>
                <x-ts-button type="submit" color="green" loading="saveNewTemplateName">
                    Save & Select
                </x-ts-button>
            </div>
        </form>
    </x-ts-modal>

</div>
