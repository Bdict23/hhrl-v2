<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Models\Transaction\AdvancesForLiquidationSnapshot;
use App\Services\Transaction\AdvancesForLiquidationService;

new class extends Component {
    use Interactions;

    public $approvedById;
    public $preparedById;
    public $note;
    public $disburserId;
    public $receivedAmount;
    public $eventId;
    public $status;
    public $aflId;
    public $isDraft;
    public $reference;
    public $balance;
    public $data;
    public $expenditurePercentage;

    public ?int $quantity = 100;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'asc',
    ];

    protected $rules = [
        'approvedById' => 'required|exists:employees,id',
        'preparedById' => 'required|exists:employees,id',
        'eventId' => 'nullable|exists:banquet_events,id',
    ];

    public function mount($id)
    {
        $this->aflId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $data = AdvancesForLiquidation::find($this->aflId);
        $this->data = $data;
        $this->approvedById = $data->approved_by;
        $this->preparedById = $data->prepared_by;
        $this->note = $data->notes;
        $this->disburserId = $data->received_by;
        $this->receivedAmount = $data->amount_received;
        $this->eventId = $data->event_id;
        $this->status = $data->status;
        $this->isDraft = $data->status == 'DRAFT' ? true : false;
        $this->reference = $data->reference;
        $this->balance = AdvancesForLiquidationService::currentBalance($this->aflId);
        $expence = $this->receivedAmount - $this->balance;
        $this->expenditurePercentage = ($expence / $this->receivedAmount) * 100;
    }

    public function saveAsDraftAction()
    {
        $validated = $this->validate();
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('New Acknowledgement Receipt', 'Are you sure to save this acknowledgement receipt as draft?')
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
        $this->status = 'OPEN';
        $this->dialog()
            ->question('New Acknowledgement Receipt', 'Are you sure to save this acknowledgement receipt as final ?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function store(AdvancesForLiquidationService $advancesForLiquidationService)
    {
        try {
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'received_by' => $this->disburserId,
                'date_received' => now(),
                'approved_by' => $this->approvedById,
                'amount_received' => $this->receivedAmount,
                'event_id' => $this->eventId,
                'note' => $this->note,
                'status' => $this->status,
            ];

            // 4. Call the Service
            $afl = $advancesForLiquidationService->create($data);

            // 5. Success Feedback
            $this->toast()
                ->success('Success', "Acknowledgement Receipt {$afl->reference} created successfully!")
                ->send();
            $this->reset();
            return redirect()->route('afl.summary');
        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error('Acknowledgement Receipt Creation Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while saving: ' . $e->getMessage())
                ->send();
        }
    }
    public function resetForm()
    {
        $this->eventId = null;
        $this->note = null;
        $this->disburserId = null;
        $this->receivedAmount = null;
        $this->preparedById = null;
        $this->approvedById = null;
    }

    public function with(): array
    {
        return [
            'headers' => [['index' => 'created_at', 'label' => 'date'], ['index' => 'reference', 'label' => 'Reference'], ['index' => 'description', 'label' => 'description'], ['index' => 'amount', 'label' => 'amount'], ['index' => 'balance', 'label' => 'balance']],
            'rows' => AdvancesForLiquidationSnapshot::query()->where('advance_liquidation_id', $this->aflId)->orderBy(...array_values($this->sort))->paginate($this->quantity)->withQueryString(),
        ];
    }
};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
        ['label' => 'Transaction', 'link' => route('afl.summary'), 'icon' => 'archive-box'],
        ['label' => 'Advance for liquidation Summary', 'link' => route('afl.summary'), 'icon' => 'list-bullet'],
        ['label' => 'View advances for liquidation', 'icon' => 'pencil-square'],
    ]" class="mb-3" />
    <x-ts-card class=" p-6">
        <div class="flex justify-between items-start">

            <!-- Left Side: Ref No & Balance -->
            <div>
                <div class="flex items-center space-x-3 mb-2">
                    <h2 class="text-xl font-bold">AFL reference#</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ strtoupper($reference) }}
                    </span>
                </div>
                <div class="border-l-4 border-emerald-600 pl-4">
                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <x-ts-icon name="calendar" class="h-4 w-4" />
                            <span>Date created: <strong>{{ $data->created_at->format('M. d, Y') }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ts-icon name="calendar" class="h-4 w-4" />
                            <span>Event: <strong>{{ $data->event?->event_name }}
                                    {{ $data->event?->reference }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ts-icon name="user" class="h-4 w-4" />
                            <span>Accountable: <strong>{{ $data->receivedBy->full_name }}</strong></span>
                        </div>
                    </div>
                </div>
                <p class="text-sm mt-4">Current Balance</p>
                <h1 class="text-4xl font-black  mt-1">
                    ₱{{ number_format($balance, 2) }}
                </h1>
            </div>
            <div class=" border-gray-100 flex justify-end items-center space-x-3">
                @if ($isDraft)
                    <x-ts-button light icon="pencil-square" :href="route('afl.edit', ['id' => $aflId])">Edit</x-ts-button>
                @else
                    <x-ts-button light icon="pencil-square" disabled>Edit</x-ts-button>
                @endif
                <x-ts-button icon="printer" disabled>Print</x-ts-button>
            </div>
        </div>

        <!-- Visual Progress Bar indicator sa Balance -->
        <div class="mt-6">
            <div class="flex justify-between text-xs text-gray-500">
                <span>Depletion Level</span>
                <span>Ceiling: ₱{{ number_format($receivedAmount, 2) }}</span>
            </div>
            <x-ts-progress percent="{{ number_format($expenditurePercentage, 2) }}" md floating />
        </div>
    </x-ts-card>

    <div class="mt-5">
        <x-ts-table :headers="$headers" :rows="$rows" expandable striped>
            @interact('column_created_at', $row)
                {{ $row->created_at->format('M. d, Y') }}
            @endinteract
            @interact('column_reference', $row)
                @if ($row->description == 'ENCASHMENT')
                    <x-ts-button class="font-mono" flat>{{ $row->advanceLiquidation->reference }}
                        <x-slot:right>
                            @if ($row->advanceLiquidation->status == 'OPEN')
                                <x-ts-badge color="yellow" text="{{ $row->advanceLiquidation->status }}" round light xs />
                            @elseif($row->advanceLiquidation->status == 'CLOSED')
                                <x-ts-badge color="green" text="{{ $row->advanceLiquidation->status }}" round light xs />
                            @elseif($row->advanceLiquidation->status == 'DRAFT')
                                <x-ts-badge color="gray" text="{{ $row->advanceLiquidation->status }}" round light xs />
                            @else
                                <x-ts-badge color="red" text="{{ $row->advanceLiquidation->status }}" round light xs />
                            @endif
                        </x-slot:right>
                    </x-ts-button>
                @elseif($row->description == 'PETTY CASH VOUCHER')
                    <x-ts-button class="font-mono" flat>{{ $row->pettyCashVoucher->reference }}
                        <x-slot:right>
                            @if ($row->pettyCashVoucher->status == 'OPEN')
                                <x-ts-badge color="yellow" text="{{ $row->pettyCashVoucher->status }}" round light xs />
                            @elseif($row->pettyCashVoucher->status == 'CLOSED')
                                <x-ts-badge color="green" text="{{ $row->pettyCashVoucher->status }}" round light xs />
                            @elseif($row->pettyCashVoucher->status == 'DRAFT')
                                <x-ts-badge color="gray" text="{{ $row->pettyCashVoucher->status }}" round light xs />
                            @else
                                <x-ts-badge color="red" text="{{ $row->pettyCashVoucher->status }}" round light xs />
                            @endif
                        </x-slot:right>
                    </x-ts-button>
                @elseif($row->description == 'CASH RETURN - PCV' || $row->description == 'CASH RETURN - EXCESS')
                    <x-ts-button class="font-mono" flat>{{ $row->cashReturn->reference }}
                        <x-slot:right>
                            @if ($row->cashReturn->status == 'OPEN')
                                <x-ts-badge color="yellow" text="{{ $row->cashReturn->status }}" round light xs />
                            @elseif($row->cashReturn->status == 'FINAL')
                                <x-ts-badge color="green" text="CLOSED" round light xs />
                            @elseif($row->cashReturn->status == 'DRAFT')
                                <x-ts-badge color="gray" text="{{ $row->cashReturn->status }}" round light xs />
                            @else
                                <x-ts-badge color="red" text="{{ $row->cashReturn->status }}" round light xs />
                            @endif
                        </x-slot:right>
                    </x-ts-button>
                @elseif($row->description == 'CASH RETURN' || $row->description == 'RETURN EXCESS')
                    <x-ts-button class="font-mono" flat>{{ $row->cashReturn->reference }}
                        <x-slot:right>
                            @if ($row->cashReturn->status == 'OPEN')
                                <x-ts-badge color="yellow" text="{{ $row->cashReturn->status }}" round light xs />
                            @elseif($row->cashReturn->status == 'FINAL')
                                <x-ts-badge color="green" text="CLOSED" round light xs />
                            @elseif($row->cashReturn->status == 'DRAFT')
                                <x-ts-badge color="gray" text="{{ $row->cashReturn->status }}" round light xs />
                            @else
                                <x-ts-badge color="red" text="{{ $row->cashReturn->status }}" round light xs />
                            @endif
                        </x-slot:right>
                    </x-ts-button>
                @elseif($row->description == 'REIMBURSEMENT')
                    <x-ts-button class="font-mono" flat>{{ $row->reimbursement->reference }}
                        <x-slot:right>
                            @if ($row->reimbursement->status == 'OPEN')
                                <x-ts-badge color="yellow" text="{{ $row->reimbursement->status }}" round light xs />
                            @elseif($row->reimbursement->status == 'CLOSED')
                                <x-ts-badge color="green" text="CLOSED" round light xs />
                            @elseif($row->reimbursement->status == 'DRAFT')
                                <x-ts-badge color="gray" text="{{ $row->reimbursement->status }}" round light xs />
                            @else
                                <x-ts-badge color="red" text="{{ $row->reimbursement->status }}" round light xs />
                            @endif
                        </x-slot:right>
                    </x-ts-button>
                @endif
            @endinteract
            @interact('column_amount', $row)
                @if ($row->type == 'IN')
                    <x-ts-badge :text="'+ ₱ ' . number_format($row->amount, 2)" color="green" outline />
                @elseif($row->type == 'OUT')
                    <x-ts-badge :text="'- ₱' . number_format($row->amount, 2)" color="red" outline />
                @endif
            @endinteract
            @interact('column_balance', $row)
                @if ($row->pettyCashVoucher?->status == 'DRAFT')
                    ₱ --.--
                @elseif($row->cashReturn?->status == 'DRAFT')
                    ₱ --.--
                @else
                    ₱ {{ NUMBER_FORMAT($row->balance, 2) }}
                @endif
            @endinteract
            @interact('sub_table', $row)
                @if ($row->description == 'ENCASHMENT')
                    @php
                        $headers = [
                            ['index' => 'preparedBy', 'label' => 'prepared by'],
                            ['index' => 'approvedBy', 'label' => 'approved by'],
                            ['index' => 'note', 'label' => 'note'],
                        ];
                        $rows = [
                            [
                                'preparedBy' => $row->advanceLiquidation->preparedBy?->full_name,
                                'approvedBy' => $row->advanceLiquidation->approvedBy?->full_name,
                                'note' => $row->advanceLiquidation->notes,
                            ],
                        ];
                    @endphp
                    <x-ts-table :headers="$headers" :rows="$rows" />
                @elseif ($row->description == 'PETTY CASH VOUCHER')
                    @php
                        $headers = [
                            ['index' => 'payee', 'label' => 'Payee'],
                            ['index' => 'event', 'label' => 'event'],
                            ['index' => 'preparedBy', 'label' => 'prepared by'],
                            ['index' => 'purchase order', 'label' => 'purchase order'],
                            ['index' => 'transaction', 'label' => 'transaction'],
                        ];
                        $rows = [
                            [
                                'payee' => $row->pettyCashVoucher->paidToEmployee?->full_name,
                                'event' => $row->pettyCashVoucher->event?->event_name,
                                'preparedBy' => $row->pettyCashVoucher->preparedBy?->full_name,
                                'purchase order' => $row->pettyCashVoucher->purchaseOrder?->requisition_number,
                                'transaction' => $row->pettyCashVoucher->transaction_title,
                            ],
                        ];

                    @endphp
                    <x-ts-table :headers="$headers" :rows="$rows" />
                @elseif($row->description == 'CASH RETURN')
                    @php
                        $headers = [
                            ['index' => 'preparedBy', 'label' => 'prepared by'],
                            ['index' => 'pcv', 'label' => 'linked pcv'],
                            ['index' => 'pcv_amount', 'label' => 'pcv amount'],
                            ['index' => 'note', 'label' => 'note'],
                        ];
                        $rows = [
                            [
                                'preparedBy' => $row->cashReturn->preparedBy?->full_name,
                                'pcv' => $row->cashReturn->pettyCashVoucher?->reference,
                                'pcv_amount' =>
                                    '₱ ' . number_format($row->cashReturn->pettyCashVoucher->total_amount, 2),
                                'note' => $row->cashReturn->notes,
                            ],
                        ];
                    @endphp
                    <x-ts-table :headers="$headers" :rows="$rows" />
                    @elseif($row->description == 'CASH RETURN - EXCESS')
                        @php
                            $headers = [
                                ['index' => 'preparedBy', 'label' => 'prepared by'],
                                ['index' => 'note', 'label' => 'note'],
                            ];
                            $rows = [
                                [
                                    'preparedBy' => $row->cashReturn->preparedBy?->full_name,
                                    'note' => $row->cashReturn->notes,
                                ],
                            ];
                        @endphp
                    <x-ts-table :headers="$headers" :rows="$rows" />
                @endif
            @endinteract
        </x-ts-table>
    </div>

    <x-ts-loading delay="short" />
    <x-ts-back-to-top lg />
</div>
