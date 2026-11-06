<?php

use App\Models\DataManagement\Recipe;
use App\Services\Restaurant\MenuControllerService;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use WithPagination;

    public ?string $controlName = null;
    public ?string $effectiveDate = null;
    public ?string $endDate = null;
    public string $status = 'active';
    public array $selectedDays = [];
    public array $menuItems = [];
    public bool $recipeModal = false;
    public string $recipeSearch = '';

    protected function rules(): array
    {
        return [
            'controlName' => ['required', 'string', 'max:255'],
            'effectiveDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:effectiveDate'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'selectedDays' => ['required', 'array', 'min:1'],
            'selectedDays.*' => ['required', 'string', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'menuItems' => ['required', 'array', 'min:1'],
            'menuItems.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('menus', 'id')
                    ->where('company_id', $this->companyId())
                    ->where('status', 'AVAILABLE'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public ?string $notes = null;

    public function updatingRecipeSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $companyId = $this->companyId();

        return [
            'selectedMenuHeaders' => [
                ['index' => 'category_name', 'label' => 'Category'],
                ['index' => 'menu_name', 'label' => 'Recipe'],
                ['index' => 'price', 'label' => 'Price'],
                ['index' => 'action', 'label' => 'Action'],
            ],
            'recipeItemsHeaders' => [
                ['index' => 'menu_image', 'label' => 'Image'],
                ['index' => 'menu_code', 'label' => 'Menu Code'],
                ['index' => 'menu_name', 'label' => 'Recipe Name'],
                ['index' => 'category_name', 'label' => 'Category'],
                ['index' => 'menu_type', 'label' => 'Type'],
                ['index' => 'price', 'label' => 'Price'],
                ['index' => 'action', 'label' => 'Action'],
            ],
            'selectedMenuRows' => Recipe::query()
                ->with(['category', 'rate'])
                ->where('company_id', $companyId)
                ->whereIn('id', $this->menuItems)
                ->orderBy('menu_name')
                ->get(),
            'recipeItemsRows' => Recipe::query()
                ->with(['category', 'rate'])
                ->where('company_id', $companyId)
                ->where('status', 'AVAILABLE')
                ->when($this->recipeSearch !== '', function ($query): void {
                    $query->where('menu_name', 'like', '%' . $this->recipeSearch . '%')->orWhere('menu_code', 'like', '%' . $this->recipeSearch . '%');
                })
                ->orderBy('menu_name')
                ->paginate(10),
        ];
    }

    public function addMenuItem(int $recipeId): void
    {
        $recipe = Recipe::query()
            ->where('company_id', $this->companyId())
            ->where('status', 'AVAILABLE')
            ->findOrFail($recipeId);

        if (! in_array($recipe->id, $this->menuItems, true)) {
            $this->menuItems[] = $recipe->id;
        }

        $this->resetValidation('menuItems');
    }

    public function removeMenuItem(int $recipeId): void
    {
        $this->menuItems = array_values(array_filter(
            $this->menuItems,
            static fn (int|string $id): bool => (int) $id !== $recipeId,
        ));

        $this->resetValidation('menuItems');
    }

    public function resetForm(): void
    {
        $this->reset([
            'controlName',
            'effectiveDate',
            'endDate',
            'selectedDays',
            'menuItems',
            'recipeModal',
            'recipeSearch',
            'notes',
        ]);
        $this->status = 'active';
        $this->resetValidation();
        $this->resetPage();
    }

    public function save(MenuControllerService $service): Redirector
    {
        $validated = $this->validate();

        $controller = $service->create([
            ...$validated,
            'branch_id' => $this->branchId(),
            'created_by' => auth()->user()->emp_id,
        ]);

        $this->toast()->success('Saved', 'Menu schedule created successfully.')->send();

        return redirect()->route('restaurant.menu-controller-view', ['id' => $controller->id]);
    }

    private function branchId(): int
    {
        $branchId = auth()->user()?->branch_id;
        abort_unless($branchId, 403);

        return (int) $branchId;
    }

    private function companyId(): int
    {
        $companyId = auth()->user()?->branch?->company_id;
        abort_unless($companyId, 403);
        return (int) $companyId;
    }
};
?>

<div class="min-h-screen bg-slate-50 p-4 md:p-6">
    <div class="mx-auto max-w-7xl space-y-6">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Restaurant', 'link' => route('restaurant.menu-controller-summary'), 'icon' => 'cog'],
            ['label' => 'Menu Schedules', 'link' => route('restaurant.menu-controller-summary'), 'icon' => 'list-bullet'],
            ['label' => 'Create Schedule', 'icon' => 'plus'],
        ]" />

        <form wire:submit="save" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h1 class="text-xl font-bold text-slate-800">Create Recipe Schedule</h1>
                <p class="mt-1 text-sm text-slate-500">Set the schedule dates, availability, days and included recipes.</p>
            </div>

            <div class="space-y-7 p-6">
                <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-2">
                        <x-ts-input label="Menu Control Name *" wire:model="controlName" />
                        @error('controlName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-ts-date label="Effective Date *" wire:model="effectiveDate" />
                        @error('effectiveDate') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-ts-date label="End Date" wire:model="endDate" />
                        @error('endDate') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-ts-select.native
                            label="Status *"
                            wire:model="status"
                            :options="[
                                ['label' => 'Active', 'value' => 'active'],
                                ['label' => 'Inactive', 'value' => 'inactive'],
                            ]"
                            select="label:label|value:value"
                        />
                        @error('status') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <x-ts-textarea label="Notes" wire:model="notes" />
                        @error('notes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section>
                    <div class="mb-3">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">Schedule Availability *</h2>
                        <p class="mt-1 text-xs text-slate-500">Choose at least one day for this schedule.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $dayValue => $dayLabel)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="selectedDays" value="{{ $dayValue }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span>{{ $dayLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedDays') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('selectedDays.*') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                </section>

                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600">Recipes *</h2>
                            <p class="mt-1 text-xs text-slate-500">Choose the available recipes included in this schedule.</p>
                        </div>
                        <x-ts-button text="Add Recipe" icon="plus" color="green" type="button" wire:click="$set('recipeModal', true)" />
                    </div>

                    @error('menuItems') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                    <x-ts-card>
                        <x-ts-table :headers="$selectedMenuHeaders" :rows="$selectedMenuRows" compact>
                            @interact('column_category_name', $row)
                                {{ $row->category?->category_name ?? 'Uncategorized' }}
                            @endinteract
                            @interact('column_price', $row)
                                {{ number_format((float) ($row->rate?->amount ?? 0), 2) }}
                            @endinteract
                            @interact('column_action', $row)
                                <x-ts-button text="Remove" color="rose" size="sm" type="button" wire:click="removeMenuItem({{ $row->id }})" />
                            @endinteract
                        </x-ts-table>
                    </x-ts-card>
                </section>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:justify-end">
                <x-ts-button text="Reset" color="slate" type="button" wire:click="resetForm" />
                <x-ts-button text="Save Schedule" color="green" type="submit" />
            </div>
        </form>
    </div>

    <x-ts-modal title="Add Recipes" wire="recipeModal" size="5xl">
        <div class="space-y-4">
            <x-ts-input label="Search recipes" wire:model.live.debounce.300ms="recipeSearch" />
            <x-ts-card>
                <x-ts-table :headers="$recipeItemsHeaders" :rows="$recipeItemsRows" paginate compact>
                    @interact('column_menu_image', $row)
                        @if($row->menu_image)
                            <x-ts-avatar image="{{ asset('storage/'.$row->menu_image) }}" md square />
                        @else
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-xs font-bold text-emerald-700">
                                {{ strtoupper(substr($row->menu_name, 0, 2)) }}
                            </div>
                        @endif
                    @endinteract
                    @interact('column_category_name', $row)
                        {{ $row->category?->category_name ?? 'Uncategorized' }}
                    @endinteract
                    @interact('column_price', $row)
                        {{ number_format((float) ($row->rate?->amount ?? 0), 2) }}
                    @endinteract
                    @interact('column_action', $row)
                        @if(in_array($row->id, $this->menuItems, true))
                            <x-ts-badge text="Added" color="green" />
                        @else
                            <x-ts-button text="Add" color="green" size="sm" type="button" wire:click="addMenuItem({{ $row->id }})" />
                        @endif
                    @endinteract
                </x-ts-table>
            </x-ts-card>
        </div>
    </x-ts-modal>
</div>
