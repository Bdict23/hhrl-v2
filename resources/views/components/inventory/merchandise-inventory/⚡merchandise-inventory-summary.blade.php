<?php

use App\Services\DataManagement\ItemService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class () extends Component {
    use WithPagination;

    public ?string $search = null;
    public int $quantity = 20;
    public array $cardex = [];
    public array $sort = [
        'column' => 'item_description',
        'direction' => 'asc',
    ];

    public array $merchandiseHeader = [
        ['index' => 'balance', 'label' => 'Balance'],
        ['index' => 'available', 'label' => 'Available'],
        ['index' => 'reserved', 'label' => 'Reserved'],
        ['index' => 'item_code', 'label' => 'Item Code'],
        ['index' => 'item_description', 'label' => 'Item Description'],
        ['index' => 'uom_symbol', 'label' => 'Unit'],
        ['index' => 'item_status', 'label' => 'Status'],
    ];

    public function mount(): void
    {
        $this->cardex = app(ItemService::class)->cardexRecords(Auth::user()->branch_id, Auth::user()->branch->company_id);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedQuantity(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $rows = collect($this->cardex);
        $search = mb_strtolower(trim($this->search ?? ''));

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                foreach (['item_code', 'item_description', 'uom_symbol'] as $field) {
                    if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $search)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $sortableColumns = ['balance', 'available', 'reserved', 'item_description', 'uom_symbol'];
        $sortColumn = in_array($this->sort['column'] ?? null, $sortableColumns, true)
            ? $this->sort['column']
            : 'item_description';
        $sortDirection = in_array($this->sort['direction'] ?? null, ['asc', 'desc'], true)
            ? $this->sort['direction']
            : 'asc';

        $rows = $rows->sortBy($sortColumn, SORT_REGULAR, $sortDirection === 'desc')->values();

        $perPage = max(1, $this->quantity);
        $currentPage = max(1, $this->getPage());
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $currentPage = min($currentPage, $lastPage);

        return [
            'rows' => new LengthAwarePaginator(
                $rows->forPage($currentPage, $perPage)->values(),
                $rows->count(),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'pageName' => 'page'],
            ),
        ];
    }
};
?>

<div>
    <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-3">
        <x-ts-stats
            :number="number_format(collect($cardex)->sum('balance'), 2)"
            title="Total Balance"
        />
        <x-ts-stats
            :number="number_format(collect($cardex)->sum('available'), 2)"
            title="Total Available"
        />
        <x-ts-stats
            :number="number_format(collect($cardex)->sum('reserved'), 2)"
            title="Total Reserved"
        />
    </div>

    <x-ts-table
        :headers="$merchandiseHeader"
        :rows="$rows"
        :sort="$sort"
        :filter="['quantity' => 'quantity', 'search' => 'search']"
        :quantity="[10, 20, 50, 100]"
        striped
        compact
        paginate
        loading
        expandable
    >
        @interact('column_balance', $row)
            {{ number_format((float) ($row['balance'] ?? 0), 2) }}
        @endinteract

        @interact('column_available', $row)
            {{ number_format((float) ($row['available'] ?? 0), 2) }}
        @endinteract

        @interact('column_reserved', $row)
            {{ number_format((float) ($row['reserved'] ?? 0), 2) }}
        @endinteract

        @interact('column_item_description', $row)
            {{ $row['item_description'] ?? 'N/A' }}
        @endinteract

        @interact('column_uom_symbol', $row)
            {{ $row['uom_symbol'] ?? '-' }}
        @endinteract
        @interact('sub_table', $row)
            <x-ts-table
                :headers="[
                    ['index' => 'brand', 'label' => 'Brand'],
                    ['index' => 'classification', 'label' => 'Classification'],
                    ['index' => 'subclass', 'label' => 'Subclass'],
                    ['index' => 'category', 'label' => 'Category'],
                    ['index' => 'orderpoint', 'label' => 'Order Point'],
                    ['index' => 'optimal_stock', 'label' => 'Optimal Stock'],
                    ['index' => 'barcode', 'label' => 'Barcode'],
                    ['index' => 'is_forsale', 'label' => 'For Sale']
                ]"
                :rows="[$row]"
                striped
                compact
            >
                @interact('column_brand', $row)
                    {{ $row['brand'] ?? '-' }}
                @endinteract

                @interact('column_classification', $row)
                    {{ $row['classification'] ?? '-' }}
                @endinteract

                @interact('column_subclass', $row)
                    {{ $row['subclass'] ?? '-' }}
                @endinteract

                @interact('column_category', $row)
                    {{ $row['category'] ?? '-' }}
                @endinteract

                @interact('column_orderpoint', $row)
                    {{ $row['orderpoint'] ?? '-' }}
                @endinteract

                @interact('column_optimal_stock', $row)
                    {{ $row['optimal_stock'] ?? '-' }}
                @endinteract
                @interact('column_barcode', $row)
                    {{ $row['barcode'] ?? '-' }}
                @endinteract
                @interact('column_is_forsale', $row)
                    {{ ($row['is_forsale'] ?? false) ? 'Yes' : 'No' }}
                @endinteract
            </x-ts-table>
        @endinteract
    </x-ts-table>
</div>