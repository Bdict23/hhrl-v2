<?php

use Livewire\Component;
use App\Models\Transaction\PettyCashVoucher;
use App\Services\Transaction\PettyCashVoucherService;
use App\Services\Transaction\ReimbursementService;
use App\Models\Transaction\PcvLiquidationSnapshot;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction\Reimbursement;




new class extends Component
{
    use Interactions;

    public $pcvId, 
            $pcvData,
            $reimburseData, 
            $employee, 
            $customer, 
            $liquidatedAmt, 
            $liquidationRow = [], 
            $note, 
            $reimburseAmount, 
            $status, 
            $approvedBy,
            $pcvReference,
            $disbursedAmt,
            $step;

    protected $rules =
    [
        'pcvId' => 'required|exists:petty_cash_vouchers,id',
        'note' => 'nullable|string|max:255',
    ];

   public function mount($id)
    {
        $this->reimburseData = Reimbursement::findOrFail($id);
        $this->fetchData();
    }

    public function fetchData()
    {
        $this->pcvData = PettyCashVoucher::findOrFail($this->reimburseData->pcv_id);
        $this->pcvId = $this->reimburseData->pcv_id;
        $this->liquidationRow = PcvLiquidationSnapshot::where('pcv_id', $this->pcvId)->get();
        $this->liquidatedAmt = number_format(PettyCashVoucherService::liquidatedAmount($this->pcvId), 2);
        $liquidated = (float) str_replace(",", "", $this->liquidatedAmt);
        $this->reimburseAmount = number_format(($liquidated - $this->pcvData->total_amount), 2);

         if ($this->pcvData->paid_to_employee_id) {
                $this->employee = $this->pcvData->paidToEmployee->fullName;
            } else {
                $this->customer = $this->pcvData->paidToCustomer->fullName;
            }
        $this->pcvReference = $this->pcvData->reference;
        $this->disbursedAmt = $this->pcvData->total_amount;
        $this->note = $this->reimburseData->note;
        $this->approvedBy = $this->reimburseData->approved_by;
        $currentStep = $this->reimburseData->status;
        if($currentStep == 'DRAFT'){
            $this->step = '1';
        }elseif($currentStep == 'FOR APPROVAL'){
            $this->step = '2';
        }elseif($currentStep == 'COMPLETED'){
            $this->step = '3';
        }else{
            $this->step = '1';
        }
    }

    public function updateAsFinalAction()
    {
        $validated = $this->validate();
        $this->status = 'FOR APPROVAL';
        $this->dialog()
            ->question('Update Reimbursement?', 'Are you sure to save this reimbursement as final?')
            ->confirm(
                'Confirm',
                'update', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function updateAsDraftAction()
    {
        $validated = $this->validate();
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('Update Reimbursement?', 'Are you sure to save this reimbursement as draft?')
            ->confirm(
                'Confirm',
                'update', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }

    public function update(ReimbursementService $reimbursementService)
    {
        try {
            $data = [
                'note' => $this->note,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'approved_by' => $this->approvedBy,
                'reimbursement_id' => $this->reimburseData->id,
            ];
            $reimbursement = $reimbursementService->update($data);
            $this->toast()->success('Success', "Reimbursement created successfully!")->send();
            $this->reset();
            return redirect()->route('reimbursement.summary');
        } catch (\Exception $e) {
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

    public function  with(): array
    {
        return [
            'liquidationDataHeader' => [
                ['index' => 'purchase_date', 'label' => 'Status'],
                ['index' => 'vendor', 'label' => 'vendor'],
                ['index' => 'reference', 'label' => 'Prepared By', 'sortable' => false],
                ['index' => 'particular', 'label' => 'Received', 'sortable' => false],
                ['index' => 'amount', 'label' => 'Returned'],
            ],
        ];
    }
};
?>

<div>
    <div class="lg:flex lg:justify-between mb-3">
        <div class="w-auto mb-3">
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' =>  route('reimbursement.summary'), 'icon' => 'cog'],
            ['label' => 'Reimbursement Summary', 'icon' => 'list-bullet' ,'link' =>  route('reimbursement.summary')],
            ['label' => 'Edit reimbursement', 'icon' => 'list-bullet' ],

            ]"  class="mb-3"/>
        </div>
        <div class="gap-4">
            <i class="mr-3">{{$reimburseData->reference}}</i>
                @if($reimburseData->status == 'DRAFT')
                    <x-ts-badge text="DRAFT" color="secondary" />
                @elseif($reimburseData->status == 'FOR APPROVAL')
                    <x-ts-badge :text="$reimburseData->status" color="amber" />
                @elseif($reimburseData->status == 'CLOSED')
                    <x-ts-badge :text="$reimburseData->status" color="green" />
                @elseif($reimburseData->status == 'CANCELLED'| $reimburseData->requisition_status == 'REJECTED')
                    <x-ts-badge :text="$reimburseData->status" color="rose" />
                @endif
        </div>
    </div>
    <!--  -->
    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
            <x-ts-card>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                         <x-ts-input wire:model="pcvReference" readonly/>
                    </div>
                    <x-ts-input label="Account" disabled value="{{ $employee ?? $customer }}" />
                    <x-ts-input label="Transaction" disabled value="{{$pcvData?->transaction_title}}" />
                    <x-ts-currency label="AFL Reference" disabled value="{{$pcvData?->advanceLiquidation?->reference ?? 'N/A'}}" />
                    <x-ts-input label="P.O Reference" disabled value="{{$pcvData?->purchaseOrder->reference ?? 'N/A'}}" />
                </div>
            </x-ts-card>
        </div>
        <div class="col-span-1">
            <x-ts-card>
                <div class="grid gap-3">
                    <x-ts-currency label="Disbursed Amount" disabled wire:model="disbursedAmt" mutate symbol />
                    <x-ts-currency label="Liquidated Amount" disabled wire:model="liquidatedAmt" mutate symbol decimal />
                </div>
            </x-ts-card>
        </div>
    </div>

    <!-- -- TABLE -- -->
    <div class="mt-4 mb-4">
        <x-ts-card header="LIQUIDATION ENTRIES">
            <x-ts-table :headers="$liquidationDataHeader" :rows="$liquidationRow" striped loading>
                @interact('column_purchase_date', $row)
                {{ \Illuminate\Support\Carbon::parse($row->purchase_date)->format('M. d, Y') }}
                @endinteract
                @interact('column_amount', $row)
                ₱ {{ number_format(($row->amount) ?? 0 , 2) }}
                @endinteract
            </x-ts-table>
        </x-ts-card>
    </div>

    <!-- -- BOTTOM FORM ---->
    <x-ts-card>
        <div class="grid grid-cols-2 mt-4 gap-3">
            <div class="grid gap-3">
                <x-ts-currency label="Reimbursed amount" symbol wire:model='reimburseAmount' mutate readonly/>
                <x-ts-textarea label="Note" wire:model='note' />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-ts-input label="Prepared By" value="{{ auth()->user()->employee->fullName }}" disabled />
                <x-ts-select.styled :request="route('api.get.reimburse-approvers',['branch_id' => auth()->user()->branch_id])"
                    select="label:fullName|value:id"
                    wire:model.live="approvedBy"
                    label="Approved By" />
                    <div class="col-span-2">
                        <x-ts-step wire:model="step" circles>
                            <x-ts-step.items step="1"
                                        title="Create reimbursement"
                                        description="Step 1">
                            </x-ts-tep.items>
                            <x-ts-step.items step="2"
                                        title="For Approval"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        completed
                                        title="Completed"
                                        description="Step 6">
                                        <b>Reimbursed Completed!</b>
                            </x-ts-step.items>
                        </x-ts-step>
                    </div>
            </div>
        </div>
        <x-slot:footer>
            <div class="whitespace-nowrap flex justify-end">
                <x-ts-dropdown>
                    <x-slot:action>
                        <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">UPDATE AS</x-ts-button>
                    </x-slot:action>
                    <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="updateAsDraftAction()" />
                    <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator wire:click="updateAsFinalAction()" />
                </x-ts-dropdown>
            </div>
        </x-slot:footer>
    </x-ts-card>
    <x-ts-loading loading="update" />
    <x-ts-back-to-top lg />

</div>