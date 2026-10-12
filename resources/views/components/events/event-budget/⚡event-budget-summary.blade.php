<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BanquetEvent\BanquetProcurement;
use Illuminate\Support\Facades\Auth;
use App\Services\Event\BanquetProcurementService;
use TallStackUi\Traits\Interactions;





new class extends Component
{
    use WithPagination;
    use Interactions;


    public ?int $quantity = 10;
    public ?string $search = null;
    public ?string $status = null;
    public ?array $dates = null;
    public $budgetId = null;
    public bool $printPreviewModal = false;
    public ?int $previewBudgetId = null;

    public function openPrintPreview($id): void
    {
        $this->previewBudgetId = $id;
        $this->printPreviewModal = true;
    }

    public function exportPdf(?int $budgetId = null)
    {
        $targetId = $budgetId ?: $this->previewBudgetId ?: $this->budgetId;
        if (! $targetId) {
            return;
        }

        $budget = BanquetProcurement::with([
            'preparedBy.position',
            'event.customer',
            'event.menus.recipe.category',
            'event.menus.rate',
            'event.services.service',
            'event.services.price',
            'event.venues.venue',
            'event.venues.rate',
        ])->findOrFail($targetId);

        $logoPath = public_path('assets/images/1772440510.png');
        $logo = '';
        if (file_exists($logoPath)) {
            $logo = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        $notedEmp = \App\Models\Business\Employee::find($budget->noted_by);
        $approvedEmp = \App\Models\Business\Employee::find($budget->approved_by);
        $preparerEmp = $budget->preparedBy ?? \App\Models\Business\Employee::find($budget->created_by);

        return response()->streamDownload(function () use ($budget, $logo, $notedEmp, $approvedEmp, $preparerEmp) {
            echo \Spatie\LaravelPdf\Facades\Pdf::view('pdf.event-budget-summary', [
                'budget' => $budget,
                'event' => $budget->event,
                'preparer' => $preparerEmp,
                'reviewer' => $notedEmp,
                'approver' => $approvedEmp,
                'logo' => $logo,
                'company' => auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure',
                'branch' => auth()->user()->branch?->branch_name ?? 'Main Branch',
                'branch_address' => auth()->user()->branch?->branch_address ?? '',
            ])->format('a4')->generatePdfContent();
        }, 'Event-Budget-' . ($budget->reference_number ?: $budget->id) . '.pdf');
    }

    public array $sort = [
            'column' => 'created_at',
            'direction' => 'desc',
        ];


    public function rollbackAction($id)
        {
            $this->budgetId = $id;
        $this->dialog()
        ->question('Save event budget?', 'Are you sure to save this event budget as final?')
        ->confirm(
            'Confirm',
            'applyRollback', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
        }
    public function applyRollback(BanquetProcurementService $service)
    {
        try{
        $rollback = $service->applyRollback($this->budgetId);
        $this->budgetId = null;
        $this->toast()->success('Success', "BEB reference {$rollback->reference_number} rolled back successfully!")->send();
        }catch(\Exception $e)
        {
            \Log::error("BEB rollback Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while rolling back BEB: ' . $e->getMessage())->send();
        }
    }
    public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'reference_number', 'label' => 'reference', 'sortable' => false],
                ['index' => 'event_name', 'label' => 'event', 'sortable' => false],
                ['index' => 'suggested_amount', 'label' => 'Budget' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'prepared by',  'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date',  'sortable' => false],
                ['index' => 'action', 'label' => 'action'],

            ],
            'rows' => BanquetProcurement::query()
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference_number', 'like', "%{$this->search}%");
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->where('branch_id', Auth::user()->branch_id)
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
            'previewBudget' => $this->previewBudgetId
                ? BanquetProcurement::with([
                    'preparedBy.position',
                    'event.customer',
                    'event.menus.recipe.category',
                    'event.menus.rate',
                    'event.services.service',
                    'event.services.price',
                    'event.venues.venue',
                    'event.venues.rate',
                ])->find($this->previewBudgetId)
                : null,
        ];
    }
};
?>

<div>
    <div class="lg:flex lg:justify-between grid mb-4">
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                          ['label' => 'Event','link' => route('event-budget-summary'), 'icon' => 'calendar' ],
                          ['label' => 'Event budget summary', 'icon' => 'list-bullet'],
              ]"  />
                <div class="lg:flex gap-3 grid grid-cols-3">
                    <x-ts-select.native wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                            ['name' => 'All', 'id' => null],
                            ['name' => 'DRAFT', 'id' => 'PREPARING'],
                            ['name' => 'FOR APPROVAL', 'id' => 'PENDING'],
                            ['name' => 'APPROVED', 'id' => 'APPROVED'],
                            ['name' => 'REJECTED', 'id' => 'REJECTED'],
                    ]" select="label:name|value:id" />
                    <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                </div>
    </div>

    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
            @interact('column_created_at', $row)
                {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
            @endinteract
             @interact('column_reference', $row)
                {{ $row->reference }}
            @endinteract
            @interact('column_event_name', $row)
                {{ $row->event?->event_name }}
            @endinteract
            @interact('column_suggested_amount', $row)
               ₱ {{ number_format($row->suggested_amount ?? 0, 2) }}
            @endinteract
            
            @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'PREPARING')
                        <x-ts-badge text="DRAFT" color="gray" />
                    @elseif($row->status == 'PENDING')
                        <x-ts-badge text="FOR APPROVAL" color="teal" />
                    @elseif($row->status == 'APPROVED')
                        <x-ts-badge text="APPROVED" color="green" />
                    @elseif($row->status == 'REJECTED')
                        <x-ts-badge text="REJECTED" color="red" />
                    @endif
                </div>
            @endinteract
            @interact('column_prepared_by', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                </div>
            @endinteract
            @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                @if ($row->status == 'PREPARING')
                    <a href="{{ route('event-budget-edit', ['id' => $row->id]) }}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @else
                    <x-ts-dropdown.items text="Rollback" separator icon="arrow-path" wire:click="rollbackAction({{$row->id}})"/>
                @endif
                <a href="{{ route('event-budget-view', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <x-ts-dropdown.items text="Export to PDF" separator icon="document-arrow-down" wire:click="exportPdf({{ $row->id }})" />
                <x-ts-dropdown.items text="Preview" icon="eye" wire:click="openPrintPreview({{ $row->id }})" />
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>
    </div>

    {{-- BANQUET EVENT BUDGET PRINTABLE PREVIEW MODAL --}}
    <x-ts-modal wire="printPreviewModal" size="6xl" center>
        <x-slot:title>
            <div class="flex items-center gap-2">
                <x-ts-icon name="document-arrow-down" class="w-5 h-5 text-primary-600" />
                <span class="font-bold text-slate-800 dark:text-slate-100">
                    Export to PDF - Banquet Event Budget ({{ $previewBudget?->reference_number ?? '' }})
                </span>
            </div>
        </x-slot:title>

        <div class="space-y-4">
            {{-- Toolbar / Screen Controls (Hidden in Print) --}}
            <div class="flex items-center justify-between p-3 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 print:hidden">
                <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <x-ts-badge text="PDF Ready" color="green" light />
                    <span>Formatted for export via Spatie Laravel PDF.</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ts-button flat sm text="Close" wire:click="$set('printPreviewModal', false)" />
                    <x-ts-button sm icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf({{ $previewBudgetId }})" color="primary" />
                </div>
            </div>

            @if($previewBudget)
                @php
                    $event = $previewBudget->event;
                    $menuTotal = $event?->menus?->sum('total_amount') ?? 0;
                    $serviceTotal = $previewBudget->services_included ? ($event?->services?->sum('total_amount') ?? 0) : 0;
                    $grossTotal = $menuTotal + $serviceTotal;
                    $allocatedBudget = (float) ($previewBudget->suggested_amount ?? 0);
                    $budgetPct = $grossTotal > 0 ? number_format(($allocatedBudget / $grossTotal) * 100, 2) : '0.00';
                    $notedEmp = \App\Models\Business\Employee::find($previewBudget->noted_by);
                    $approvedEmp = \App\Models\Business\Employee::find($previewBudget->approved_by);
                    $preparerEmp = $previewBudget->preparedBy ?? \App\Models\Business\Employee::find($previewBudget->created_by);
                @endphp

                {{-- Document Sheet --}}
                <div id="printable-budget-summary-preview" class="bg-white text-slate-900 p-6 sm:p-10 rounded-2xl border border-slate-200 shadow-sm print:border-none print:shadow-none print:p-0 print:m-0 print:w-full">
                    {{-- Header with Company Logo --}}
                    <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5 mb-6">
                        <div class="flex items-center gap-4">
                            <img src="{{ asset('assets/images/1772440510.png') }}" alt="Company Logo" class="h-20 w-auto object-contain" />
                            <div>
                                <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wide text-slate-900">
                                    {{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }}
                                </h1>
                                <p class="text-xs text-slate-600 font-medium">
                                    {{ auth()->user()->branch?->branch_name ?? 'Main Branch' }}
                                    @if(auth()->user()->branch?->branch_address)
                                        • {{ auth()->user()->branch->branch_address }}
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500 font-medium">
                                    Banquet Event Procurement & Budget Allocation Division
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-3 py-1 rounded bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">
                                EVENT BUDGET
                            </span>
                            <div class="mt-2 text-lg font-black font-mono text-slate-900">
                                {{ $previewBudget->reference_number }}
                            </div>
                            <div class="text-xs text-slate-500">
                                Event Ref: <span class="font-mono font-bold">{{ $event?->reference ?? 'N/A' }}</span>
                            </div>
                            <div class="mt-1">
                                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded {{ $previewBudget->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : ($previewBudget->status === 'REJECTED' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    Status: {{ $previewBudget->status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Event Logistics Meta (NO logged-in user info) --}}
                    <div class="grid grid-cols-2 gap-4 mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                        <div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                                Event & Customer Profile
                            </h4>
                            <div class="space-y-1">
                                <div><span class="text-slate-500 font-medium">Event Name:</span> <strong class="text-slate-900">{{ $event?->event_name ?? 'N/A' }}</strong></div>
                                <div><span class="text-slate-500 font-medium">Customer / Client:</span> <span class="text-slate-800 font-semibold">{{ $event?->customer?->full_name ?? 'N/A' }}</span></div>
                                <div><span class="text-slate-500 font-medium">Include Services in Gross:</span> <span class="text-slate-800 font-semibold">{{ $previewBudget->services_included ? 'Yes (Included)' : 'No' }}</span></div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                                Schedule & Location
                            </h4>
                            <div class="space-y-1">
                                <div><span class="text-slate-500 font-medium">Event Dates:</span> <span class="text-slate-800">{{ $event?->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y') : 'N/A' }} to {{ $event?->end_date ? \Carbon\Carbon::parse($event->end_date)->format('M d, Y') : 'N/A' }}</span></div>
                                <div><span class="text-slate-500 font-medium">Location / Guests:</span> <span class="text-slate-800">{{ $event?->event_address ?? 'N/A' }} ({{ $event?->guest_count ?? 0 }} Pax)</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- Financial & Budget Allocation Highlight Cards --}}
                    <div class="grid grid-cols-4 gap-3 mb-6">
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Menu Subtotal</span>
                            <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $menuTotal, 2) }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Services Subtotal</span>
                            <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $serviceTotal, 2) }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Gross Order Total</span>
                            <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $grossTotal, 2) }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Allocated Budget ({{ $budgetPct }}%)</span>
                            <div class="text-base font-mono font-black text-emerald-700 mt-1">₱{{ number_format((float) $allocatedBudget, 2) }}</div>
                        </div>
                    </div>

                    {{-- 1. Menu Items Table --}}
                    @if($event && $event->menus->isNotEmpty())
                        <div class="mb-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                                <span>1. Menu Items Breakdown</span>
                                <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $menuTotal, 2) }}</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                            <th class="py-2 px-3 w-10 text-center">#</th>
                                            <th class="py-2 px-3">Menu / Recipe</th>
                                            <th class="py-2 px-3">Category</th>
                                            <th class="py-2 px-3 text-right">Rate</th>
                                            <th class="py-2 px-3 text-right">Qty</th>
                                            <th class="py-2 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($event->menus as $mIdx => $mItem)
                                            <tr>
                                                <td class="py-1.5 px-3 text-center text-slate-400">{{ $mIdx + 1 }}</td>
                                                <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $mItem->recipe?->menu_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-slate-600">{{ $mItem->recipe?->category?->category_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($mItem->price?->amount ?? $mItem->rate?->amount ?? 0), 2) }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $mItem->qty ?? 0 }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) $mItem->total_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- 2. Services Breakdown --}}
                    @if($event && $event->services->isNotEmpty())
                        <div class="mb-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                                <span>2. Services & Miscellaneous Breakdown</span>
                                <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $event->services->sum('total_amount'), 2) }}</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                            <th class="py-2 px-3 w-10 text-center">#</th>
                                            <th class="py-2 px-3">Service Name</th>
                                            <th class="py-2 px-3 text-right">Rate</th>
                                            <th class="py-2 px-3 text-right">Qty</th>
                                            <th class="py-2 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($event->services as $sIdx => $sItem)
                                            <tr>
                                                <td class="py-1.5 px-3 text-center text-slate-400">{{ $sIdx + 1 }}</td>
                                                <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $sItem->service?->service_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($sItem->price?->amount ?? 0), 2) }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $sItem->qty ?? 0 }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) $sItem->total_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Notes --}}
                    @if($previewBudget->notes)
                        <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                            <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Notes / Justification:</span>
                            <p class="mt-1 text-slate-800 whitespace-pre-line">{{ $previewBudget->notes }}</p>
                        </div>
                    @endif

                    {{-- Signatures Section --}}
                    <div class="grid grid-cols-3 gap-6 pt-6 mt-6 border-t border-slate-200 text-xs">
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $preparerEmp?->full_name ?? auth()->user()->name }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Prepared By</div>
                            <div class="text-[10px] text-slate-400">{{ $preparerEmp?->position_name ?? 'Procurement Officer' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $notedEmp?->full_name ?? '_________________________' }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Reviewed / Noted By</div>
                            <div class="text-[10px] text-slate-400">{{ $notedEmp?->position_name ?? 'Financial Reviewer' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $approvedEmp?->full_name ?? '_________________________' }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Approved By</div>
                            <div class="text-[10px] text-slate-400">{{ $approvedEmp?->position_name ?? 'General Manager' }}</div>
                        </div>
                    </div>

                    {{-- Document Tracking Footer --}}
                    <div class="mt-8 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
                        <div>{{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }} • Banquet Budget Management System</div>
                        <div>Generated on {{ now()->format('M d, Y h:i:s A') }}</div>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-ts-button flat text="Close" wire:click="$set('printPreviewModal', false)" />
                <x-ts-button icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf({{ $previewBudgetId }})" color="primary" />
            </div>
        </x-slot:footer>
    </x-ts-modal>

    <style>
        @media print {
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body * {
                visibility: hidden !important;
            }
            #printable-budget-summary-preview, #printable-budget-summary-preview * {
                visibility: visible !important;
            }
            #printable-budget-summary-preview {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 1.5cm !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
            }
            .print\:hidden {
                display: none !important;
            }
        }
    </style>

    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Budget" href="{{ route('event-budget-create')}}" navigate />
        </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
