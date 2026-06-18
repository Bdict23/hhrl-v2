<?php

use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

use App\Models\Transaction\RevolvingFund;
use App\Models\Business\Branch;
use App\Models\Transaction\RevolvingFundSnapshot;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Transaction\RevolvingFundService;
use App\Models\Transaction\Acknowledgement;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;
    use Interactions;

    public ?int $quantity = 100;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'asc',
    ];
    public array $sortBathHistory = [
        'column' => 'opened_at',
        'direction' => 'desc',
    ];

    public $revolvingFundId,
        $balance = 0,
        $reference,
        $ceilingAmount,
        $maxEpenditurePercentage,
        $replenishedAmount,
        $expensedAmount = 0,
        $expenditurePercentage;
    public $acknowlegementId, $source, $checkName, $checkDate, $checkNumber, $checkAmount, $note; // for acknowledgement modal
    public $currentBatchData = [];
    public bool $replenishModal = false;

    public function mount()
    {
        $activeRevolvingFund = RevolvingFund::where('status', 'OPEN')->first() ?? null;
        $myBranch = Branch::find(Auth::user()->branch_id);
        $this->revolvingFundId = $activeRevolvingFund->id ?? null;
        $this->reference = $activeRevolvingFund->reference ?? 'NOT AVAILABLE';
        $this->replenishedAmount = $activeRevolvingFund->replenished_amount ?? 0;
        $this->ceilingAmount = $activeRevolvingFund?->starting_balance ?? 0;
        $this->maxEpenditurePercentage = $myBranch->maxExpenditurePercentage->name;
        $this->balance = RevolvingFundService::currentBalance(Auth::user()->branch_id);
        $this->expensedAmount = $activeRevolvingFund?->starting_balance - $this->balance;

        if ($this->ceilingAmount > 0) {
            $this->expenditurePercentage = (float) ($this->expensedAmount / $this->ceilingAmount) * 100;
        } else {
            // Define a default value (0%) when there is no ceiling to calculate against
            $this->expenditurePercentage = 0.0;
        }
    }

    public function replenishmentAction()
    {
        $this->dialog()
            ->question('Genearate new batch ?', 'Are you sure you want to generate a new batch?')
            ->confirm(
                'Yes',
                'replenish', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }

    public function replenish(RevolvingFundService $revolvingFundService)
    {
        //close modal
        $this->replenishModal = false;
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'prepared_by' => Auth::user()->emp_id,
                'balance' => $this->balance + $this->checkAmount,
                'replenished_amount' => $this->checkAmount,
                'current_revolving_fund_id' => $this->revolvingFundId,
                'acknowledgement_id' => $this->acknowlegementId,
                'items' => $this->currentBatchData,
            ];
            $po = $revolvingFundService->createBatch($data);
            $this->toast()
                ->success('Success', "Revolving Fund Batch {$po->reference} genearated successfully!, with replenished amount of ₱{$po->replenished_amount}")
                ->send();
            $this->reset();
            $this->mount();
        } catch (Exception $e) {
            $this->toast()
                ->error('Error', 'Something went wrong while generate new batch: ' . $e->getMessage())
                ->send();
        }
    }
    public function with(): array
    {
        return [
            'headers' => [['index' => 'created_at', 'label' => 'Date'], ['index' => 'reference', 'label' => 'reference', 'sortable' => false], ['index' => 'description', 'label' => 'description', 'sortable' => false], ['index' => 'type', 'label' => 'type', 'sortable' => false], ['index' => 'amount', 'label' => 'amount', 'sortable' => false], ['index' => 'balance', 'label' => 'running balance', 'sortable' => false]],

            'batchHistoryHeader' => [['index' => 'status', 'label' => 'status', 'sortable' => false], ['index' => 'reference', 'label' => 'reference', 'sortable' => false], ['index' => 'prepared_by', 'label' => 'prepared by', 'sortable' => false], ['index' => 'replenished_amount', 'label' => 'replenished amount', 'sortable' => false], ['index' => 'starting_balance', 'label' => 'starting balance', 'sortable' => false], ['index' => 'ending_balance', 'label' => 'Ending balance', 'sortable' => false], ['index' => 'opened_at', 'label' => 'Opened date'], ['index' => 'closed_at', 'label' => 'closed date']],

            'rows' => RevolvingFundSnapshot::query()->where('revolving_fund_id', $this->revolvingFundId)->orderBy(...array_values($this->sort))->paginate($this->quantity),

            'batchHistoryRows' => RevolvingFund::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->orderBy(...array_values($this->sortBathHistory))
                ->paginate($this->quantity),
        ];
    }
    public function updatedAcknowlegementId()
    {
        $data = Acknowledgement::find($this->acknowlegementId);
        $this->source = $data->customer->full_name;
        $this->checkName = $data->account_name;
        $this->checkDate = $data->check_date;
        $this->checkNumber = $data->check_number;
        $this->checkAmount = $data->check_amount;
        $this->note = $data->notes;

        // for forwarding balance
        if ($this->revolvingFundId != null && $this->balance > 0) {
            $this->currentBatchData[] = [
                'forwarded_revolving_fund_id' => $this->revolvingFundId,
                'amount' => $this->balance,
                'description' => 'FORWARDED BALANCE',
                'prepared_by' => Auth::user()->emp_id,
                'acknowledgement_id' => null,
                'balance' => $this->balance,
            ];
        }
        // for replenishment
        $this->currentBatchData[] = [
            'forwarded_revolving_fund_id' => null,
            'amount' => $this->checkAmount,
            'description' => 'REPLENISHMENT',
            'prepared_by' => Auth::user()->emp_id,
            'acknowledgement_id' => $this->acknowlegementId,
            'balance' => $this->balance + $this->checkAmount,
        ];
    }
    public function cancelReplenish()
    {
        $this->replenishModal = false;
        $this->acknowlegementId = null;
        $this->source = null;
        $this->checkName = null;
        $this->checkDate = null;
        $this->checkNumber = null;
        $this->checkAmount = null;
        $this->note = null;
        $this->currentBatchData = [];
    }
};
?>

<div class="space-y-6">
    <x-ts-loading delay="longest" />
    <x-ts-tab selected="Active">
        <x-ts-tab.items tab="Active">

            <!-- SUMMARY CARD -->
            <x-ts-card class=" p-6">
                <div class="flex justify-between items-start">

                    <!-- Left Side: Ref No & Balance -->
                    <div>
                        <div class="flex items-center space-x-3 mb-2">
                            <h2 class="text-xl font-bold">Batch reference#</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ strtoupper($reference) }}
                            </span>
                        </div>
                        <p class="text-sm">Replenished Amount : ₱{{ number_format($replenishedAmount, 2) }}</p>
                        <p class="text-sm mt-4">Current Balance</p>
                        <h1 class="text-4xl font-black  mt-1">
                            ₱{{ number_format($balance, 2) }}
                        </h1>
                    </div>
                    <x-ts-button icon="arrow-path" wire:click="$toggle('replenishModal')" loading>Replenish &
                        Rollover</x-ts-button>
                </div>

                <!-- Visual Progress Bar indicator sa Balance -->
                <div class="mt-6">
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>Depletion Level</span>
                        <span>Ceiling: ₱{{ number_format($ceilingAmount, 2) }}</span>
                    </div>
                    @if ($expenditurePercentage >= $maxEpenditurePercentage)
                        <x-ts-progress percent="{{ number_format($expenditurePercentage, 2) }}" md floating
                            color="orange" light />
                    @else
                        <x-ts-progress percent="{{ number_format($expenditurePercentage, 2) }}" md floating />
                    @endif
                    @if ($expenditurePercentage >= $maxEpenditurePercentage)
                        <p class="text-xs text-red-600 mt-2 font-medium">⚠️ Low balance! Consider replenishing soon.</p>
                    @endif
                </div>
            </x-ts-card>

            <!-- RECENT TRANSACTIONS TABLE -->
            <x-ts-card class="mt-4 verflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Batch Ledger</h3>
                </div>

                <x-ts-table :$headers :$rows striped expandable>
                    @interact('column_reference', $row)
                    @if ($row->description == 'REPLENISHMENT')
                        <x-ts-button class="font-mono" flat>{{ $row->acknowledgement->reference }}</x-ts-button>
                    @elseif($row->description == 'FORWARDED BALANCE')
                        {{ $row->forwardedRevolvingFund->reference ?? 0 }}
                    @elseif($row->description == 'PETTY CASH VOUCHER')
                        <x-ts-button class="font-mono" flat>{{ $row->pettyCashVoucher->reference }}
                            <x-slot:right>
                                @if ($row->pettyCashVoucher->status == 'OPEN')
                                    <x-ts-badge color="yellow" text="{{ $row->pettyCashVoucher->status }}" round light
                                        xs />
                                @elseif($row->pettyCashVoucher->status == 'CLOSED')
                                    <x-ts-badge color="green" text="{{ $row->pettyCashVoucher->status }}" round light
                                        xs />
                                @elseif($row->pettyCashVoucher->status == 'DRAFT')
                                    <x-ts-badge color="gray" text="{{ $row->pettyCashVoucher->status }}" round light
                                        xs />
                                @else
                                    <x-ts-badge color="red" text="{{ $row->pettyCashVoucher->status }}" round light
                                        xs />
                                @endif
                            </x-slot:right>
                        </x-ts-button>
                    @elseif($row->description == 'CASH RETURN')
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
                     @elseif($row->description == 'CASH RETURN - CA')
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
                    @interact('column_created_at', $row)
                    {{ \Illuminate\Support\Carbon::parse($row->trans_date)->format('M. d, Y') }}
                    @endinteract
                    @interact('column_amount' , $row)
                    @if ($row->type === 'IN')
                        <x-ts-badge :text="'+ ₱ ' . number_format($row->amount, 2)" color="green" outline />
                    @else
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
                    @if ($row->description == 'REPLENISHMENT')
                        @php
                            $headers = [
                                ['index' => 'reference', 'label' => 'acknowledgement ref.'],
                                ['index' => 'source', 'label' => 'Source'],
                                ['index' => 'check_name', 'label' => 'Check name'],
                                ['index' => 'check_date', 'label' => 'Check date'],
                                ['index' => 'check_number', 'label' => 'Check number'],
                                ['index' => 'check_amount', 'label' => 'Check amount'],
                            ];
                            $rows = [
                                [
                                    'reference' => $row->acknowledgement->reference,
                                    'source' => $row->acknowledgement->customer?->full_name,
                                    'check_name' => $row->acknowledgement->account_name,
                                    'check_date' => \Carbon\Carbon::parse($row->acknowledgement->check_date)->format(
                                        'M d, Y',
                                    ),
                                    'check_number' => $row->acknowledgement->check_number,
                                    'check_amount' => '₱ ' . number_format($row->acknowledgement->check_amount, 2),
                                ],
                            ];
                        @endphp
                        <x-ts-table :headers="$headers" :rows="$rows" />
                    @elseif($row->description == 'PETTY CASH VOUCHER')
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
                                    'payee' => $row->pettyCashVoucher->paid_to_employee_id
                                        ? $row->pettyCashVoucher->paidToEmployee?->full_name
                                        : $row->pettyCashVoucher->paidToCustomer?->full_name,
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
                                ['index' => 'pcv_amount', 'label' => 'cA amount'],
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
                    @elseif($row->description == 'CASH RETURN - CA')
                        @php
                            $headers = [
                                ['index' => 'preparedBy', 'label' => 'prepared by'],
                                ['index' => 'ca', 'label' => 'linked cash advance'],
                                ['index' => 'ca_amount', 'label' => 'pcv amount'],
                                ['index' => 'note', 'label' => 'note'],
                            ];
                            $rows = [
                                [
                                    'preparedBy' => $row->cashReturn->preparedBy?->full_name,
                                    'ca' => $row->cashReturn->employeeCashAdvance?->reference,
                                    'ca_amount' =>
                                        '₱ ' . number_format($row->cashReturn->employeeCashAdvance->amount, 2),
                                    'note' => $row->cashReturn->notes,
                                ],
                            ];
                        @endphp
                        <x-ts-table :headers="$headers" :rows="$rows" />
                    @endif
                    @endinteract
                </x-ts-table>
            </x-ts-card>


            <x-ts-modal id="modal-ids" wire="replenishModal" z-index="z-10" center>
                <x-ts-card>
                    <x-ts-select.styled :request="route('api.get.active.acknowledgement-for-revolving-fund', [
                        'branch_id' => auth()->user()->branch_id,
                    ])"
                        select="label:reference|value:id|description:additional_details" label="Acknowledgement"
                        wire:model.live='acknowlegementId' :placeholders="[
                            'default' => 'Select',
                            'empty' => 'No acknowledgement found',
                        ]" required id="acknowledgementSelect" />
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <x-ts-input label="Source" readonly wire:model='source' />
                        <x-ts-input label="Check name" readonly wire:model='checkName' />
                        <x-ts-input label="Check date" readonly wire:model='checkDate' />
                        <x-ts-input label="Check number" readonly wire:model='checkNumber' />
                        <x-ts-input label="Check amount" readonly wire:model='checkAmount' />
                        <x-ts-textarea label="Note" readonly wire:model='note'>
                        </x-ts-textarea>

                    </div>
                </x-ts-card>
                <x-slot:footer>
                    <x-ts-button flat wire:click='cancelReplenish'>Cancel</x-ts-button>
                    <x-ts-button wire:click='replenishmentAction'>Confirm</x-ts-button>
                </x-slot:footer>
            </x-ts-modal>
        </x-ts-tab.items>
        <x-ts-tab.items tab="Batch History">
            <x-ts-table :headers="$batchHistoryHeader" :rows="$batchHistoryRows" striped :sort expandable>
                @interact('column_status', $row)
                @if ($row->status == 'OPEN')
                    <x-ts-badge color="yellow">OPEN</x-ts-badge>
                @elseif($row->status == 'CLOSED')
                    <x-ts-badge color="green">CLOSED</x-ts-badge>
                @endif
                @endinteract
                @interact('column_reference', $row)
                {{ $row->reference }}
                @endinteract
                @interact('column_replenished_amount', $row)
                ₱ {{ number_format($row->replenished_amount, 2) }}
                @endinteract
                @interact('column_starting_balance', $row)
                ₱ {{ number_format($row->starting_balance, 2) }}
                @endinteract
                @interact('column_ending_balance', $row)
                @if ($row->ending_balance == 0)
                    N/A
                @else
                    ₱ {{ number_format($row->ending_balance, 2) }}
                @endif
                @endinteract
                @interact('column_opened_at', $row)
                {{ \Carbon\Carbon::parse($row->opened_at)->format('M d, Y') }}
                @endinteract
                @interact('column_closed_at', $row)
                {{ \Carbon\Carbon::parse($row->closed_at)->format('M d, Y') }}
                @endinteract
                @interact('sub_table', $row)
                @php
                    $insertRow = [];
                    foreach ($row->revolvingFundSnapshot as $index => $value) {
                        $insertRow[] = [
                            'created_at' => \Carbon\Carbon::parse($value->created_at)->format('M d, Y'),
                            'reference' => $value->reference,
                            'description' => $value->description,
                            'type' => $value->type,
                            'amount' => $value->amount,
                            'balance' => $value->balance,
                            'acknowledgement' => $value->acknowledgement->reference ?? null,
                            'pettyCashVoucher' => $value->pettyCashVoucher->reference ?? null,
                            'forwardedRevolvingFund' => $value->forwardedRevolvingFund->reference ?? null,
                            'cashReturn' => $value->cashReturn->reference ?? null,
                        ];
                    }
                @endphp
                <x-ts-table :headers="[
                    ['index' => 'created_at', 'label' => 'Date'],
                    ['index' => 'reference', 'label' => 'reference'],
                    ['index' => 'description', 'label' => 'description'],
                    ['index' => 'amount', 'label' => 'amount'],
                    ['index' => 'balance', 'label' => 'running balance'],
                ]" :rows="$insertRow">
                    @interact('column_reference', $row)
                        @if ($row['description'] == 'REPLENISHMENT')
                            {{ $row['acknowledgement'] }}
                        @elseif($row['description'] == 'FORWARDED BALANCE')
                            {{ $row['forwardedRevolvingFund'] }}
                        @elseif($row['description'] == 'PETTY CASH VOUCHER')
                            {{ $row['pettyCashVoucher'] }}
                        @elseif($row['description'] == 'CASH RETURN')
                            {{ $row['cashReturn'] }}
                        @endif
                        @endinteract()
                        @interact('column_amount', $row)
                            @if ($row['type'] == 'IN')
                                <x-ts-badge :text="'+ ₱ ' . number_format($row['amount'], 2)" color="green" outline />
                            @else
                                <x-ts-badge :text="'- ₱' . number_format($row['amount'], 2)" color="red" outline />
                            @endif
                        @endinteract
                    </x-ts-table>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
    </x-ts-tab>

    <x-ts-back-to-top lg />


</div>
