<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\EmployeesAdvanceService;
use App\Models\BanquetEvent\Event;
use App\Models\Transaction\EmployeeAdvance;
use App\Models\Transaction\EmployeeAdvanceSnapshot;




new class extends Component
{
    use Interactions;

    public $approvedById;
    public $preparedById;
    public $note;
    public $employeeId;
    public $receivedAmount;
    public $status;
    public $reference;



    // mount
    public $id;
    public $data;
    public $balance;
    public $expenditurePercentage;
    public $isDraft;
    public ?int $quantity = 100;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'asc',
    ];

    protected $rules =[
        'approvedById' => 'required|exists:employees,id',
        'preparedById' => 'required|exists:employees,id',
        'employeeId' => 'required|exists:employees,id',
        ];

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
        $this->reference = $data->reference;
        $this->isDraft = $data->status == 'DRAFT' ? true : false;
        $this->balance = EmployeesAdvanceService::currentBalance($this->id);

    }

     public function with(): array
    {
        return [
            'headers' => [
                ['index' => 'created_at', 'label' => 'date'], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'description', 'label' => 'description'], 
                ['index' => 'amount', 'label' => 'amount'], 
                ['index' => 'balance', 'label' => 'balance']],
            'rows' =>  EmployeeAdvanceSnapshot::query()
            ->where('advance_id', $this->id)
            ->orderBy(...array_values($this->sort))->paginate($this->quantity)->withQueryString(),
        ];
    }


};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction', 'link' => route('employees-advances.summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Employees Advance Summary', 'link' => route('employees-advances.summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Employees advance create', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    <x-ts-card class=" p-6">
        <div class="flex justify-between items-start">

            <!-- Left Side: Ref No & Balance -->
            <div>
                <div class="flex items-center space-x-3 mb-2">
                    <h2 class="text-xl font-bold">Cash Advance reference#</h2>
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
                            <x-ts-icon name="user" class="h-4 w-4" />
                            <span>Received By: <strong>{{ $data->receivedBy->full_name }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ts-icon name="document-text" class="h-4 w-4" />
                            <span>Note: <strong>{{ $data->remarks}}</strong></span>
                        </div>
                    </div>
                </div>
                <p class="text-sm mt-4">Balance</p>
                <h1 class="text-4xl font-black  mt-1">
                    ₱{{ number_format($balance, 2) }}
                </h1>
            </div>
            <div class=" border-gray-100 flex justify-end items-center space-x-3">
                @if ($isDraft)
                    <x-ts-button light icon="pencil-square" :href="route('employees-advances.edit', ['id' => $id])">Edit</x-ts-button>
                @else
                    <x-ts-button light icon="pencil-square" disabled>Edit</x-ts-button>
                @endif
                <x-ts-button icon="printer" disabled>Print</x-ts-button>
            </div>
        </div>

        <!-- Visual Progress Bar indicator sa Balance -->
        <div class="mt-6">
            <div class="flex justify-end text-xs text-gray-500">
                <span>Payable Amount: ₱{{ number_format($receivedAmount, 2) }}</span>
            </div>
            <x-ts-progress percent="{{ number_format($expenditurePercentage, 2) }}" md floating >
                    <x-slot:footer>
                        Amount Returned
                    </x-slot:footer>
            </x-ts-progress>
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
                @elseif($row->description == 'DISBURSEMENT')
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
                @elseif ($row->description == 'DISBURSEMENT')
                    @php
                        $headers = [
                            ['index' => 'payee', 'label' => 'Payee'],
                            ['index' => 'preparedBy', 'label' => 'prepared by'],
                            ['index' => 'transaction', 'label' => 'transaction'],
                            ['index' => 'note', 'label' => 'note'],
                        ];
                        $rows = [
                            [
                                'payee' => $row->pettyCashVoucher->paidToEmployee?->full_name,
                                'preparedBy' => $row->pettyCashVoucher->preparedBy?->full_name,
                                'transaction' => $row->pettyCashVoucher->transaction_title,
                                'note' => $row->pettyCashVoucher->purpose
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
                @endif
            @endinteract
        </x-ts-table>
    </div>

    <x-ts-loading delay="short" />
    <x-ts-back-to-top lg />
</div>
