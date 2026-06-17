<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction\CashReturn;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Services\Transaction\CashReturnService;
use App\Services\Transaction\EmployeesAdvanceService;
use App\Models\Transaction\EmployeeAdvance;


new class extends Component {
    use Interactions;

    protected $rules = [
        'notes' => 'nullable|max:225',
    ];

    public  $approvedBy, $amountReturned, $notes, $amountToReturn;
    public $status = 'DRAFT';
    
    public $cashAdvanceId,$receivedDate,$note,$receivedBy,$preparedBy,$advanceAmount,$balance,$hasPendingCashReturn = false;

    public function updatedCashAdvanceId($value)
    {
        $this->hasPendingCashReturn = EmployeesAdvanceService::hasPendingCashReturn($value); // check if naa pay open nga transaction sa cash return or pcv
        if($this->hasPendingCashReturn){
            $this->dialog()
                ->warning('Pending Cash return Detected', 'There are pending Cash return associated with this employee cash advance. Would you like to view and edit them?')
                ->confirm('Yes','redirectCrs')
                ->cancel('Dismiss')
                ->send();
                return;
        }
        if ($value) {
            $eca = EmployeeAdvance::find($value);
            if ($eca) {
                $this->receivedDate = $eca->opened_at; //eca
                $this->note = $eca->remarks;
                $this->receivedBy = $eca->receivedBy?->full_name;
                $this->preparedBy = $eca->preparedBy?->full_name;
                $this->approvedBy = $eca->approvedBy?->full_name;
                $this->advanceAmount = $eca->amount;
                $this->balance = EmployeesAdvanceService::currentBalance($value);
                $this->amountToReturn = $this->balance;
                $this->amountReturned = $this->amountToReturn;
            }
        }else{
            $this->resetForm();
        }
    }

    public function redirectCrs()
    {
        $this->redirect(route('cash-return.employee-advances.edit', $this->hasPendingCashReturn));

    }
    public function isValidReturn()
    {
        $validateReturn = str_replace(',', '', $this->amountReturned) <= $this->amountToReturn ? true : false;
        return $validateReturn;
    }

    public function saveAsDraftAction()
    {
        $validated = $this->validate();
        if (!$this->isValidReturn()) {
            $this->toast()->error('Error', 'Retun amount should not be exceed!')->send();
            return;
        }
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('New Cash return for employee advance', 'Are you sure to save this cash return as draft?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }

    public function saveAsFinalAction()
    {
        $validated = $this->validate();
        if (!$this->isValidReturn()) {
            $this->toast()->error('Error', 'Retun amount should not be exceed to AFL amount')->send();
            return;
        }
        $this->status = 'FINAL';
        $this->dialog()
            ->question('New Cash return for employee advance', 'Are you sure to save this cash return as draft?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function store(CashReturnService $service)
    {
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'amount_returned' => str_replace(',', '', $this->amountReturned),
                'notes' => $this->notes,
                'employee_advance_id' => $this->cashAdvanceId,
                'balance' => $this->balance - str_replace(',', '', $this->amountReturned)
            ];
            $crs = $service->createEmployeeAdvanceCrs($data);
            $this->toast()
                ->success('Success', "Cash Return {$crs->reference} created successfully!")
                ->send();
            $this->reset();
        } catch (\Exception $e) {
            \Log::error('Cash return Creation Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while saving: ' . $e->getMessage())
                ->send();
        }
    }
    public function resetForm()
    {
        $this->receivedDate = null;
        $this->note = null;
        $this->receivedBy = null;
        $this->preparedBy = null;
        $this->approvedBy = null;
        $this->advanceAmount = null;
        $this->balance = null;
        $this->amountReturned = null;
        $this->amountToReturn = null;
        $this->notes = null;
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' => route('cash-return.summary-tab'), 'icon' => 'archive-box'],
            ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
            ['label' => 'create cash return for cash advances', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-select.styled label="Employee cash advance" :request="route('api.get.active-advances-for-employees', ['branch_id' => auth()->user()->branch_id])"
                        select="label:reference|value:id|description:remarks" wire:model.live="cashAdvanceId" />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='receivedDate' label="Received Date" disabled />
                    <x-ts-textarea label="Note" wire:model='note' readonly />
                    <x-ts-input label="Received By" wire:model="receivedBy" readonly />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-input label="Prepared By" wire:model="preparedBy" readonly />
                        <x-ts-input label="Approved By" wire:model="approvedBy" readonly />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate decimal wire:model="advanceAmount" label="Advance Amount" readonly symbol
                            currency />
                        <x-ts-currency wire:model="balance" label="Balance" readonly symbol currency />
                    </div>
                </div>
                <div>
                    <x-ts-currency label="Return Amount" wire:model="amountReturned" mutate symbol />
                    <x-ts-textarea label="Notes" resize maxlength="225" count placeholder="Add note here..."
                        wire:model="notes" />
                </div>

            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <div class="whitespace-nowrap content-center">
                        <x-ts-dropdown>
                            <x-slot:action>
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE
                                    AS</x-ts-button>
                            </x-slot:action>
                            <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT"
                                wire:click="saveAsDraftAction()" />
                            <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator
                                wire:click="saveAsFinalAction()" />
                        </x-ts-dropdown>
                    </div>
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>
    <x-ts-back-to-top />
</div>
