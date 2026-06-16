<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\EmployeesAdvanceService;
use App\Models\BanquetEvent\Event;



new class extends Component
{
    use Interactions;

    public $approvedById;
    public $preparedById;
    public $note;
    public $employeeId;
    public $receivedAmount;
    public $status;

    protected $rules =[
        'approvedById' => 'required|exists:employees,id',
        'preparedById' => 'required|exists:employees,id',
        'employeeId' => 'required|exists:employees,id',
        ];

    public function mount()
    {
        $this->preparedById = Auth::user()->emp_id;
    }


    public function saveAsDraftAction(){
        $validated = $this->validate();
         $this->status = 'DRAFT';
         $this->dialog()
        ->question('New employee advance', 'Are you sure to save this employee advance as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function saveAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'FOR APPROVAL';
        $this->dialog()
        ->question('New employee advance', 'Are you sure to save this employee advance as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(EmployeesAdvanceService $employeesAdvanceService)
    {
        try {
            $checkAmt = floatval(str_replace(",", "", $this->receivedAmount));
            if ($checkAmt <= 0) {
                throw new Exception("Invalid amount: Received amount cannot be negative.", 400);
            }

            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'received_by' => $this->employeeId,
                'approved_by' => $this->approvedById,
                'amount_received' => str_replace(",", "", $this->receivedAmount),
                'note' => $this->note,
            ];

            // 4. Call the Service
            $afe = $employeesAdvanceService->create($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Employees Advance {$afe->reference} created successfully!")->send();
            $this->reset();
            } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("Acknowledgement Receipt Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
    public function resetForm()
    {
        $this->note = null;
        $this->employeeId = null;
        $this->receivedAmount = null;
        $this->preparedById = null;
        $this->approvedById = null;
        $this->preparedById = Auth::user()->emp_id;

    }

};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction', 'link' => route('employees-advances.summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Employees Advance Summary', 'link' => route('employees-advances.summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Employees advance create', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    <x-ts-card>
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold tracking-tight uppercase">EMPLOYEE CASH ADVANCE FORM</h2>
        </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="md:col-span-12">
                        <x-ts-select.styled searchable
                                :request="route('api.get.active-employees-advances', ['branch_id' => auth()->user()->branch_id])"
                                label="RECEIVED BY *"
                                select="label:full_name|value:id|description:position_name"
                                placeholder="Select Employee"
                                wire:model.live="employeeId"
                                required/>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-currency label="REQUESTED AMOUNT *" wire:model.live="receivedAmount" mutate decimal symbol currency/>
                    </div>

                    <div class="md:col-span-8 flex flex-col justify-between space-y-5">
                        <div>
                           <x-ts-select.styled searchable
                                            :request="route('api.get.active-employees-advances', ['branch_id' => auth()->user()->branch_id])"
                                            label="PREPARED BY"
                                            select="label:full_name|value:id|description:position_name"
                                            wire:model.live="preparedById"
                                            disabled
                                            required/>
                        </div>

                        <div>
                            <x-ts-select.styled searchable
                                            :request="route('api.get.afl-approvers', ['branch_id' => auth()->user()->branch_id])"
                                            label="APPROVED BY *"
                                            select="label:full_name|value:id|description:position_name"
                                            placeholder="Select approver"
                                            wire:model.live="approvedById"
                                            required/>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-textarea label="NOTE" wire:model="note" count maxlength="150" resize class="md:h-28"></x-ts-textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end items-center space-x-3">
                <x-ts-button  wire:click="resetForm" flat>Reset</x-ts-button>
                <div class="whitespace-nowrap content-center">
                        <x-ts-dropdown>
                            <x-slot:action>
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                            </x-slot:action>
                            <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                            <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                        </x-ts-dropdown>
                    </div>
            </div>
    </x-ts-card>
    <x-ts-loading delay="short" loading="store" />
</div>
