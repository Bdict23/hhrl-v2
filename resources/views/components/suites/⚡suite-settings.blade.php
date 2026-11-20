<?php

use Livewire\Component;
use App\Models\Suite\ResortSetting;
use Livewire\WithPagination;
use App\Models\Suite\RoomCategory;
use App\Models\Suite\Amenity;
use App\Models\Suite\RoomAddonService;
use TallStackUi\Traits\Interactions;
use App\Services\Suite\AmenityService;
use App\Services\Suite\RoomCategoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;
    use Interactions;

    public int $tabNum = 1;
    public array $form = [];
    public bool $createCategoryModal = false;
    public ?int $categoryId = null;
    public bool $createAmenityModal = false;
    public bool $editAmenityModal = false;
    public ?int $amenityId = null;
    public string $categoryName = '';
    public string $categorySlug = '';
    public string $categoryDescription = '';
    public int $categorySortOrder = 0;
    public bool $categoryIsActive = true;
    public string $amenityName = '';

    // Add-on Service form state
    public bool $createAddonServiceModal = false;
    public bool $editAddonServiceModal = false;
    public ?int $addonServiceId = null;
    public string $addonServiceName = '';
    public string $addonServiceDescription = '';
    public string $addonServiceIcon = '';
    public string $addonServicePricingType = 'flat';
    public float $addonServicePrice = 0.0;
    public bool $addonServiceIsActive = true;
    public int $addonServiceSortOrder = 0;

    public function mount(): void
    {
        foreach (ResortSetting::all() as $setting) {
            $this->form[$setting->key] = $setting->value;
        }
    }

    public function getSettingsProperty()
    {
        $query = ResortSetting::orderBy('group')->orderBy('key');

        if ($this->tabNum === 1) {
            $query->where('group', 'general');
        } elseif ($this->tabNum === 2) {
            $query->where('group', 'room_categories');
        } elseif ($this->tabNum === 3) {
            $query->where('group', 'amenities');
        }

        return $query->get()->groupBy('group');
    }

    public function with(): array
    {
        return [
            'categoryHeaders' => [
                ['index' => 'name', 'label' => 'name'],
                ['index' => 'slug', 'label' => 'slug'],
                ['index' => 'description', 'label' => 'rooms', 'sortable' => false],
                ['index' => 'sort_order', 'label' => 'sort order'],
                ['index' => 'is_active', 'label' => 'status', 'sortable' => false],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'amenityHeaders' => [
                ['index' => 'name', 'label' => 'Amenity Name'],
                ['index' => 'linked', 'label' => 'Linked Rooms'],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'categoryRows' => RoomCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'amenityRows' => Amenity::withCount('rooms')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'addonServiceHeaders' => [
                ['index' => 'name', 'label' => 'Service Name'],
                ['index' => 'price', 'label' => 'Price', 'sortable' => false],
                ['index' => 'pricing_type', 'label' => 'Pricing Type', 'sortable' => false],
                ['index' => 'is_active', 'label' => 'Status', 'sortable' => false],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'addonServiceRows' => RoomAddonService::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
        ];
    }

    public function save(): void
    {
        foreach ($this->form as $key => $value) {
            ResortSetting::where('key', $key)->update(['value' => $value]);
        }

        $this->toast()->success('Success', ' Settings updated successfully!')->send();

    }

    public function updatedCategoryName(string $value): void
    {
        $this->categorySlug = Str::slug($value);
    }

    public function openCategoryModal(): void
    {
        $this->resetCategoryForm();
        $this->resetValidation();
        $this->createCategoryModal = true;
    }

    public function editCategoryAction(int $id): void
    {
        $category = RoomCategory::query()->findOrFail($id);

        $this->categoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categorySlug = $category->slug;
        $this->categoryDescription = $category->description ?? '';
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = $category->is_active;
        $this->resetValidation();
        $this->createCategoryModal = true;
    }

    public function saveCategoryAction(): void
    {
        $this->validate($this->categoryRules($this->categoryId));
        $this->createCategoryModal = false;
        $this->dialog()
            ->question(
                $this->categoryId ? 'Update Category?' : 'Save Category?',
                $this->categoryId ? 'Are you sure you want to update this category?' : 'Are you sure you want to save this category?'
            )
            ->confirm('Confirm', $this->categoryId ? 'updateCategory' : 'createCategory')
            ->cancel('Cancel')
            ->send();
    }

    public function createCategory(RoomCategoryService $service): void
    {
        $validated = $this->validate($this->categoryRules());

        try {
            $category = $service->create([
                'name' => $validated['categoryName'],
                'slug' => $validated['categorySlug'],
                'description' => $validated['categoryDescription'] ?: null,
                'sort_order' => $validated['categorySortOrder'],
                'is_active' => $validated['categoryIsActive'],
            ]);

            $this->createCategoryModal = false;
            $this->resetCategoryForm();
            $this->toast()->success('Success', $category->name.' category created successfully!')->send();
        } catch (QueryException $exception) {
            Log::error('Create room category failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to create the category. Please try again.')->send();
        }
    }

    public function updateCategory(RoomCategoryService $service): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:room_categories,id'],
            ...$this->categoryRules($this->categoryId),
        ]);

        try {
            $category = $service->update([
                'id' => $validated['categoryId'],
                'name' => $validated['categoryName'],
                'slug' => $validated['categorySlug'],
                'description' => $validated['categoryDescription'] ?: null,
                'sort_order' => $validated['categorySortOrder'],
                'is_active' => $validated['categoryIsActive'],
            ]);

            $this->createCategoryModal = false;
            $this->resetCategoryForm();
            $this->toast()->success('Success', $category->name.' category updated successfully!')->send();
        } catch (QueryException $exception) {
            Log::error('Update room category failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to update the category. Please try again.')->send();
        }
    }

    public function changeCategoryStatus(int $id): void
    {
        $category = RoomCategory::query()->findOrFail($id);
        $this->categoryId = $category->id;

        $this->dialog()
            ->question(
                $category->is_active ? 'Set Category Inactive?' : 'Set Category Active?',
                'Are you sure you want to '.($category->is_active ? 'set this category inactive?' : 'set this category active?')
            )
            ->confirm('Confirm', 'toggleCategoryStatus')
            ->cancel('Cancel')
            ->send();
    }

    public function toggleCategoryStatus(): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:room_categories,id'],
        ]);

        try {
            $category = RoomCategory::query()->findOrFail($validated['categoryId']);
            $category->update(['is_active' => ! $category->is_active]);

            $this->reset('categoryId');
            $this->toast()->success(
                'Success',
                $category->name.($category->is_active ? ' activated successfully!' : ' set inactive successfully!')
            )->send();
        } catch (QueryException $exception) {
            Log::error('Update room category status failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to update the category status. Please try again.')->send();
        }
    }

    public function openAmenityModal(): void
    {
        $this->resetAmenityForm();
        $this->resetValidation();
        $this->createAmenityModal = true;
    }

    public function saveAmenityAction(): void
    {
        $this->validate($this->amenityRules());

        $this->createAmenityModal = false;
        $this->dialog()
            ->question('Save Amenity?', 'Are you sure you want to save this amenity?')
            ->confirm('Confirm', 'createAmenity')
            ->cancel('Cancel')
            ->send();
    }

    public function createAmenity(AmenityService $service): void
    {
        $validated = $this->validate($this->amenityRules());

        try {
            $message = $service->create(['amenity_name' => $validated['amenityName']]);

            $this->createAmenityModal = false;
            $this->resetAmenityForm();
            $this->toast()->success('Success', $message)->send();
        } catch (QueryException $exception) {
            Log::error('Create amenity failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to create the amenity. Please try again.')->send();
        }
    }

    public function editAmenityAction(int $id): void
    {
        $amenity = Amenity::query()->findOrFail($id);
        $this->amenityId = $amenity->id;
        $this->amenityName = $amenity->name;
        $this->resetValidation();
        $this->editAmenityModal = true;
    }

    public function saveAmenityUpdateAction(): void
    {
        $this->validate($this->amenityRules($this->amenityId));

        $this->editAmenityModal = false;
        $this->dialog()
            ->question('Update Amenity?', 'Are you sure you want to update this amenity?')
            ->confirm('Confirm', 'updateAmenity')
            ->cancel('Cancel')
            ->send();
    }

    public function updateAmenity(AmenityService $service): void
    {
        $validated = $this->validate([
            'amenityId' => ['required', 'integer', 'exists:room_amenities,id'],
            ...$this->amenityRules($this->amenityId),
        ]);

        try {
            $message = $service->update([
                'id' => $validated['amenityId'],
                'amenity_name' => $validated['amenityName'],
            ]);

            $this->editAmenityModal = false;
            $this->resetAmenityForm();
            $this->toast()->success('Success', $message)->send();
        } catch (QueryException $exception) {
            Log::error('Update amenity failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to update the amenity. Please try again.')->send();
        }
    }

    public function deleteAmenityAction(int $id): void
    {
        $amenity = Amenity::query()->findOrFail($id);
        $this->amenityId = $amenity->id;

        $this->dialog()
            ->question('Delete Amenity?', 'Are you sure you want to delete '.$amenity->name.'?')
            ->confirm('Confirm', 'deleteAmenity')
            ->cancel('Cancel')
            ->send();
    }

    public function deleteAmenity(AmenityService $service): void
    {
        $validated = $this->validate([
            'amenityId' => ['required', 'integer', 'exists:room_amenities,id'],
        ]);

        try {
            $message = $service->delete(['id' => $validated['amenityId']]);
            $this->resetAmenityForm();
            $this->toast()->success('Success', $message)->send();
        } catch (\DomainException $exception) {
            $this->toast()->error('Unable to delete amenity', $exception->getMessage())->send();
        } catch (QueryException $exception) {
            Log::error('Delete amenity failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to delete the amenity. Please try again.')->send();
        }
    }

    private function categoryRules(?int $ignoreId = null): array
    {
        return [
            'categoryName' => ['required', 'string', 'max:255'],
            'categorySlug' => [
                'required',
                'string',
                'alpha_dash',
                'max:255',
                Rule::unique('room_categories', 'slug')->ignore($ignoreId),
            ],
            'categoryDescription' => ['nullable', 'string'],
            'categorySortOrder' => ['required', 'integer', 'min:0'],
            'categoryIsActive' => ['required', 'boolean'],
        ];
    }

    private function amenityRules(?int $ignoreId = null): array
    {
        return [
            'amenityName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_amenities', 'name')->ignore($ignoreId),
            ],
        ];
    }

    private function resetCategoryForm(): void
    {
        $this->reset('categoryId', 'categoryName', 'categorySlug', 'categoryDescription', 'categorySortOrder', 'categoryIsActive');
    }

    private function resetAmenityForm(): void
    {
        $this->reset('amenityName', 'amenityId');
    }

    public function openAddonServiceModal(): void
    {
        $this->resetAddonServiceForm();
        $this->resetValidation();
        $this->createAddonServiceModal = true;
    }

    public function editAddonServiceAction(int $id): void
    {
        $addonService = RoomAddonService::query()->findOrFail($id);

        $this->addonServiceId = $addonService->id;
        $this->addonServiceName = $addonService->name;
        $this->addonServiceDescription = $addonService->description ?? '';
        $this->addonServiceIcon = $addonService->icon ?? '';
        $this->addonServicePricingType = $addonService->pricing_type;
        $this->addonServicePrice = (float) $addonService->price;
        $this->addonServiceIsActive = $addonService->is_active;
        $this->addonServiceSortOrder = $addonService->sort_order;
        $this->resetValidation();
        $this->editAddonServiceModal = true;
    }

    public function saveAddonServiceAction(): void
    {
        $this->validate($this->addonServiceRules());
        $this->createAddonServiceModal = false;
        $this->dialog()
            ->question('Save Add-on Service?', 'Are you sure you want to save this add-on service?')
            ->confirm('Confirm', 'createAddonService')
            ->cancel('Cancel')
            ->send();
    }

    public function createAddonService(): void
    {
        $validated = $this->validate($this->addonServiceRules());

        try {
            RoomAddonService::create([
                'name' => $validated['addonServiceName'],
                'description' => $validated['addonServiceDescription'] ?: null,
                'icon' => $validated['addonServiceIcon'] ?: null,
                'pricing_type' => $validated['addonServicePricingType'],
                'price' => $validated['addonServicePrice'],
                'is_active' => $validated['addonServiceIsActive'],
                'sort_order' => $validated['addonServiceSortOrder'],
            ]);

            $this->createAddonServiceModal = false;
            $this->resetAddonServiceForm();
            $this->toast()->success('Success', $validated['addonServiceName'].' add-on service created successfully!')->send();
        } catch (QueryException $exception) {
            Log::error('Create add-on service failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to create the add-on service. Please try again.')->send();
        }
    }

    public function saveAddonServiceUpdateAction(): void
    {
        $this->validate($this->addonServiceRules($this->addonServiceId));
        $this->editAddonServiceModal = false;
        $this->dialog()
            ->question('Update Add-on Service?', 'Are you sure you want to update this add-on service?')
            ->confirm('Confirm', 'updateAddonService')
            ->cancel('Cancel')
            ->send();
    }

    public function updateAddonService(): void
    {
        $validated = $this->validate([
            'addonServiceId' => ['required', 'integer', 'exists:room_addon_services,id'],
            ...$this->addonServiceRules($this->addonServiceId),
        ]);

        try {
            $addonService = RoomAddonService::query()->findOrFail($validated['addonServiceId']);
            $addonService->update([
                'name' => $validated['addonServiceName'],
                'description' => $validated['addonServiceDescription'] ?: null,
                'icon' => $validated['addonServiceIcon'] ?: null,
                'pricing_type' => $validated['addonServicePricingType'],
                'price' => $validated['addonServicePrice'],
                'is_active' => $validated['addonServiceIsActive'],
                'sort_order' => $validated['addonServiceSortOrder'],
            ]);

            $this->editAddonServiceModal = false;
            $this->resetAddonServiceForm();
            $this->toast()->success('Success', $addonService->name.' add-on service updated successfully!')->send();
        } catch (QueryException $exception) {
            Log::error('Update add-on service failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to update the add-on service. Please try again.')->send();
        }
    }

    public function deleteAddonServiceAction(int $id): void
    {
        $addonService = RoomAddonService::query()->findOrFail($id);
        $this->addonServiceId = $addonService->id;

        $this->dialog()
            ->question('Delete Add-on Service?', 'Are you sure you want to delete '.$addonService->name.'?')
            ->confirm('Confirm', 'deleteAddonService')
            ->cancel('Cancel')
            ->send();
    }

    public function deleteAddonService(): void
    {
        $validated = $this->validate([
            'addonServiceId' => ['required', 'integer', 'exists:room_addon_services,id'],
        ]);

        try {
            $addonService = RoomAddonService::query()->findOrFail($validated['addonServiceId']);
            $name = $addonService->name;
            $addonService->delete();

            $this->resetAddonServiceForm();
            $this->toast()->success('Success', $name.' add-on service deleted successfully!')->send();
        } catch (QueryException $exception) {
            Log::error('Delete add-on service failed.', ['exception' => $exception]);
            $this->toast()->error('Error', 'Unable to delete the add-on service. Please try again.')->send();
        }
    }

    private function addonServiceRules(?int $ignoreId = null): array
    {
        return [
            'addonServiceName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_addon_services', 'name')->ignore($ignoreId),
            ],
            'addonServiceDescription' => ['nullable', 'string'],
            'addonServiceIcon' => ['nullable', 'string', 'max:100'],
            'addonServicePricingType' => ['required', 'string', 'in:flat,per_night,per_person'],
            'addonServicePrice' => ['required', 'numeric', 'min:0'],
            'addonServiceIsActive' => ['required', 'boolean'],
            'addonServiceSortOrder' => ['required', 'integer', 'min:0'],
        ];
    }

    private function resetAddonServiceForm(): void
    {
        $this->reset('addonServiceId', 'addonServiceName', 'addonServiceDescription', 'addonServiceIcon', 'addonServicePricingType', 'addonServicePrice', 'addonServiceIsActive', 'addonServiceSortOrder');
        $this->addonServicePricingType = 'flat';
        $this->addonServiceIsActive = true;
    }

};
?>

<div class="flex h-[calc(90vh-4rem)] overflow-hidden bg-slate-50 dark:bg-slate-900">
    {{-- Sidebar / Tab List --}}
    <aside class="hidden md:flex w-72 flex-shrink-0 flex-col border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Resort Configuration</h2>
        </div>
        
        <div class="flex-1 overflow-y-auto py-3 px-3 space-y-1">
            {{-- Tab 1: General --}}
            <div wire:click="$set('tabNum', 1)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 1
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>General Settings</span>
            </div>

            {{-- Tab 2: Room Categories --}}
            <div wire:click="$set('tabNum', 2)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 2
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Room Categories</span>
            </div>

            {{-- Tab 3: Resort Amenities --}}
            <div wire:click="$set('tabNum', 3)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 3
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                <span>Resort Amenities</span>
            </div>

            {{-- Tab 4: Add-on Services --}}
            <div wire:click="$set('tabNum', 4)"
                class="group flex items-center gap-3 px-3.5 py-3 rounded-xl cursor-pointer transition-all font-medium text-sm
                    {{ $tabNum === 4
                        ? 'bg-green-700 text-white dark:bg-indigo-600 shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                <span>Add-on Services</span>
            </div>
        </div>
    </aside>

    {{-- Main Content Area --}}
    <div class="flex-1 flex flex-col min-w-0 bg-white dark:bg-slate-950 overflow-y-auto">
        <div class=" w-full mx-auto px-6 py-8 space-y-6">
            
            {{-- Flash Message --}}
            @if (session()->has('message'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ session('message') }}
                </div>
            @endif

            @if($tabNum === 1)
                <form wire:submit.prevent="save" class="space-y-6">
                    @forelse($this->settings as $group => $groupSettings)
                        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                            <h3 class="font-bold text-slate-900 dark:text-white text-lg mb-5 capitalize">{{ str_replace('_', ' ', $group) }}</h3>
                            
                            <div class="space-y-4">
                                @foreach($groupSettings as $setting)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5 capitalize">
                                            {{ str_replace('_', ' ', $setting->key) }}
                                        </label>
                                        
                                        @if(in_array($setting->key, ['pet_policy', 'resort_description']))
                                            <textarea wire:model="form.{{ $setting->key }}" rows="3"
                                                    class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                                        @else
                                            <input type="text" wire:model="form.{{ $setting->key }}"
                                                    class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="bg-white dark:bg-slate-900 rounded-2xl p-12 text-center shadow-sm border border-slate-200 dark:border-slate-800">
                            <p class="text-slate-500 dark:text-slate-400">No settings found for this section.</p>
                        </div>
                    @endforelse

                    @if($this->settings->isNotEmpty())
                        <div class="flex items-center justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl text-sm transition-colors shadow-sm">
                                Save Settings
                            </button>
                        </div>
                    @endif
                </form>
            @elseif($tabNum === 2)
                <div class="flex justify-end">
                    <x-ts-button sm icon="plus" position="left" wire:click="openCategoryModal">Add Category</x-ts-button>
                </div>
                <x-ts-table :headers="$categoryHeaders" :rows="$categoryRows" compact> 
                    @interact('column_is_active', $row)
                         <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $row->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600' }}">
                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    @endinteract
                    @interact('column_action', $row)
                        <div class="flex items-center gap-1.5">
                            <x-ts-button xs color="secondary" wire:click="editCategoryAction({{ $row->id }})">Edit</x-ts-button>
                            <x-ts-button xs color="secondary" wire:click="changeCategoryStatus({{ $row->id }})">
                                {{ $row->is_active ? 'Set inactive' : 'Set active' }}
                            </x-ts-button>
                        </div>
                    @endinteract
                </x-ts-table>
            @elseif($tabNum === 3)
            <div class="flex justify-end">
                <x-ts-button sm icon="plus" position="left" wire:click="openAmenityModal">Add Amenity</x-ts-button>
            </div>
                <x-ts-table :headers="$amenityHeaders" :rows="$amenityRows" compact paginate loading striped> 
                    @interact('column_linked', $row)
                         {{ $row->rooms_count }} rooms
                    @endinteract
                    @interact('column_action',$row)
                        <div class="flex items-center  gap-1.5">
                                    <x-ts-button xs color="secondary" wire:click="editAmenityAction({{ $row->id }})">Edit</x-ts-button>
                                    <x-ts-button xs color="rose" wire:click="deleteAmenityAction({{ $row->id }})">Delete</x-ts-button>
                                </div>
                    @endinteract
                </x-ts-table>
            @elseif($tabNum === 4)
            <div class="flex justify-end">
                <x-ts-button sm icon="plus" position="left" wire:click="openAddonServiceModal">Add Add-on Service</x-ts-button>
            </div>
                <x-ts-table :headers="$addonServiceHeaders" :rows="$addonServiceRows" compact paginate loading striped>
                    @interact('column_price', $row)
                        ₱{{ number_format((float) $row->price, 2) }}
                    @endinteract
                    @interact('column_pricing_type', $row)
                        @php
                            $pricingLabels = [
                                'flat' => ['label' => 'Flat Rate', 'class' => 'bg-blue-100 text-blue-800'],
                                'per_night' => ['label' => 'Per Night', 'class' => 'bg-violet-100 text-violet-800'],
                                'per_person' => ['label' => 'Per Person', 'class' => 'bg-amber-100 text-amber-800'],
                            ];
                            $pricingInfo = $pricingLabels[$row->pricing_type] ?? ['label' => $row->pricing_type, 'class' => 'bg-slate-100 text-slate-700'];
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $pricingInfo['class'] }}">
                            {{ $pricingInfo['label'] }}
                        </span>
                    @endinteract
                    @interact('column_is_active', $row)
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $row->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600' }}">
                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    @endinteract
                    @interact('column_action', $row)
                        <div class="flex items-center gap-1.5">
                            <x-ts-button xs color="secondary" wire:click="editAddonServiceAction({{ $row->id }})">Edit</x-ts-button>
                            <x-ts-button xs color="rose" wire:click="deleteAddonServiceAction({{ $row->id }})">Delete</x-ts-button>
                        </div>
                    @endinteract
                </x-ts-table>
            @endif
        </div>
    </div>

    <x-ts-modal :title="$categoryId ? 'Edit Category' : 'Create Category'" wire="createCategoryModal" size="xl">
        <form wire:submit.prevent="saveCategoryAction" class="space-y-4">
            <x-ts-input label="Category name" wire:model.live="categoryName" />
            <x-ts-input label="Slug" hint="Generated from the category name; you can edit it." wire:model="categorySlug" />
            <div>
                <label for="category-description" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                <textarea id="category-description" wire:model="categoryDescription" rows="3"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                @error('categoryDescription')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="category-sort-order" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Sort order</label>
                <input id="category-sort-order" type="number" min="0" wire:model="categorySortOrder"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                @error('categorySortOrder')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="categoryIsActive" class="rounded border-slate-300">
                Active
            </label>
            <div class="flex items-center gap-3 border-t border-stone-100 pt-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                    {{ $categoryId ? 'Save Changes' : 'Create Category' }}
                </button>
                <button type="button" wire:click="$set('createCategoryModal', false)" class="text-sm text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal title="Create Amenity" wire="createAmenityModal" size="xl">
        <x-ts-card loading loading="saveAmenityAction">
            <form wire:submit.prevent="saveAmenityAction" class="space-y-4">
                <x-ts-input label="Amenity name" hint="e.g. Smart TV" wire:model="amenityName" />
                <div class="flex items-center gap-3 pt-4 border-t border-stone-100">
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                        Create Amenity
                    </button>
                    <button type="button" wire:click="$set('createAmenityModal', false)" class="text-sm text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                </div>
            </form>
        </x-ts-card>
    </x-ts-modal>

    <x-ts-modal title="Edit Amenity" wire="editAmenityModal" size="xl">
        <x-ts-card loading loading="saveAmenityUpdateAction">
            <form wire:submit.prevent="saveAmenityUpdateAction" class="space-y-4">
                <x-ts-input label="Amenity name" wire:model="amenityName" />
                <div class="flex items-center gap-3 border-t border-stone-100 pt-4">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        Save Changes
                    </button>
                    <button type="button" wire:click="$set('editAmenityModal', false)" class="text-sm text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                </div>
            </form>
        </x-ts-card>
    </x-ts-modal>

    <x-ts-modal title="Add Add-on Service" wire="createAddonServiceModal" size="xl">
        <x-ts-card loading loading="saveAddonServiceAction">
            <form wire:submit.prevent="saveAddonServiceAction" class="space-y-4">
                <x-ts-input label="Service name" hint="e.g. Extra Towel Set" wire:model="addonServiceName" />
                <div>
                    <label for="addon-description" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                    <textarea id="addon-description" wire:model="addonServiceDescription" rows="2"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                    @error('addonServiceDescription')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <x-ts-input label="Icon (optional)" hint="e.g. towel" wire:model="addonServiceIcon" />
                <div>
                    <label for="addon-pricing-type" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Pricing Type</label>
                    <select id="addon-pricing-type" wire:model="addonServicePricingType"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="flat">Flat Rate</option>
                        <option value="per_night">Per Night</option>
                        <option value="per_person">Per Person</option>
                    </select>
                    @error('addonServicePricingType')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="addon-price" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Price (₱)</label>
                    <input id="addon-price" type="number" min="0" step="0.01" wire:model="addonServicePrice"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    @error('addonServicePrice')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="addon-sort-order" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Sort Order</label>
                    <input id="addon-sort-order" type="number" min="0" wire:model="addonServiceSortOrder"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    @error('addonServiceSortOrder')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="addonServiceIsActive" class="rounded border-slate-300">
                    Active
                </label>
                <div class="flex items-center gap-3 border-t border-stone-100 pt-4">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        Create Add-on Service
                    </button>
                    <button type="button" wire:click="$set('createAddonServiceModal', false)" class="text-sm text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                </div>
            </form>
        </x-ts-card>
    </x-ts-modal>

    <x-ts-modal title="Edit Add-on Service" wire="editAddonServiceModal" size="xl">
        <x-ts-card loading loading="saveAddonServiceUpdateAction">
            <form wire:submit.prevent="saveAddonServiceUpdateAction" class="space-y-4">
                <x-ts-input label="Service name" wire:model="addonServiceName" />
                <div>
                    <label for="edit-addon-description" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                    <textarea id="edit-addon-description" wire:model="addonServiceDescription" rows="2"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                    @error('addonServiceDescription')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <x-ts-input label="Icon (optional)" hint="e.g. towel" wire:model="addonServiceIcon" />
                <div>
                    <label for="edit-addon-pricing-type" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Pricing Type</label>
                    <select id="edit-addon-pricing-type" wire:model="addonServicePricingType"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="flat">Flat Rate</option>
                        <option value="per_night">Per Night</option>
                        <option value="per_person">Per Person</option>
                    </select>
                    @error('addonServicePricingType')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="edit-addon-price" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Price (₱)</label>
                    <input id="edit-addon-price" type="number" min="0" step="0.01" wire:model="addonServicePrice"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    @error('addonServicePrice')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="edit-addon-sort-order" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Sort Order</label>
                    <input id="edit-addon-sort-order" type="number" min="0" wire:model="addonServiceSortOrder"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    @error('addonServiceSortOrder')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="addonServiceIsActive" class="rounded border-slate-300">
                    Active
                </label>
                <div class="flex items-center gap-3 border-t border-stone-100 pt-4">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        Save Changes
                    </button>
                    <button type="button" wire:click="$set('editAddonServiceModal', false)" class="text-sm text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                </div>
            </form>
        </x-ts-card>
    </x-ts-modal>
</div>