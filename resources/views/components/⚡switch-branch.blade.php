<?php

use Livewire\Component;
use App\Models\Business\Branch;
use App\Models\Settings\AssignedBranch;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Collection;

new class extends Component
{
    use Interactions;

    public string $search = '';

    public function selectBranch(int|string $branchId): void
    {
        $branchId = (int) $branchId;
        $user = auth()->user();

        if ($user && (int) $user->branch_id === $branchId) {
            $this->toast()->info('Already Active', 'You are already operating under this branch.')->send();
            return;
        }

        $targetBranch = Branch::where('id', $branchId)->where('branch_status', 'ACTIVE')->first();
        if (! $targetBranch) {
            $this->toast()->error('Branch Unavailable', 'Selected branch could not be found or is inactive.')->send();
            return;
        }

        // Trigger TallStackUI confirmation dialog
        $this->dialog()
            ->question(
                title: 'Switch Active Branch?',
                description: "Are you sure you want to switch your active branch to \"{$targetBranch->branch_name}\"? Your workspace and records will refresh to this branch."
            )
            ->confirm(
                text: 'Yes, Switch Branch',
                method: 'confirmSwitchBranch',
                params: $targetBranch->id
            )
            ->cancel('Cancel')
            ->send();
    }

    public function confirmSwitchBranch(int|string $branchId): void
    {
        $branchId = (int) $branchId;
        $branch = Branch::where('id', $branchId)->where('branch_status', 'ACTIVE')->first();

        if (! $branch) {
            $this->toast()->error('Switch Failed', 'Target branch could not be found or is inactive.')->send();
            return;
        }

        $user = auth()->user();
        $user->update(['branch_id' => $branch->id]);

        // Re-authenticate so current auth session has updated user model
        auth()->login($user->fresh());

        // Flash toast notification for display after page reload
        $this->toast()
            ->success('Branch Switched', "Active branch successfully changed to {$branch->branch_name}.")
            ->flash();

        // Reload current page to refresh all Livewire components and scoped data
        $referrer = request()->header('Referer');
        $this->redirect($referrer ?: route('dashboard'), navigate: false);
    }

    public function with(): array
    {
        $user = auth()->user();
        $currentBranch = null;
        $branches = collect();

        if ($user) {
            if ($user->branch_id) {
                $currentBranch = Branch::find($user->branch_id);
            }

            $assignedIds = [];
            if ($user->emp_id) {
                $assignedIds = AssignedBranch::where('employee_id', $user->emp_id)
                    ->pluck('branch_id')
                    ->filter()
                    ->toArray();
            }

            $query = Branch::query()->where('branch_status', 'ACTIVE');

            if ($user->isAdmin() && empty($assignedIds)) {
                // Admin with no specific branch assignments has access to all active branches
            } elseif (! empty($assignedIds)) {
                // User assigned branches plus current branch if set
                $ids = array_unique(array_filter(array_merge($assignedIds, (array) $user->branch_id)));
                $query->whereIn('id', $ids);
            } else {
                if ($user->isAdmin()) {
                    // all active
                } elseif ($user->branch_id) {
                    $query->where('id', $user->branch_id);
                }
            }

            if (! empty(trim($this->search))) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('branch_name', 'like', $term)
                      ->orWhere('branch_address', 'like', $term);
                });
            }

            $branches = $query->orderBy('branch_name')->get();
        }

        return [
            'currentBranch' => $currentBranch,
            'branches' => $branches,
        ];
    }
};
?>

<div class="relative inline-block text-left" x-data="{ open: false }">
    <!-- Trigger Button -->
    <button 
        type="button" 
        x-on:click="open = !open" 
        class="group inline-flex items-center gap-2 sm:gap-2.5 px-2.5 sm:px-3 py-1.5 rounded-xl  dark:border-gray-700/80 bg-white/90 dark:bg-gray-800/90 shadow-xs hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500/60 backdrop-blur-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 active:scale-[0.98] cursor-pointer"
        aria-haspopup="true"
        :aria-expanded="open"
        title="Click to switch active branch"
    >
        <!-- Icon Avatar -->
        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/70 text-primary-600 dark:text-primary-400 ring-1 ring-primary-500/20 group-hover:scale-105 transition-transform duration-200">
            <x-ts-icon icon="building-office-2" class="h-4 w-4" />
        </div>

        <!-- Label & Name -->
        <div class="flex flex-col text-left leading-tight">
            <div class="flex items-center gap-1.5">
                <!-- <span class="text-[9px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Active Branch
                </span> -->
                <!-- <span class="relative flex h-1.5 w-1.5" title="Active">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                </span> -->
            </div>
            <span class="max-w-[110px] sm:max-w-[170px] md:max-w-[220px] truncate text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ $currentBranch?->branch_name ?? 'Select Branch' }}
            </span>
        </div>

        <!-- Chevron Down -->
        {{-- <x-ts-icon 
            icon="chevron-down" 
            class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200 group-hover:text-gray-600 dark:group-hover:text-gray-300"
            ::class="{ 'rotate-180': open }" 
        /> --}}
    </button>

    <!-- Dropdown Menu Panel -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
        x-cloak
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        class="absolute left-0 mt-2 w-76 sm:w-84 rounded-2xl border border-gray-200/90 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 backdrop-blur-xl p-2.5 shadow-2xl ring-1 ring-black/5 z-50 divide-y divide-gray-100 dark:divide-gray-700/60"
    >
        <!-- Panel Header -->
        <div class="px-2 py-1.5 pb-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <x-ts-icon icon="arrows-right-left" class="h-4 w-4 text-primary-500" />
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                        Switch Branch
                    </h3>
                </div>
                <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300">
                    {{ count($branches) }} {{ Str::plural('branch', count($branches)) }}
                </span>
            </div>
            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                Select a branch to switch your active operational scope.
            </p>
        </div>

        <!-- Search Bar (Visible when there are 3+ branches or a query is typed) -->
        @if(count($branches) > 3 || !empty($search))
            <div class="px-1.5 py-2">
                <div class="relative">
                    <x-ts-icon icon="magnifying-glass" class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" />
                    <input 
                        type="text" 
                        wire:model.live.debounce.200ms="search" 
                        placeholder="Search branches..." 
                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/60 pl-8 pr-3 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:bg-white dark:focus:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all"
                    />
                </div>
            </div>
        @endif

        <!-- Branches List -->
        <div class="max-h-64 overflow-y-auto px-1 py-1.5 space-y-1">
            @forelse($branches as $branch)
                @php
                    $isActive = (int) auth()->user()->branch_id === (int) $branch->id;
                @endphp
                <button
                    type="button"
                    @if(! $isActive)
                        x-on:click="open = false; $wire.selectBranch({{ $branch->id }})"
                    @endif
                    @class([
                        'group/item w-full flex items-center justify-between rounded-xl p-2 text-left transition-all duration-150',
                        'bg-primary-50/80 dark:bg-primary-950/40 text-primary-900 dark:text-primary-100 ring-1 ring-primary-500/20 dark:ring-primary-500/30 cursor-default' => $isActive,
                        'hover:bg-gray-100/80 dark:hover:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white cursor-pointer active:scale-[0.99]' => !$isActive,
                    ])
                >
                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                        <div @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                            'bg-primary-600 text-white shadow-xs' => $isActive,
                            'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 group-hover/item:bg-primary-500 group-hover/item:text-white' => !$isActive,
                        ])>
                            <x-ts-icon icon="building-office" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold {{ $isActive ? 'text-primary-950 dark:text-white' : 'text-gray-800 dark:text-gray-200' }}">
                                {{ $branch->branch_name }}
                            </p>
                            @if($branch->branch_address)
                                <p class="truncate text-[10px] text-gray-400 dark:text-gray-500">
                                    {{ $branch->branch_address }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if($isActive)
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-950/70 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300">
                            <x-ts-icon icon="check" class="h-3 w-3" />
                            Current
                        </span>
                    @else
                        <span class="inline-flex shrink-0 items-center gap-1 text-[11px] font-medium text-gray-400 group-hover/item:text-primary-600 dark:group-hover/item:text-primary-400 transition-colors">
                            Switch
                            <x-ts-icon icon="arrow-right" class="h-3 w-3 transition-transform group-hover/item:translate-x-0.5" />
                        </span>
                    @endif
                </button>
            @empty
                <div class="px-3 py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                    <x-ts-icon icon="exclamation-circle" class="mx-auto h-6 w-6 text-gray-400 mb-1" />
                    No matching branches found.
                </div>
            @endforelse
        </div>

        <!-- Panel Footer -->
        <div class="pt-2 px-2 flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
            
            <span class="font-medium text-gray-500 dark:text-gray-400">
                Auto-refreshes workspace
            </span>
        </div>
    </div>
</div>