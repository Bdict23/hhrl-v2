<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\EmployeesAdvanceService;
use App\Models\BanquetEvent\Event;
use App\Models\Transaction\EmployeeAdvance;




new class extends Component
{
    use Interactions;

    public $approvedById;
    public $preparedById;
    public $note;
    public $employeeId;
    public $receivedAmount;
    public $status;

    // mount
    public $id;
    public $data;


    public function mount($id)
    {
        $this->id = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $data = EmployeeAdvance::find($this->id);
        $this->data = $data;
        $this->approvedById = $data->approved_by;
        $this->preparedById = $data->prepared_by;
        $this->note = $data->remarks;
        $this->employeeId = $data->received_by;
        $this->receivedAmount = $data->amount;
        $this->status = $data->status;
    }

    public function approveAction()
    {
        $this->status = 'APPROVED';
        $this->dialog()
        ->question('Approve employee cash advance', 'Are you sure to approve this cash advance as final ?')
        ->confirm(
            'Confirm',
            'update', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function reviseAction(){
         $this->status = 'REVISE';
         $this->dialog()
        ->question('Revise employee cash advance', 'Are you sure to revise this cash advance?')
        ->confirm(
            'Confirm',
            'update', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

     public function rejectAction(){
        $validated = $this->validate();
         $this->status = 'REJECTED';
         $this->dialog()
        ->question('Reject employee cash advance', 'Are you sure to reject this cash advance?')
        ->confirm(
            'Confirm',
            'update', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function update(EmployeesAdvanceService $employeesAdvanceService)
    {
        try {

            $data = ['id' => $this->id];

            if($this->status == 'APPROVED'){
                $afe = $employeesAdvanceService->approve($data);
            }
            else if($this->status == 'REVISE'){
                $afe = $employeesAdvanceService->revise($data);
            }
            else if($this->status == 'REJECTED'){
                $afe = $employeesAdvanceService->reject($data);
            }

            // Success Feedback
            $this->toast()->success('Success', "Employees cash advance status {$afe->reference} updated successfully!")->send();
            $this->reset();
            } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("Employees cash advance action Failed: " . $e->getMessage());
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
                              ['label' => 'Employees cash advance approval', 'icon' => 'pencil-square'],
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
                                readonly/>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-currency label="REQUESTED AMOUNT *" wire:model.live="receivedAmount" mutate decimal symbol currency readonly/>
                    </div>

                    <div class="md:col-span-8 flex flex-col justify-between space-y-5">
                        <div>
                           <x-ts-select.styled searchable
                                            :request="route('api.get.active-employees-advances', ['branch_id' => auth()->user()->branch_id])"
                                            label="PREPARED BY"
                                            select="label:full_name|value:id|description:position_name"
                                            wire:model.live="preparedById"
                                            readonly
                                            required/>
                        </div>

                        <div>
                            <x-ts-select.styled searchable
                                            :request="route('api.get.afl-approvers', ['branch_id' => auth()->user()->branch_id])"
                                            label="APPROVED BY *"
                                            select="label:full_name|value:id|description:position_name"
                                            placeholder="Select approver"
                                            wire:model.live="approvedById"
                                            required readonly/>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-textarea label="NOTE" wire:model="note" count maxlength="150" resize class="md:h-28" readonly></x-ts-textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-start items-center space-x-3">
                <x-ts-button  wire:click="rejectAction" color="rose" flat icon="x-mark">REJECT</x-ts-button>
                <x-ts-button  wire:click="reviseAction" light icon="arrow-path">REVISE</x-ts-button>
                <x-ts-button  wire:click="approveAction" icon="check">APPROVED</x-ts-button>
            </div>
    </x-ts-card>
    <x-ts-loading delay="short" loading="update" />
</div>
