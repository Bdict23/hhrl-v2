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
            $preparedBy,
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
        $this->approvedBy = $this->reimburseData->approvedBy->full_name;
        $this->preparedBy = $this->reimburseData->preparedBy->full_name;
        $currentStep = $this->reimburseData->status;
        if($currentStep == 'DRAFT'){
            $this->step = '1';
        }elseif($currentStep == 'FOR APPROVAL'){
            $this->step = '2';
        }elseif($currentStep == 'CLOSED'){
            $this->step = '3';
        }else{
            $this->step = '1';
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
            ['label' => 'Reimbursement Summary', 'icon' => 'list-bullet' ],

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
                <x-ts-textarea label="Note" wire:model='note' readonly />
            </div>
            <div class="grid grid-cols-2  gap-3">
                <x-ts-input label="Prepared By" wire:model="preparedBy" disabled />
                <x-ts-input
                    wire:model="approvedBy"
                    label="Approved By" readonly/>
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
            <div class="flex justify-start gap-2">
                     <x-ts-button  icon="arrow-left" outline :href="route('reimbursement.summary')">Back</x-ts-button>
                     @if($reimburseData->status == 'DRAFT')
                     <x-ts-button  icon="pencil-square" :href="route('reimbursement.edit', ['id' => $reimburseData->id])">Edit</x-ts-button>
                     @else
                     <x-ts-button  icon="pencil-square" disabled>Edit</x-ts-button>
                     @endif
                </div>
        </x-slot:footer>
    </x-ts-card>
    <x-ts-loading/>
    <x-ts-back-to-top lg />

</div>