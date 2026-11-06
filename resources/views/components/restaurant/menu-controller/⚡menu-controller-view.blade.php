<?php

use App\Services\Restaurant\MenuControllerService;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public int $scheduleId;
    public string $controllerName = '';
    public ?string $effectiveDate = null;
    public ?string $endDate = null;
    public bool $isAvailable = false;
    public array $selectedDays = [];
    public array $recipeItems = [];
    public ?string $notes = null;
    public ?string $preparedBy = null;

    public function mount(int $id, MenuControllerService $service): void
    {
        $this->scheduleId = $id;
        $controller = $service->findForBranch($id, $this->branchId());

        $this->controllerName = $controller->control_name ?? '';
        $this->effectiveDate = $controller->start_date;
        $this->endDate = $controller->end_date;
        $this->isAvailable = (bool) $controller->is_available;
        $this->notes = $controller->notes;
        $this->preparedBy = $controller->preparedBy?->full_name;
        $this->selectedDays = array_keys(array_filter([
            'Monday' => $controller->mon,
            'Tuesday' => $controller->tue,
            'Wednesday' => $controller->wed,
            'Thursday' => $controller->thu,
            'Friday' => $controller->fri,
            'Saturday' => $controller->sat,
            'Sunday' => $controller->sun,
        ]));
        $this->recipeItems = $controller->Branchrecipes
            ->map(static fn ($branchRecipe): array => [
                'menu_image' => $branchRecipe->recipe?->menu_image,
                'name' => $branchRecipe->recipe?->menu_name ?? 'Recipe unavailable',
                'category' => $branchRecipe->recipe?->category?->category_name ?? 'Uncategorized',
                'price' => (float) ($branchRecipe->recipe?->rate?->amount ?? 0),
            ])
            ->all();
    }

    public function toggleAvailability(MenuControllerService $service): void
    {
        $controller = $service->setAvailability(
            $this->scheduleId,
            $this->branchId(),
            ! $this->isAvailable,
        );

        $this->isAvailable = (bool) $controller->is_available;
        $this->toast()->success(
            'Updated',
            $this->isAvailable ? 'Menu schedule activated.' : 'Menu schedule deactivated.',
        )->send();
    }

    private function branchId(): int
    {
        $branchId = auth()->user()?->branch_id;
        abort_unless($branchId, 403);

        return (int) $branchId;
    }
};
?>

<div class="min-h-screen bg-slate-50 p-4 md:p-6">
    <div class="mx-auto max-w-7xl space-y-6">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Restaurant', 'link' => route('restaurant.menu-controller-summary'), 'icon' => 'cog'],
            ['label' => 'Menu Schedules', 'link' => route('restaurant.menu-controller-summary'), 'icon' => 'list-bullet'],
            ['label' => 'View Schedule', 'icon' => 'eye'],
        ]" />

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-bold text-slate-800">{{ $controllerName }}</h1>
                        @if($isAvailable)
                            <x-ts-badge text="ACTIVE" color="green" />
                        @else
                            <x-ts-badge text="INACTIVE" color="rose" />
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Recipe schedule details and included menu items.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ts-button
                        :text="$isAvailable ? 'Set Inactive' : 'Set Active'"
                        :color="$isAvailable ? 'rose' : 'green'"
                        wire:click="toggleAvailability"
                    />
                    <x-ts-button text="Edit Schedule" color="blue" :href="route('restaurant.menu-controller-edit', ['id' => $scheduleId])" />
                    <x-ts-button text="Back to Schedules" color="slate" :href="route('restaurant.menu-controller-summary')" />
                </div>
            </header>

            <div class="space-y-7 p-6">
                <section>
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-500">Schedule Information</h2>
                    <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase text-slate-500">Effective Date</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $effectiveDate ? \Illuminate\Support\Carbon::parse($effectiveDate)->format('M d, Y') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-slate-500">End Date</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $endDate ? \Illuminate\Support\Carbon::parse($endDate)->format('M d, Y') : 'No end date' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-slate-500">Prepared By</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $preparedBy ?: 'Unknown' }}</dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-500">Available Days</h2>
                    @if($selectedDays !== [])
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedDays as $day)
                                <x-ts-badge :text="$day" color="green" />
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No days have been selected.</p>
                    @endif
                </section>

                @if($notes)
                    <section>
                        <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-slate-500">Notes</h2>
                        <p class="whitespace-pre-line text-sm text-slate-700">{{ $notes }}</p>
                    </section>
                @endif

                <section class="space-y-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Included Recipes</h2>
                    @if($recipeItems !== [])
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Menu Image</th>
                                        <th class="px-4 py-3">Recipe</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3 text-right">Price</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($recipeItems as $recipe)
                                        <tr>
                                            <td class="px-4 py-3">
                                                @if($recipe['menu_image'])
                                                    <img src="{{ asset('storage/' . $recipe['menu_image']) }}" alt="Recipe Image" class="h-16 w-16 rounded object-cover" />
                                                @else
                                                    <div class="flex h-16 w-16 items-center justify-center rounded bg-slate-100 text-slate-400">No Image</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-medium text-slate-800">{{ $recipe['name'] }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $recipe['category'] }}</td>
                                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format($recipe['price'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">No recipes are assigned to this schedule.</p>
                    @endif
                </section>
            </div>
        </section>
    </div>
    <x-ts-back-to-top lg/>
</div>
