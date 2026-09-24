<?php

use App\Models\CarouselSlide;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new  class extends Component {
    use WithFileUploads;

    public bool $showModal = false;
    public ?int $editingSlideId = null;

    public string $title = '';
    public string $description = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    /** @var mixed */
    public $image = null;
    public ?string $currentImagePath = null;

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->sortOrder = ((int) CarouselSlide::max('sort_order')) + 1;
        $this->showModal = true;
    }

    public function openEditModal(int $slideId): void
    {
        $slide = CarouselSlide::findOrFail($slideId);
        $this->editingSlideId = $slide->id;
        $this->title = (string) ($slide->title ?? '');
        $this->description = (string) ($slide->description ?? '');
        $this->sortOrder = (int) $slide->sort_order;
        $this->isActive = (bool) $slide->is_active;
        $this->currentImagePath = $slide->image_url;
        $this->image = null;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingSlideId = null;
        $this->title = '';
        $this->description = '';
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->image = null;
        $this->currentImagePath = null;
        $this->resetErrorBag();
    }

    private function storedImagePath(?string $imagePath): ?string
    {
        if (!$imagePath || str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return null;
        }

        if (str_starts_with($imagePath, 'assets/') || str_starts_with($imagePath, '/assets/')) {
            return null;
        }

        $path = parse_url($imagePath, PHP_URL_PATH) ?: $imagePath;
        $storageUrlPath = parse_url(Storage::disk('public')->url(''), PHP_URL_PATH);

        if ($storageUrlPath && str_starts_with($path, rtrim($storageUrlPath, '/') . '/')) {
            $path = substr($path, strlen(rtrim($storageUrlPath, '/')) + 1);
        }

        return ltrim($path, '/');
    }

    public function save(): void
    {
        $rules = [
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sortOrder'   => ['required', 'integer', 'min:0'],
            'isActive'    => ['required', 'boolean'],
        ];

        if ($this->editingSlideId) {
            $rules['image'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'];
        } else {
            $rules['image'] = ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'];
        }

        $this->validate($rules);

        if ($this->editingSlideId) {
            $slide = CarouselSlide::findOrFail($this->editingSlideId);

            $updates = [
                'title'       => $this->title !== '' ? $this->title : null,
                'description' => $this->description !== '' ? $this->description : null,
                'sort_order'  => $this->sortOrder,
                'is_active'   => $this->isActive,
            ];

            if ($this->image) {
                $oldImagePath = $this->storedImagePath($slide->image_path);
                if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }

                $path = $this->image->store('carousel', 'public');
                $updates['image_path'] = url(Storage::disk('public')->url($path));
            }

            $slide->update($updates);

            $this->dispatch('tallstackui:toast', [
                'type'        => 'success',
                'description' => 'Carousel slide updated successfully.',
            ]);
        } else {
            $path = $this->image->store('carousel', 'public');

            CarouselSlide::create([
                'title'       => $this->title !== '' ? $this->title : null,
                'description' => $this->description !== '' ? $this->description : null,
                'image_path'  => url(Storage::disk('public')->url($path)),
                'sort_order'  => $this->sortOrder,
                'is_active'   => $this->isActive,
            ]);

            $this->dispatch('tallstackui:toast', [
                'type'        => 'success',
                'description' => 'New carousel slide created successfully.',
            ]);
        }

        $this->closeModal();
    }

    public function toggleActive(int $slideId): void
    {
        $slide = CarouselSlide::findOrFail($slideId);
        $slide->update(['is_active' => !$slide->is_active]);

        $this->dispatch('tallstackui:toast', [
            'type'        => 'info',
            'description' => 'Slide visibility updated.',
        ]);
    }

    public function moveUp(int $slideId): void
    {
        $slide = CarouselSlide::findOrFail($slideId);
        $previousSlide = CarouselSlide::where('sort_order', '<', $slide->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previousSlide) {
            $prevOrder = $previousSlide->sort_order;
            $currentOrder = $slide->sort_order;

            if ($prevOrder === $currentOrder) {
                $slide->decrement('sort_order');
            } else {
                $previousSlide->update(['sort_order' => $currentOrder]);
                $slide->update(['sort_order' => $prevOrder]);
            }
        } elseif ($slide->sort_order > 0) {
            $slide->decrement('sort_order');
        }
    }

    public function moveDown(int $slideId): void
    {
        $slide = CarouselSlide::findOrFail($slideId);
        $nextSlide = CarouselSlide::where('sort_order', '>', $slide->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($nextSlide) {
            $nextOrder = $nextSlide->sort_order;
            $currentOrder = $slide->sort_order;

            if ($nextOrder === $currentOrder) {
                $slide->increment('sort_order');
            } else {
                $nextSlide->update(['sort_order' => $currentOrder]);
                $slide->update(['sort_order' => $nextOrder]);
            }
        } else {
            $slide->increment('sort_order');
        }
    }

    public function deleteSlide(int $slideId): void
    {
        $slide = CarouselSlide::findOrFail($slideId);

        $imagePath = $this->storedImagePath($slide->image_path);
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        $slide->delete();

        $this->dispatch('tallstackui:toast', [
            'type'        => 'success',
            'description' => 'Carousel slide deleted successfully.',
        ]);
    }

    public function with(): array
    {
        $slides = CarouselSlide::orderBy('sort_order', 'asc')->get();
        $activeSlides = $slides->where('is_active', true);

        $carouselImages = $activeSlides->isNotEmpty()
            ? $activeSlides->map(fn($s) => [
                'src'         => $s->image_url,
                'alt'         => $s->title ?? 'LYR Pickleball Club',
                'title'       => $s->title,
                'description' => $s->description,
            ])->values()->toArray()
            : [
                [
                    'src'         => '/assets/images/1.png',
                    'alt'         => 'Agro Inland Resort Pickleball Club',
                    'title'       => '',
                    'description' => '',
                ]
            ];

        return [
            'slides'         => $slides,
            'carouselImages' => $carouselImages,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Hero Carousel Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Upload and configure dynamic slides, reorder display sequences, and customize text overlays for the public booking banner.
            </p>
        </div>
        <div class="flex items-center gap-2">
            {{-- <a
                href="{{ route('welcome') }}"
                target="_blank"
                class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition flex items-center gap-1.5"
            > --}}
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>View Public Grid</span>
            </a>
            <button
                wire:click="openCreateModal"
                class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Upload New Slide</span>
            </button>
        </div>
    </div>

    <!-- LIVE HERO BANNER PREVIEW -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-lime-500 animate-pulse"></span>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                    Live Carousel Preview (Active Slides)
                </h3>
            </div>
            <span class="text-[11px] text-slate-500">
                Visual preview of how the banner appears on the public booking view
            </span>
        </div>

        <div class="w-full overflow-hidden rounded-xl sm:rounded-2xl border border-emerald-900/40 dark:border-emerald-800/40 shadow-md bg-slate-900">
            <x-ts-carousel
                :images="$carouselImages"
                autoplay
                clickable
                navigable 
                :interval="5"
                stop-on-hover
                :round="false"
                wrapper="w-full aspect-[21/9] sm:aspect-[3/1] min-h-[160px] max-h-[380px]"
            />
        </div>
    </div>

    <!-- SLIDES LIST TABLE -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase font-black tracking-wider text-[10px]">
                    <tr>
                        <th class="p-4 w-16 text-center">Order</th>
                        <th class="p-4 w-36">Image Preview</th>
                        <th class="p-4">Title & Description</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($slides as $slide)
                        <tr wire:key="slide-{{ $slide->id }}" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <!-- SORT ORDER CONTROLS -->
                            <td class="p-4 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="moveUp({{ $slide->id }})"
                                        class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-lime-500 transition"
                                        title="Move Up"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <span class="font-black text-slate-800 dark:text-slate-200 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
                                        {{ $slide->sort_order }}
                                    </span>
                                    <button
                                        type="button"
                                        wire:click="moveDown({{ $slide->id }})"
                                        class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-lime-500 transition"
                                        title="Move Down"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </td>

                            <!-- THUMBNAIL PREVIEW -->
                            <td class="p-4">
                                <div class="w-28 h-16 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-950 relative group">
                                    <img
                                        src="{{ $slide->image_url }}"
                                        alt="{{ $slide->title ?? 'Slide' }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
                                </div>
                            </td>

                            <!-- TITLE & DESCRIPTION -->
                            <td class="p-4">
                                <div>
                                    <h4 class="font-black text-sm text-slate-900 dark:text-white">
                                        {{ $slide->title ?: '(No Title Overlay)' }}
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 max-w-lg">
                                        {{ $slide->description ?: 'No description provided.' }}
                                    </p>
                                </div>
                            </td>

                            <!-- STATUS TOGGLE -->
                            <td class="p-4 text-center">
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $slide->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold transition cursor-pointer {{ $slide->is_active ? 'bg-lime-500/10 text-lime-600 dark:text-lime-400 border border-lime-500/20' : 'bg-slate-200 dark:bg-slate-800 text-slate-500 border border-transparent' }}"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full {{ $slide->is_active ? 'bg-lime-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                    <span>{{ $slide->is_active ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>

                            <!-- ACTIONS -->
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $slide->id }})"
                                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteSlide({{ $slide->id }})"
                                        wire:confirm="Are you sure you want to delete this slide? The image file will be permanently removed."
                                        class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 font-semibold transition"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="font-semibold">No carousel slides uploaded yet.</p>
                                    <p class="text-xs text-slate-500">Click "Upload New Slide" above to add your first banner image.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CREATE / EDIT MODAL -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95">
                <button
                    type="button"
                    wire:click="closeModal"
                    class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2"
                >
                    ✕
                </button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    {{ $editingSlideId ? 'Edit Carousel Slide' : 'Upload New Carousel Slide' }}
                </h3>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <!-- IMAGE UPLOAD & PREVIEW -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Banner Image {{ $editingSlideId ? '(Leave empty to keep existing)' : '*' }}
                        </label>

                        <!-- PREVIEW CONTAINER -->
                        @if ($image && method_exists($image, 'temporaryUrl') && rescue(fn() => $image->temporaryUrl()))
                            <div class="mb-3 rounded-2xl overflow-hidden border border-lime-500/50 bg-slate-950 aspect-[3/1] max-h-48 relative">
                                <img src="{{ $image->temporaryUrl() }}" alt="New preview" class="w-full h-full object-cover" />
                                <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-lime-500 text-slate-950 text-[10px] font-black uppercase">
                                    New Upload Preview
                                </div>
                            </div>
                        @elseif ($currentImagePath)
                            <div class="mb-3 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-950 aspect-[3/1] max-h-48 relative">
                                <img src="{{ $currentImagePath }}" alt="Current image" class="w-full h-full object-cover" />
                                <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-slate-800 text-slate-200 text-[10px] font-black uppercase">
                                    Current Image
                                </div>
                            </div>
                        @endif

                        <div class="relative">
                            <input
                                type="file"
                                wire:model="image"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs focus:outline-none focus:border-lime-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-black file:bg-lime-500 file:text-slate-950 hover:file:bg-lime-400 file:cursor-pointer"
                            />
                        </div>

                        <div wire:loading wire:target="image" class="text-lime-500 font-semibold mt-1">
                            Uploading and processing image...
                        </div>

                        @error('image')
                            <span class="text-red-500 mt-1 block font-medium">{{ $message }}</span>
                        @enderror

                        <p class="text-[11px] text-slate-500 mt-1">
                            Recommended dimensions: 2048 x 682 px (3:1 aspect ratio). Supported: PNG, JPG, JPEG, WEBP. Max 4MB.
                        </p>
                    </div>

                    <!-- TITLE INPUT -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Overlay Title (Optional)
                        </label>
                        <input
                            type="text"
                            wire:model="title"
                            placeholder="e.g. Tagum’s Premier Tournament Courts"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        />
                        @error('title') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- DESCRIPTION TEXTAREA -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Overlay Description (Optional)
                        </label>
                        <textarea
                            wire:model="description"
                            rows="3"
                            placeholder="Brief highlights or promotional details displayed over the banner..."
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        ></textarea>
                        @error('description') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- SORT ORDER -->
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Sort Order *</label>
                            <input
                                type="number"
                                wire:model="sortOrder"
                                min="0"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            />
                            @error('sortOrder') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- DISPLAY STATUS TOGGLE -->
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Display Status</label>
                            <div class="h-10 flex items-center">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="isActive"
                                        class="w-4 h-4 rounded text-lime-500 focus:ring-lime-400 bg-slate-100 dark:bg-slate-950 border-slate-300 dark:border-slate-700"
                                    />
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                        Active (Visible on public grid)
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- FORM ACTIONS -->
                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 font-bold transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 disabled:opacity-50"
                        >
                            <span wire:loading.remove>{{ $editingSlideId ? 'Update Slide' : 'Save & Publish' }}</span>
                            <span wire:loading>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
