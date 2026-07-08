<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Inventory\PurchaseOrderService;
use App\Services\Inventory\ItemWithdrawalService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Livewire\WithFileUploads;
// use App\Models\Inventory\Receiving;
use App\Services\Transaction\AcknowledgementReceiptService; 
use App\Services\Transaction\PettyCashVoucherService; 
use App\Models\Inventory\PurchaseOrderItems;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\SplFileInfo;
use App\Services\Event\EventLiquidationService;
use App\Services\Event\BanquetEventService;


new class extends Component
{
    use WithFileUploads;
    use WithPagination;
    use Interactions;

    // NEW VARIABLES
    public 
    $eventId,
    $purchaseOrder,
    $checkNumber,
    $approvedBudget,
    $photos = [],
    $pcvList = [],
    $purchaseList = [],
    $receivedList = [],
    $withdrawalList = [],
    $pcvTotal = 0.00,
    $pcvTotalMutate = 0.00,
    $purchaseOrderTotal = 0.00,
    $receiveOrderTotal = 0.00,
    $pcvTotalReturn = 0.00,
    $withdrawalTotal = 0.00,
    $checkData,
    $reviewedBy,
    $status,
    $notes,
    $approvedBy,
    $liquidationId,
    $liquidationData,
    $currentStep = 1,
    $liquidationTotalAmount=0.00,
    $eventExpenseTotalAmount=0.00;


    public function mount($id)
    {
        $this->liquidationId = $id;
        $this->fetchData();
    }
    public function fetchData()
    {
        $this->liquidationData  = BanquetEventService::getLiquidationData($this->liquidationId);
        $id = $this->liquidationData?->event_id;
        
        if($id){
            $this->checkData = AcknowledgementReceiptService::eventCheckData( $id, Auth::user()->branch_id);
            $this->checkNumber = $this->checkData?->check_number ?? '';
            $this->approvedBudget = number_format($this->checkData->check_amount ?? 0,2);

            $this->purchaseList = PurchaseOrder::where('event_id', $id)->get();
            $this->pcvList = PettyCashVoucherService::pcvListsCollection($id, Auth::user()->branch_id);
            $this->pcvTotalReturn = PettyCashVoucherService::totalPcvRetunAmount($id, Auth::user()->branch_id);
            $this->pcvTotal = (float) $this->pcvList->sum('total_amount') + $this->pcvList->sum('total_reimbursement') - $this->pcvTotalReturn;
            $this->pcvTotalMutate = $this->pcvTotal;
            $this->purchaseOrderTotal = $this->purchaseList->sum('total_amount');
            $this->receivedList = PurchaseOrderService::purchaseReceivedData($id, Auth::user()->branch_id);
            $this->receiveOrderTotal = $this->receivedList->sum('receive_amount');
            $this->withdrawalList = ItemWithdrawalService::getEventwithdrawals($id, Auth::user()->branch_id);
            $this->withdrawalTotal = ItemWithdrawalService::getEventWithdrawalTotal($id, Auth::user()->branch_id);
            $receivingAttachment= PurchaseOrderService::getEventReceivingAttachments($id, Auth::user()->branch_id);
            $this->liquidationTotalAmount = $this->pcvTotal - $this->receiveOrderTotal;
            $this->eventExpenseTotalAmount = $this->liquidationTotalAmount - $this->withdrawalTotal;
            $this->notes = $this->liquidationData->note;
            if($this->liquidationData->status == 'DRAFT'){
                $this->currentStep = 1;
            }else if($this->liquidationData->status == 'FOR REVIEW'){
                $this->currentStep = 2;
            }else if($this->liquidationData->status =='FOR SETTLEMENT'){
                $this->currentStep = 2;
            }else if($this->liquidationData->status == 'FOR APPROVAL'){
                $this->currentStep = 4;
            }else if($this->liquidationData->status == 'CLOSED'){
                $this->currentStep = 5;
            }

  

            // attached all receipt from receiving
            $this->photos = $receivingAttachment->map(function ($attachment) {
            // Standardizes the file path reference
            $filePath = $attachment->file_path; 

            return [
                'id'        => $attachment->id, // handy if you need to delete it later
                'name'      => basename($filePath),
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'size'      => Storage::disk('public')->exists($filePath) ? Storage::disk('public')->size($filePath) : 0,
                'path'      => $filePath,
                'url'       => Storage::disk('public')->url($filePath),
            ];
        })->toArray();

            $this->validateCheck();

            }
            else{
                $this->reset();
            }
    }

    public function updatedEventId($id)
    {
        if($id){
            $this->checkData = AcknowledgementReceiptService::eventCheckData( $id, Auth::user()->branch_id);
            $this->checkNumber = $this->checkData?->check_number ?? '';
            $this->approvedBudget = number_format($this->checkData->check_amount ?? 0,2);

            $this->purchaseList = PurchaseOrder::where('event_id', $id)->get();
            $this->pcvList = PettyCashVoucherService::pcvListsCollection($id, Auth::user()->branch_id);
            $this->pcvTotalReturn = PettyCashVoucherService::totalPcvRetunAmount($id, Auth::user()->branch_id);
            $this->pcvTotal = (float) $this->pcvList->sum('total_amount') - $this->pcvTotalReturn;
            $this->pcvTotalMutate = $this->pcvTotal;
            $this->purchaseOrderTotal = $this->purchaseList->sum('total_amount');
            $this->receivedList = PurchaseOrderService::purchaseReceivedData($id, Auth::user()->branch_id);
            $this->receiveOrderTotal = $this->receivedList->sum('receive_amount');
            $this->withdrawalList = ItemWithdrawalService::getEventwithdrawals($id, Auth::user()->branch_id);
            $this->withdrawalTotal = ItemWithdrawalService::getEventWithdrawalTotal($id, Auth::user()->branch_id);
            $receivingAttachment= PurchaseOrderService::getEventReceivingAttachments($id, Auth::user()->branch_id);
  

            // attached all receipt from receiving
            $this->photos = $receivingAttachment->map(function ($attachment) {
            // Standardizes the file path reference
            $filePath = $attachment->file_path; 

            return [
                'id'        => $attachment->id, // handy if you need to delete it later
                'name'      => basename($filePath),
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'size'      => Storage::disk('public')->exists($filePath) ? Storage::disk('public')->size($filePath) : 0,
                'path'      => $filePath,
                'url'       => Storage::disk('public')->url($filePath),
            ];
        })->toArray();

            $this->validateCheck();

            }
            else{
                $this->reset();
            }
    }

    public function validateCheck()
    {
        $rules = [
        'checkNumber' => 'required',
        ];
            $messages = [
            'checkNumber.required' => 'No check number found, please process the acknowledgment first.'
        ];

        $this->validate($rules, $messages);
        
    }

    public function with(): array
    {
        return [
            'pettyCashVoucherHeader' => [
                ['index' => 'status', 'label' => 'status'],
                ['index' => 'created_at', 'label' => 'Date' ],
                ['index' => 'reference', 'label' => 'reference' ],
                ['index' => 'paid_to_employee_id', 'label' => 'payee'],
                ['index' => 'total_amount', 'label' => 'PCV amount' ],
                ['index' => 'liquidated_amount', 'label' => 'liquidated amount' ],
                ['index' => 'return_amount', 'label' => 'cash return' ],
                ['index' => 'reimburse_amount', 'label' => 'reimbursement' ],
                ['index' => 'total', 'label' => 'total' ],
            ],
            'purchaseOrderHeader' => [
                ['index' => 'requisition_status', 'label' => 'status'],
                ['index' => 'created_at', 'label' => 'Date' ],
                ['index' => 'requisition_number', 'label' => 'reference' ],
                ['index' => 'prepared_by', 'label' => 'prepared by'],
                ['index' => 'total_amount', 'label' => 'P.O amount' ],
            ],
            'receivedOrderHeader' => [
                ['index' => 'receiving_status', 'label' => 'status'],
                ['index' => 'created_at', 'label' => 'Date' ],
                ['index' => 'reference', 'label' => 'reference' ],
                ['index' => 'prepared_by', 'label' => 'prepared by'],
                ['index' => 'total_received_amount', 'label' => 'total received amount' ],
            ],
            'withdrawalHeader' => [
                ['index' => 'reference_number', 'label' => 'reference' ],
                ['index' => 'receiving_status', 'label' => 'status'],
                ['index' => 'created_at', 'label' => 'Date' ],
                ['index' => 'total_received_amount', 'label' => 'withdrawal amount' ],
                ['index' => 'prepared_by', 'label' => 'Withdrawer'],
                ['index' => 'approved_by', 'label' => 'Approver'],
            ],
        ];
    }

    public function saveAsDraftAction(): void
    {

        // 1. Validate the UI State
        $this->validationRule();

        $this->status = "DRAFT";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('Save liquidation?', 'Are you sure to save this liquidation as draft ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function saveAsFinalAction(): void
    {
        // 1. Validate the UI State
        $this->validationRule();
        $this->status = "FINAL";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('Save liquidation?', 'Are you sure to save this liquidation as final?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(EventLiquidationService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id'   => Auth::user()->branch_id,
                'company_id'    => Auth::user()->branch->company_id,
                'event_id' => $this->eventId,
                'prepared_by'  => auth()->user()->emp_id,
                'status'  => $this->status,
                'notes'       => $this->notes,
                'total_incurred' => $this->pcvTotal,
                'reviewed_by' => $this->reviewedBy,
                'approved_by' => $this->approvedBy,
            ];

            // 4. Call the Service
            $po = $service->createLiquidation($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "event liquidation {$po->reference} created successfully!")->send();
            $this->reset();
            

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PO Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

    public function validationRule()
    {
        $this->validate([
            'checkNumber'   => 'required',
            'pcvTotal'      => 'numeric|min:1',
            'reviewedBy'    =>'required|exists:employees,id',
            'approvedBy'    =>'required|exists:employees,id',
            'notes'         =>'nullable|string|max:150',
            'eventId'      => 'required|exists:banquet_events,id'
        ]);
    }
 
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Event', 'link' => route('event-liquidation-summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Event Liquidation Summary', 'link' => route('event-liquidation-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'View Event Liquidation', 'icon' => 'eye'],
                  ]"  class="mb-3"/>
                  <i>({{$liquidationData->reference}})
                    <x-ts-badge text="{{$liquidationData->status}}" color="gray" outline /> </i>
    </div>

    <div class="grid gap-4 mb-10">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-input label="BANQUET EVENT" value="{{$liquidationData->event->reference}}" readonly/>

                    <x-ts-currency mutate currency symbol label="APPROVED BUDGET" wire:model="approvedBudget" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="CHECK No̱." wire:model.blur="checkNumber" readonly/>
                    <x-ts-currency mutate symbol currency label="INCURRED TOTAL" wire:model="pcvTotalMutate" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="CRS No̱." readonly/>
                    <x-ts-input label="CASH RETURN" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="RMB No̱." readonly/>
                    <x-ts-input label="REIMBURSEMENT" readonly/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <x-ts-tab selected="PETTY CASH VOUCHERS">
            <x-ts-tab.items tab="PETTY CASH VOUCHERS">
                <x-ts-card>
                    <x-ts-table :headers="$pettyCashVoucherHeader" :rows="$pcvList" striped expandable loading highlight>
                        @interact('column_status', $row)
                            <div class="flex items-center gap-2">
                                @if($row->status == 'DRAFT')
                                    <x-ts-badge text="DRAFT" color="secondary" />
                                @elseif($row->status == 'OPEN')
                                    <x-ts-badge :text="$row->status" color="amber" />
                                @elseif($row->status == 'CLOSED')
                                    <x-ts-badge :text="$row->status" color="green" />
                                @elseif($row->status == 'CANCELLED')
                                    <x-ts-badge :text="$row->status" color="rose" />
                                @endif
                            </div>
                        @endinteract
                        @interact('column_created_at', $row)
                             {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
                        @endinteract
                        @interact('column_paid_to_employee_id', $row)
                            <div class="flex items-center gap-2">
                                <x-ts-badge :text="$row->paidToEmployee != null ? $row->paidToEmployee?->name . ' ' . $row->paidToEmployee?->last_name . ' - (Employee)'  : $row->paidToCustomer?->customer_fname . ' ' . $row->paidToCustomer?->customer_lname . ' - (Customer)' ?? 'Unknown'" outline />
                            </div>
                        @endinteract
                        @interact('column_total_amount', $row)
                            ₱ {{  number_format(($row->total_amount) ?? 0 , 2) }}
                        @endinteract
                        @interact('column_liquidated_amount', $row)
                            ₱ {{  number_format(($row->liquidationData?->sum('amount')) ?? 0 , 2) }}
                        @endinteract
                        @interact('column_return_amount', $row)
                            ₱ {{ number_format($row->cashReturn?->amount_returned, 2) }}
                        @endinteract
                        @interact('column_reimburse_amount', $row)
                            ₱ {{ number_format($row->reimbursement?->amount, 2) }}
                        @endinteract
                        @interact('column_total', $row)
                           ₱ {{ number_format($row->total_amount + $row->total_reimbursement - ($row->cashReturn?->amount_returned ?? 0) , 2) }}
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                        
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($pcvTotal, 2) }}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
            </x-ts-tab.items>
            <x-ts-tab.items tab="PURCHASE ORDERS">
                <x-ts-card>
                    <x-ts-table :headers="$purchaseOrderHeader" :rows="$purchaseList" striped expandable loading highlight>
                        @interact('column_requisition_status', $row)
                            <div class="flex items-center gap-2">
                                @if ($row->requisition_status == 'FOR APPROVAL')
                                    <x-ts-badge :text="$row->requisition_status" color="cyan" />
                                @elseif($row->requisition_status == 'PARTIALLY FULFILLED')
                                    <x-ts-badge :text="$row->requisition_status" color="amber" />
                                @elseif($row->requisition_status == 'TO RECEIVE')
                                    <x-ts-badge :text="$row->requisition_status" color="primary" />
                                @elseif($row->requisition_status == 'FOR REVIEW')
                                    <x-ts-badge :text="$row->requisition_status" color="teal" />
                                @elseif($row->requisition_status == 'REJECTED')
                                    <x-ts-badge :text="$row->requisition_status" color="red" />
                                @elseif($row->requisition_status == 'PREPARING')
                                    <x-ts-badge text="DRAFT" color="secondary" />
                                @elseif($row->requisition_status == 'COMPLETED')
                                    <x-ts-badge :text="$row->requisition_status" color="green" />
                                @elseif($row->requisition_status == 'CANCELLED')
                                    <x-ts-badge :text="$row->requisition_status" color="rose" />
                                @endif
                            </div>
                        @endinteract
                        @interact('column_created_at', $row)
                             {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
                        @endinteract
                        @interact('column_prepared_by', $row)
                            <div class="flex items-center gap-2">
                                <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                            </div>
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                        @interact('column_total_amount', $row)
                            ₱ {{  number_format(($row->total_amount) ?? 0 , 2) }}
                        @endinteract
                        
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($purchaseOrderTotal, 2)}}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
                
            </x-ts-tab.items>
            <x-ts-tab.items tab="PURCHASE RECEIVED">
                <x-ts-card>
                    <x-ts-table :headers="$receivedOrderHeader" :rows="$receivedList" striped expandable loading highlight>
                        @interact('column_receiving_status', $row)
                            <div class="flex items-center gap-2">
                                @if ($row->RECEIVING_STATUS == 'DRAFT')
                                    <x-ts-badge :text="$row->RECEIVING_STATUS" color="grey" />
                                @elseif($row->RECEIVING_STATUS == 'FINAL')
                                    <x-ts-badge :text="$row->RECEIVING_STATUS" color="green" />
                                @elseif($row->RECEIVING_STATUS == 'CANCELLED')
                                    <x-ts-badge :text="$row->RECEIVING_STATUS" color="rose" />
                                @endif
                            </div>
                        @endinteract
                        @interact('column_created_at', $row)
                             {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
                        @endinteract
                        @interact('column_prepared_by', $row)
                            <div class="flex items-center gap-2">
                                <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                            </div>
                        @endinteract
                         @interact('column_total_received_amount', $row)
                            ₱ {{  number_format(($row->receive_amount) ?? 0 , 2) }}
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($receiveOrderTotal, 2)}}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
            </x-ts-tab.items>
            <x-ts-tab.items tab="ITEM WITHDRAWALS">
                <x-ts-card>
                    <x-ts-table :headers="$withdrawalHeader" :rows="$withdrawalList" striped expandable loading highlight>
                        @interact('column_receiving_status', $row)
                            <div class="flex items-center gap-2">
                                @if ($row->withdrawal_status == 'PREPARING')
                                    <x-ts-badge :text="$row->withdrawal_status" color="grey" />
                                @elseif($row->withdrawal_status == 'FOR REVIEW')
                                    <x-ts-badge :text="$row->withdrawal_status" color="amber" />
                                 @elseif($row->withdrawal_status == 'FOR APPROVAL')
                                    <x-ts-badge :text="$row->withdrawal_status" color="teal" />
                                 @elseif($row->withdrawal_status == 'APPROVED')
                                    <x-ts-badge text="CLOSED" color="green" />
                                @else
                                    <x-ts-badge :text="$row->withdrawal_status" color="rose" />
                                @endif
                            </div>
                        @endinteract
                        @interact('column_created_at', $row)
                             {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
                        @endinteract
                        @interact('column_prepared_by', $row)
                            <div class="flex items-center gap-2">
                                <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                            </div>
                        @endinteract
                        @interact('column_approved_by', $row)
                            <div class="flex items-center gap-2">
                                <x-ts-badge :text="$row->approvedBy?->full_name ?? 'Unknown'" outline />
                            </div>
                        @endinteract
                         @interact('column_total_received_amount', $row)
                            ₱ {{  number_format(($row->cost_amount ) ?? 0 , 2) }}
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($withdrawalTotal, 2)}}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
            </x-ts-tab.items>
        </x-ts-tab>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3">
                    <x-ts-upload label="Receiving Attachments" multiple static wire:model="photos" :placeholder="count($photos) . ' attached'" />
                    <div class="grid">
                        <x-ts-card>
                            <div class="grid grid-cols-4">
                                <div class="grid">
                                    <span class="font-bold">Petty Cash Voucher</span>
                                    <span>₱ {{number_format($pcvTotal, 2)}}</span>
                                </div>
                                <div class="grid">
                                    <span class="font-bold">Purchase Order</span>
                                    <span>₱ {{number_format($purchaseOrderTotal,2)}}</span>
                                </div>
                                <div class="grid">
                                    <span class="font-bold">Purchase received</span>
                                    <span>₱ {{number_format($receiveOrderTotal,2)}}</span>
                                </div>
                                <div class="grid">
                                    <span class="font-bold">Withdrawal</span>
                                    <span>₱ {{number_format($withdrawalTotal,2)}}</span>
                                </div>
                            </div>
                        </x-ts-card>
                        <div class="grid grid-cols-2 mt-4">
                            <div class="grid">
                                <span class="font-bold">LIQUIDATION TOTAL AMOUNT</span>
                                <span>₱ {{number_format($liquidationTotalAmount, 2)}}</span>
                            </div>
                            <div class="grid">
                                <span class="font-bold">EVENT EXPENSE TOTAL AMOUNT</span>
                                <span>₱ {{number_format($eventExpenseTotalAmount, 2)}}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-1 gap-2">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes" readonly/>
                        <div class="col-span-2 grid grid-cols-2 gap-2">
                            <x-ts-input label="REVIEWED BY" readonly value="{{$liquidationData->reviewedBy->full_name}}  ({{$liquidationData->reviewedBy->position->position_name}})"/>
                            <x-ts-input label="APPROVED BY" readonly value="{{$liquidationData->approvedBy->full_name}}  ({{$liquidationData->approvedBy->position->position_name}})"/>
                        </div>
                         <div class="mt-3">
                            <x-ts-step wire:model="currentStep" circles>
                                <x-ts-step.items step="1"
                                            title="Create Liquidation"
                                            description="Step 1">
                                </x-ts-tep.items>
                                <x-ts-step.items step="2"
                                            title="Review"
                                            description="Step 2">
                                </x-ts-step.items>
                                <x-ts-step.items step="3"
                                            completed
                                            title="Settlement"
                                            description="Step 3">
                                </x-ts-step.items>
                                <x-ts-step.items step="4"
                                            completed
                                            title="Approved"
                                            description="Step 4">
                                </x-ts-step.items>
                                <x-ts-step.items step="5"
                                            completed
                                            title="Completed"
                                            description="Step 6">
                                            <b>Event Liquidated!</b>
                                </x-ts-step.items>
                            </x-ts-step>
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                     <x-ts-button  icon="arrow-left" outline :href="route('event-liquidation-summary')">Back</x-ts-button>
                     @if($liquidationData->status == 'DRAFT')
                     <x-ts-button  icon="pencil-square" :href="route('event-liquidation-edit', ['id' => $liquidationData->id])">Edit</x-ts-button>
                     @else
                     <x-ts-button  icon="pencil-square" disabled>Edit</x-ts-button>
                     @endif
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>

    <x-ts-back-to-top />
</div>
