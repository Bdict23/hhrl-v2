<?php

use Livewire\Component;
use App\Models\Transaction\PettyCashVoucher;
use App\Services\Transaction\PettyCashVoucherService;
use App\Services\Transaction\ReimbursementService;
use App\Models\Transaction\PcvLiquidationSnapshot;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Auth;




new class extends Component
{
    use Interactions;

    public $pcvId, $pcvData, $employee, $customer, $liqudatedAmt, $liquidationRow = [], $note, $reimburseAmount, $status, $approvedBy;

    protected $rules =
    [
        'pcvId' => 'required|exists:petty_cash_vouchers,id',
        'note' => 'nullable|string|max:255',
    ];


    public function updatedPcvId()
    {
        if ($this->pcvId) {
            $this->pcvData = PettyCashVoucher::find($this->pcvId);
            $this->liquidationRow = PcvLiquidationSnapshot::where('pcv_id', $this->pcvId)->get();
            $this->liqudatedAmt = number_format(PettyCashVoucherService::liquidatedAmount($this->pcvId), 2);
            $liquidated = (float) str_replace(",", "", $this->liqudatedAmt);
            $this->reimburseAmount = number_format(($liquidated - $this->pcvData->total_amount), 2);
            if ($this->pcvData->paid_to_employee_id) {
                $this->employee = $this->pcvData->paidToEmployee->fullName;
            } else {
                $this->customer = $this->pcvData->paidToCustomer->fullName;
            }
        } else {
            $this->reset();
        }
    }

    public function saveAsFinalAction()
    {
        $validated = $this->validate();
        $this->status = 'FOR APPROVAL';
        $this->dialog()
            ->question('Save Reimbursement', 'Are you sure to save this reimbursement as draft?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function saveAsDraftAction()
    {
        $validated = $this->validate();
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('Save Reimbursement', 'Are you sure to save this reimbursement as draft?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }

    public function store(ReimbursementService $reimbursementService)
    {
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'pcv_id' => $this->pcvId,
                'amount' => str_replace(",", "", $this->reimburseAmount),
                'note' => $this->note,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'approved_by' => $this->approvedBy,
            ];
            $reimbursement = $reimbursementService->create($data);
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
    <!--  -->
    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
            <x-ts-card>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <x-ts-select.styled
                            :request="route('api.get.reimburse-pcv',['branch_id' => auth()->user()->branch_id])"
                            label="PCV Referencce"
                            select="label:reference|value:id"
                            wire:model.live="pcvId" />
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
                    <x-ts-currency label="Disbursed Amount" disabled value="{{ $pcvData?->total_amount }}" mutate symbol />
                    <x-ts-currency label="Liquidated Amount" disabled value="{{ ($liqudatedAmt) }}" mutate symbol decimal />
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
                <x-ts-currency label="ReImbursed amount" symbol wire:model='reimburseAmount' mutate />
                <x-ts-textarea label="Note" wire:model='note' />
            </div>
            <div class="grid">
                <x-ts-input label="Prepared By" value="{{ auth()->user()->employee->fullName }}" disabled />
                <x-ts-select.styled :request="route('api.get.reimburse-approvers',['branch_id' => auth()->user()->branch_id])"
                    select="label:fullName|value:id"
                    wire:model.live="approvedBy"
                    label="Approved By" />
            </div>
        </div>
        <x-slot:footer>
            <div class="whitespace-nowrap flex justify-end">
                <x-ts-dropdown>
                    <x-slot:action>
                        <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                    </x-slot:action>
                    <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()" />
                    <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator wire:click="saveAsFinalAction()" />
                </x-ts-dropdown>
            </div>
        </x-slot:footer>
    </x-ts-card>
    <x-ts-loading loading="store" />
    <x-ts-back-to-top lg />

</div>