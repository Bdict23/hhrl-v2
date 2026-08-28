<?php

use App\Models\PricingRule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public bool $showModal = false;
    public ?int $editingRuleId = null;

    public string $name = '';
    public string $startTime = '18:00:00';
    public string $endTime = '22:00:00';
    public string $type = 'multiplier';
    public float $rateAdjustment = 1.20;
    public array $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    public bool $isActive = true;

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $ruleId): void
    {
        $rule = PricingRule::findOrFail($ruleId);
        $this->editingRuleId = $rule->id;
        $this->name = $rule->name;
        $this->startTime = substr($rule->start_time, 0, 5);
        $this->endTime = substr($rule->end_time, 0, 5);
        $this->type = $rule->type;
        $this->rateAdjustment = (float) $rule->rate_adjustment;
        $this->daysOfWeek = $rule->days_of_week ?? [];
        $this->isActive = (bool) $rule->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingRuleId = null;
        $this->name = '';
        $this->startTime = '18:00';
        $this->endTime = '22:00';
        $this->type = 'multiplier';
        $this->rateAdjustment = 1.20;
        $this->daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $this->isActive = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'name'           => ['required', 'string', 'max:100'],
            'startTime'      => ['required'],
            'endTime'        => ['required'],
            'type'           => ['required', 'in:multiplier,flat'],
            'rateAdjustment' => ['required', 'numeric', 'min:0'],
            'daysOfWeek'     => ['required', 'array', 'min:1'],
        ]);

        $start = str_contains($this->startTime, ':') && strlen($this->startTime) === 5 ? $this->startTime . ':00' : $this->startTime;
        $end = str_contains($this->endTime, ':') && strlen($this->endTime) === 5 ? $this->endTime . ':00' : $this->endTime;

        $data = [
            'name'            => mb_trim($this->name),
            'start_time'      => $start,
            'end_time'        => $end,
            'type'            => $this->type,
            'rate_adjustment' => $this->rateAdjustment,
            'days_of_week'    => $this->daysOfWeek,
            'is_active'       => $this->isActive,
        ];

        if ($this->editingRuleId) {
            PricingRule::findOrFail($this->editingRuleId)->update($data);
        } else {
            PricingRule::create($data);
        }

        $this->closeModal();
    }

    public function toggleActive(int $ruleId): void
    {
        $rule = PricingRule::findOrFail($ruleId);
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function deleteRule(int $ruleId): void
    {
        PricingRule::findOrFail($ruleId)->delete();
    }

    public function with(): array
    {
        $rules = PricingRule::all();

        return [
            'rules' => $rules,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Dynamic Pricing Engine
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Define peak hour surcharges, weekend rates, and custom pricing multipliers applied in real-time on the booking matrix.
            </p>
        </div>
        <div>
            <button
                wire:click="openCreateModal"
                class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Pricing Rule</span>
            </button>
        </div>
    </div>

    <!-- RULES TABLE -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase font-black tracking-wider text-[10px]">
                    <tr>
                        <th class="p-4">Rule Name</th>
                        <th class="p-4">Time Window</th>
                        <th class="p-4">Applicable Days</th>
                        <th class="p-4">Rate Adjustment</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($rules as $rule)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <td class="p-4">
                                <div class="font-black text-sm text-slate-900 dark:text-white">{{ $rule->name }}</div>
                            </td>

                            <td class="p-4">
                                <span class="font-mono font-bold text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::createFromTimeString($rule->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::createFromTimeString($rule->end_time)->format('g:i A') }}
                                </span>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($rule->days_of_week ?? [] as $d)
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-medium uppercase">
                                            {{ substr($d, 0, 3) }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-4">
                                @if ($rule->type === 'multiplier')
                                    <span class="font-black text-sm text-amber-500">
                                        {{ $rule->rate_adjustment }}x Multiplier
                                        <span class="text-[10px] text-slate-400 font-normal">({{ ($rule->rate_adjustment - 1) * 100 > 0 ? '+' : '' }}{{ ($rule->rate_adjustment - 1) * 100 }}%)</span>
                                    </span>
                                @else
                                    <span class="font-black text-sm text-amber-500">
                                        +₱{{ number_format((float)$rule->rate_adjustment, 2) }} Flat
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                <button
                                    wire:click="toggleActive({{ $rule->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition cursor-pointer {{ $rule->is_active ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-400 border border-slate-300 dark:border-slate-700' }}"
                                >
                                    <span class="w-2 h-2 rounded-full {{ $rule->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    <span>{{ $rule->is_active ? 'Active' : 'Disabled' }}</span>
                                </button>
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        wire:click="openEditModal({{ $rule->id }})"
                                        class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        wire:click="deleteRule({{ $rule->id }})"
                                        wire:confirm="Are you sure you want to delete this pricing rule?"
                                        class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 font-semibold transition"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No pricing rules created. Click "Add Pricing Rule" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CREATE / EDIT RULE MODAL -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95">
                <button wire:click="closeModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    {{ $editingRuleId ? 'Edit Pricing Rule' : 'Create Pricing Rule' }}
                </h3>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Rule Name *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. Peak Evening Prime (+20%)"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        />
                        @error('name') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Start Time *</label>
                            <input
                                type="time"
                                wire:model="startTime"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500 text-center"
                            />
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">End Time *</label>
                            <input
                                type="time"
                                wire:model="endTime"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500 text-center"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Adjustment Type *</label>
                            <select
                                wire:model.live="type"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            >
                                <option value="multiplier">Multiplier (e.g. 1.20 = +20%)</option>
                                <option value="flat">Flat Addition (e.g. +₱50)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Value *</label>
                            <input
                                type="number"
                                step="0.05"
                                wire:model="rateAdjustment"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            />
                            @error('rateAdjustment') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-2">Days of Week Active *</label>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach (['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'] as $key => $label)
                                <label class="flex items-center gap-1.5 p-2 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 cursor-pointer">
                                    <input type="checkbox" wire:model="daysOfWeek" value="{{ $key }}" class="rounded border-slate-300 text-lime-500 focus:ring-lime-500">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('daysOfWeek') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="py-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-lime-500 focus:ring-lime-500">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Rule Active</span>
                        </label>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider"
                        >
                            Save Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
