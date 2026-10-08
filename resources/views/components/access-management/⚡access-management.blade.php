<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;

use App\Models\Business\Branch;
use App\Models\Business\Employee;
use App\Models\Settings\Module;
use App\Models\Settings\ModulePermission;
use App\Models\Settings\Signatory;
use App\Models\Settings\AssignedBranch;

new class extends Component
{
    use Interactions;

    // Employee selector
    public ?int $selectedEmployee = null;
    public string $lastName       = '';
    public string $email          = '';
    public ?int $position         = null;
    public string $employeeStatus = '';

    // Tab state
    public string $activeTab = 'PERMISSIONS';

    // Module search (permissions tab only)
    public string $moduleSearch = '';

    // Pending changes (keyed by module_id / branch_id)
    public array $permissions  = [];
    public array $signatories  = [];
    public array $branchAccess = [];

    // Lifecycle

    public function mount(): void
    {
        // data is loaded reactively on employee selection
    }

    // Watchers

    public function updatedSelectedEmployee(?int $value): void
    {
        $this->resetState();

        if (! $value) {
            return;
        }

        $employee = Employee::with(['position', 'user'])->find($value);

        if (! $employee) {
            return;
        }

        $this->lastName       = $employee->last_name ?? '';
        $this->email          = $employee->user?->email ?? '';
        $this->position       = $employee->position_id;
        $this->employeeStatus = $employee->status ?? '';

        $this->loadPermissions($value);
        $this->loadSignatories($value);
        $this->loadBranchAccess($value);
    }

    // Data loaders

    private function resetState(): void
    {
        $this->lastName       = '';
        $this->email          = '';
        $this->position       = null;
        $this->employeeStatus = '';
        $this->moduleSearch   = '';
        $this->permissions    = [];
        $this->signatories    = [];
        $this->branchAccess   = [];
    }

    private function loadPermissions(int $employeeId): void
    {
        $this->permissions = [];

        // 3 logical states using single `access` column:
        //   no record  → 'restrict'   (no access at all)
        //   access = 0 → 'readonly'
        //   access = 1 → 'full_access'
        Module::where('status', 'ACTIVE')->each(function (Module $module) use ($employeeId) {
            $perm = ModulePermission::where('employee_id', $employeeId)
                ->where('module_id', $module->id)
                ->first();

            if (! $perm) {
                $this->permissions[$module->id] = 'restrict';
            } elseif ((int) $perm->access === 1) {
                $this->permissions[$module->id] = 'full_access';
            } else {
                $this->permissions[$module->id] = 'readonly';
            }
        });
    }

    private function loadSignatories(int $employeeId): void
    {
        $this->signatories = [];

        $branches             = Branch::all();
        $modulesWithSignatory = Module::where('has_signatory', true)->get();

        foreach ($branches as $branch) {
            foreach ($modulesWithSignatory as $module) {
                $signatory = Signatory::where('employee_id', $employeeId)
                    ->where('module_id', $module->id)
                    ->where('branch_id', $branch->id)
                    ->first();

                $this->signatories[$branch->id][$module->id] = [
                    'REVIEWER' => $signatory?->signatory_type === 'REVIEWER',
                    'APPROVER' => $signatory?->signatory_type === 'APPROVER',
                ];
            }
        }
    }

    private function loadBranchAccess(int $employeeId): void
    {
        $this->branchAccess = [];

        Branch::where('branch_status', 'ACTIVE')->each(function (Branch $branch) use ($employeeId) {
            $this->branchAccess[$branch->id] = AssignedBranch::where('employee_id', $employeeId)
                ->where('branch_id', $branch->id)
                ->exists();
        });
    }

    // Interactive updates (real-time, no save yet)

    public function setPermission(int $moduleId, string $level): void
    {
        // Three states: restrict (no record), readonly (access=0), full_access (access=1)
        if (! in_array($level, ['restrict', 'readonly', 'full_access'], true)) {
            return;
        }
        $this->permissions[$moduleId] = $level;
    }

    public function setGroupPermissions(string $groupName, string $level): void
    {
        if (! in_array($level, ['restrict', 'readonly', 'full_access'], true)) {
            return;
        }

        $modules = Module::query()
            ->when($groupName === 'General', fn ($q) => $q->whereNull('group_name')->orWhere('group_name', ''))
            ->when($groupName !== 'General', fn ($q) => $q->where('group_name', $groupName))
            ->where('status', 'ACTIVE')
            ->pluck('id');

        foreach ($modules as $id) {
            $this->permissions[$id] = $level;
        }
    }

    public function setAllPermissions(string $level): void
    {
        if (! in_array($level, ['restrict', 'readonly', 'full_access'], true)) {
            return;
        }

        $modules = Module::pluck('id');
        foreach ($modules as $id) {
            $this->permissions[$id] = $level;
        }
    }

    public function getGroupIcon(string $group): string
    {
        return match (strtolower(trim($group))) {
            'account', 'accounting'                              => 'banknotes',
            'banquet', 'events'                                  => 'calendar-days',
            'business'                                           => 'briefcase',
            'data import'                                        => 'arrow-down-tray',
            'entrance'                                           => 'key',
            'inventory'                                          => 'cube',
            'item management', 'item properties'                 => 'tag',
            'master data'                                        => 'circle-stack',
            'pickle court'                                       => 'trophy',
            'price levels'                                       => 'currency-dollar',
            'restaurant', 'restaurants', 'restaurant management' => 'building-storefront',
            'rooms & suites'                                     => 'home',
            'transaction', 'transacton'                          => 'receipt-percent',
            'validations'                                        => 'check-badge',
            default                                              => 'squares-2x2',
        };
    }

    public function setSignatoryRole(int $branchId, int $moduleId, string $role, bool $checked): void
    {
        if (! in_array($role, ['REVIEWER', 'APPROVER'], true)) {
            return;
        }

        if ($checked) {
            $opposite = $role === 'REVIEWER' ? 'APPROVER' : 'REVIEWER';
            $this->signatories[$branchId][$moduleId][$role]     = true;
            $this->signatories[$branchId][$moduleId][$opposite] = false;
        } else {
            $this->signatories[$branchId][$moduleId][$role] = false;
        }
    }

    public function toggleBranchAccess(int $branchId, bool $allowed): void
    {
        $this->branchAccess[$branchId] = $allowed;
    }

    // Persist

    public function saveChanges(): void
    {
        if (! $this->selectedEmployee) {
            $this->toast()->warning('No Employee Selected', 'Please select an employee before saving.')->send();
            return;
        }

        $this->savePermissionsToDb();
        $this->saveSignatoriesToDb();
        $this->saveBranchAccessToDb();

        $this->toast()->success('Saved', 'Access settings have been updated successfully.')->send();
    }

    private function savePermissionsToDb(): void
    {
        // 'restrict' = delete the record entirely (no access)
        // 'readonly' = upsert with access = 0
        // 'full_access' = upsert with access = 1
        foreach ($this->permissions as $moduleId => $level) {
            if ($level === 'restrict') {
                ModulePermission::where('employee_id', $this->selectedEmployee)
                    ->where('module_id', $moduleId)
                    ->delete();
            } else {
                ModulePermission::updateOrCreate(
                    [
                        'employee_id' => $this->selectedEmployee,
                        'module_id'   => $moduleId,
                    ],
                    [
                        'access' => $level === 'full_access' ? 1 : 0,
                    ]
                );
            }
        }
    }

    private function saveSignatoriesToDb(): void
    {
        foreach ($this->signatories as $branchId => $modules) {
            foreach ($modules as $moduleId => $roles) {
                // Delete existing signatory for this employee + branch + module combination
                Signatory::where('employee_id', $this->selectedEmployee)
                    ->where('branch_id', $branchId)
                    ->where('module_id', $moduleId)
                    ->delete();

                $activeRole = null;
                if ($roles['REVIEWER'] ?? false) {
                    $activeRole = 'REVIEWER';
                } elseif ($roles['APPROVER'] ?? false) {
                    $activeRole = 'APPROVER';
                }

                if ($activeRole) {
                    // signatories table: no signatory_name column; has status column
                    Signatory::create([
                        'employee_id'    => $this->selectedEmployee,
                        'signatory_type' => $activeRole,
                        'module_id'      => $moduleId,
                        'branch_id'      => $branchId,
                        'company_id'     => auth()->user()->branch?->company_id,
                        'status'         => 'ACTIVE',
                    ]);
                }
            }
        }
    }

    private function saveBranchAccessToDb(): void
    {
        foreach ($this->branchAccess as $branchId => $allowed) {
            if ($allowed) {
                AssignedBranch::firstOrCreate([
                    'employee_id' => $this->selectedEmployee,
                    'branch_id'   => $branchId,
                ]);
            } else {
                AssignedBranch::where('employee_id', $this->selectedEmployee)
                    ->where('branch_id', $branchId)
                    ->delete();
            }
        }
    }

    // View data

    public function with(): array
    {
        $modules              = Module::orderBy('group_name')->orderBy('module_name')->where('status', 'ACTIVE')->get();
        $branches             = Branch::where('branch_status', 'ACTIVE')->get();
        $modulesWithSignatory = Module::where('has_signatory', true)->get();

        $permissionRows = $modules->map(function (Module $module) {
            // Default to 'restrict' when no employee selected or no record
            $level = $this->permissions[$module->id] ?? 'restrict';

            return [
                'id'          => $module->id,
                'module'      => $module->module_name,
                'group'       => $module->group_name ?: 'General',
                'restrict'    => $level === 'restrict',
                'readonly'    => $level === 'readonly',
                'full_access' => $level === 'full_access',
            ];
        });

        // Summary counts for the permissions tab badges
        $permissionCounts = [
            'restrict'    => $permissionRows->where('restrict', true)->count(),
            'readonly'    => $permissionRows->where('readonly', true)->count(),
            'full_access' => $permissionRows->where('full_access', true)->count(),
            'total'       => $permissionRows->count(),
        ];

        // Group permissions by group_name
        $groupedPermissions = $permissionRows
            ->groupBy('group')
            ->map(function ($groupRows, $groupName) {
                return [
                    'group_name'  => $groupName,
                    'icon'        => $this->getGroupIcon($groupName),
                    'total'       => $groupRows->count(),
                    'restrict'    => $groupRows->where('restrict', true)->count(),
                    'readonly'    => $groupRows->where('readonly', true)->count(),
                    'full_access' => $groupRows->where('full_access', true)->count(),
                    'modules'     => $groupRows->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        $signatoryRows = $branches->map(function (Branch $branch) use ($modulesWithSignatory) {
            $moduleRows = $modulesWithSignatory->map(function (Module $module) use ($branch) {
                $roles = $this->signatories[$branch->id][$module->id] ?? ['REVIEWER' => false, 'APPROVER' => false];
                return [
                    'branch_id'  => $branch->id,
                    'module_id'  => $module->id,
                    'module'     => $module->module_name,
                    'reviewer'   => $roles['REVIEWER'],
                    'approver'   => $roles['APPROVER'],
                ];
            });

            return [
                'branch_id'   => $branch->id,
                'branch_name' => $branch->branch_name,
                'modules'     => $moduleRows->toArray(),
            ];
        });

        $branchAccessRows = $branches->map(function (Branch $branch) {
            return [
                'id'          => $branch->id,
                'branch_name' => $branch->branch_name,
                'allowed'     => $this->branchAccess[$branch->id] ?? false,
            ];
        });

        return compact(
            'modules',
            'branches',
            'modulesWithSignatory',
            'permissionRows',
            'permissionCounts',
            'groupedPermissions',
            'signatoryRows',
            'branchAccessRows'
        );
    }
};
?>

<div class="space-y-5">

    {{-- HEADER --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-base font-bold text-gray-800 dark:text-gray-100 tracking-tight">
                Access Management
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Manage employee module permissions, signatories, and branch access.
            </p>
        </div>

        <button
            wire:click="saveChanges"
            wire:loading.attr="disabled"
            wire:target="saveChanges"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl
                   bg-lime-500 hover:bg-lime-400 active:bg-lime-600
                   text-slate-950 text-xs font-black uppercase tracking-widest
                   transition-all shadow-lg shadow-lime-500/25
                   disabled:opacity-60 disabled:cursor-not-allowed"
        >
            <svg wire:loading.remove wire:target="saveChanges" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <svg wire:loading wire:target="saveChanges" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            <span wire:loading.remove wire:target="saveChanges">Save Changes</span>
            <span wire:loading wire:target="saveChanges">Saving…</span>
        </button>
    </div>

    {{-- EMPLOYEE CARD --}}
    <x-ts-card>
        <x-slot:header>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <x-ts-icon name="user-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                </div>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Employee Information</span>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <x-ts-select.styled
                :request="route('api.employee.all', ['branch_id' => auth()->user()->branch_id])"
                label="Employee"
                wire:model.live="selectedEmployee"
                select="label:label|value:id|description:description"
                placeholder="Search employee…"
            />
            <x-ts-input label="Last Name" wire:model="lastName" readonly placeholder="—" />
            <x-ts-input label="Email Address" wire:model="email" readonly placeholder="—" />
            <x-ts-select.styled
                :request="route('api.employee.positions', ['company_id' => auth()->user()->branch?->company_id])"
                label="Position"
                wire:model.live="position"
                select="label:label|value:id|description:description"
                placeholder="Select position…"
            />
            <x-ts-select.native
                label="Status"
                wire:model.live="employeeStatus"
                placeholder="Select status…"
                :options="[
                    ['label' => 'ACTIVE',   'value' => 'ACTIVE'],
                    ['label' => 'INACTIVE', 'value' => 'INACTIVE'],
                ]"
            />
        </div>
    </x-ts-card>

    @if ($selectedEmployee)
        <x-ts-tab wire:model.live="activeTab" scroll-on-mobile paddingless>

            {{-- TAB 1: Module Permissions --}}
            <x-ts-tab.items tab="PERMISSIONS" title="Module Permissions">
                <div class="p-4 space-y-4"
                     x-data="{
                         search: '',
                         openGroups: {},
                         groupsData: {{ json_encode(array_map(fn($g) => ['name' => $g['group_name'], 'modules' => array_column($g['modules'], 'module')], $groupedPermissions)) }},
                         init() {
                             this.groupsData.forEach(g => {
                                 this.openGroups[g.name] = true;
                             });
                         },
                         toggle(name) {
                             this.openGroups[name] = !this.openGroups[name];
                         },
                         isOpen(name) {
                             if (this.search.trim() !== '') return true;
                             return !!this.openGroups[name];
                         },
                         openAll() {
                             this.groupsData.forEach(g => { this.openGroups[g.name] = true; });
                         },
                         closeAll() {
                             this.openGroups = {};
                         },
                         groupMatches(name) {
                             if (!this.search.trim()) return true;
                             const q = this.search.toLowerCase();
                             const g = this.groupsData.find(item => item.name === name);
                             return g ? g.modules.some(m => m.toLowerCase().includes(q)) : false;
                         },
                         hasAnyMatch() {
                             if (!this.search.trim()) return true;
                             const q = this.search.toLowerCase();
                             return this.groupsData.some(g => g.modules.some(m => m.toLowerCase().includes(q)));
                         }
                     }">

                    {{-- ── Count badges ─────────────────────────────── --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        {{-- Total --}}
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-gray-700/60
                                    bg-white dark:bg-gray-800/40 px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <x-ts-icon name="squares-2x2" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="text-lg font-bold text-gray-800 dark:text-gray-100 leading-none">
                                    {{ $permissionCounts['total'] }}
                                </p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mt-0.5">
                                    Total Modules
                                </p>
                            </div>
                        </div>
                        {{-- Restrict --}}
                        <div class="flex items-center gap-3 rounded-xl border border-red-100 dark:border-red-800/30
                                    bg-red-50/60 dark:bg-red-900/10 px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-red-600 dark:text-red-400 leading-none">
                                    {{ $permissionCounts['restrict'] }}
                                </p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-red-400 mt-0.5">
                                    Restrict
                                </p>
                            </div>
                        </div>
                        {{-- Read Only --}}
                        <div class="flex items-center gap-3 rounded-xl border border-amber-100 dark:border-amber-800/30
                                    bg-amber-50/60 dark:bg-amber-900/10 px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-amber-600 dark:text-amber-400 leading-none">
                                    {{ $permissionCounts['readonly'] }}
                                </p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-400 mt-0.5">
                                    Read Only
                                </p>
                            </div>
                        </div>
                        {{-- Full Access --}}
                        <div class="flex items-center gap-3 rounded-xl border border-lime-100 dark:border-lime-800/30
                                    bg-lime-50/60 dark:bg-lime-900/10 px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-lime-100 dark:bg-lime-900/40 flex items-center justify-center">
                                <svg class="w-4 h-4 text-lime-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-lime-600 dark:text-lime-400 leading-none">
                                    {{ $permissionCounts['full_access'] }}
                                </p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-lime-500 mt-0.5">
                                    Full Access
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- ── Toolbar: Search & Group Controls ──────────── --}}
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        {{-- Search Input --}}
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Search modules across groups…"
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700
                                       bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300
                                       pl-10 pr-9 py-2.5 placeholder-gray-400
                                       focus:outline-none focus:ring-2 focus:ring-lime-500/50 focus:border-lime-500
                                       transition shadow-2xs"
                            />
                            <div x-show="search" x-cloak class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <button
                                    type="button"
                                    @click="search = ''"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- Expand All --}}
                            <button
                                type="button"
                                @click="openAll()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold
                                       border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                                       text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60
                                       transition shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" />
                                </svg>
                                <span>Expand All</span>
                            </button>

                            {{-- Collapse All --}}
                            <button
                                type="button"
                                @click="closeAll()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold
                                       border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                                       text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60
                                       transition shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
                                </svg>
                                <span>Collapse All</span>
                            </button>

                            {{-- Global Quick Set All --}}
                            <x-ts-dropdown text="Quick Actions" sm position="bottom-end">
                                <x-ts-dropdown.items
                                    text="Grant All (Full Access)"
                                    icon="check-circle"
                                    wire:click="setAllPermissions('full_access')"
                                />
                                <x-ts-dropdown.items
                                    text="Set All (Read Only)"
                                    icon="eye"
                                    wire:click="setAllPermissions('readonly')"
                                />
                                <x-ts-dropdown.items
                                    text="Reset All (Restrict)"
                                    icon="no-symbol"
                                    wire:click="setAllPermissions('restrict')"
                                    separator
                                />
                            </x-ts-dropdown>
                        </div>
                    </div>

                    {{-- ── Grouped Collapsible Modules ───────────────── --}}
                    <div class="space-y-3">
                        @forelse ($groupedPermissions as $group)
                            <div
                                x-show="groupMatches(@js($group['group_name']))"
                                class="rounded-xl border border-gray-200/90 dark:border-gray-700/70
                                       bg-white dark:bg-gray-800/60 overflow-hidden shadow-xs transition-all"
                            >
                                {{-- Group Header --}}
                                <div
                                    @click="toggle(@js($group['group_name']))"
                                    class="flex items-center justify-between gap-3 px-4 py-3
                                           bg-gray-50/70 dark:bg-gray-800/80 hover:bg-gray-100/70 dark:hover:bg-gray-700/50
                                           cursor-pointer select-none transition-colors"
                                >
                                    {{-- Left: Icon + Title + Count --}}
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-slate-900 to-slate-700 text-lime-400 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center shrink-0 shadow-2xs">
                                            <x-ts-icon :name="$group['icon']" class="w-4 h-4 text-lime-400" />
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">
                                                {{ $group['group_name'] }}
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200/80 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                {{ $group['total'] }} {{ Str::plural('module', $group['total']) }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Right: Status pill + Batch actions + Chevron --}}
                                    <div class="flex items-center gap-3 shrink-0">
                                        {{-- Group Status Summary Pill --}}
                                        <div class="hidden md:flex items-center gap-1.5 text-xs">
                                            @if ($group['full_access'] === $group['total'])
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-lime-100 dark:bg-lime-900/30 text-lime-700 dark:text-lime-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-lime-500"></span> All Full Access
                                                </span>
                                            @elseif ($group['restrict'] === $group['total'])
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> All Restricted
                                                </span>
                                            @elseif ($group['readonly'] === $group['total'])
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> All Read Only
                                                </span>
                                            @else
                                                <div class="flex items-center gap-2 bg-white dark:bg-gray-900/50 px-2.5 py-1 rounded-lg border border-gray-100 dark:border-gray-700/60 text-[11px]">
                                                    @if ($group['full_access'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-lime-600 dark:text-lime-400 font-semibold">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-lime-500"></span>{{ $group['full_access'] }} Full
                                                        </span>
                                                    @endif
                                                    @if ($group['readonly'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-semibold">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ $group['readonly'] }} Read
                                                        </span>
                                                    @endif
                                                    @if ($group['restrict'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-red-500 dark:text-red-400 font-semibold">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>{{ $group['restrict'] }} Restrict
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Group Quick Batch Controls --}}
                                        <div class="hidden sm:flex items-center gap-1 bg-white dark:bg-gray-900/50 p-1 rounded-lg border border-gray-100 dark:border-gray-700/60 shadow-2xs" @click.stop>
                                            <button
                                                type="button"
                                                wire:click="setGroupPermissions(@js($group['group_name']), 'restrict')"
                                                title="Set all in {{ $group['group_name'] }} to Restrict"
                                                class="px-2 py-0.5 text-[10px] font-bold uppercase rounded hover:bg-red-50 dark:hover:bg-red-950/40 text-red-600 dark:text-red-400 transition"
                                            >
                                                Restrict
                                            </button>
                                            <span class="text-gray-300 dark:text-gray-600 text-xs">|</span>
                                            <button
                                                type="button"
                                                wire:click="setGroupPermissions(@js($group['group_name']), 'readonly')"
                                                title="Set all in {{ $group['group_name'] }} to Read Only"
                                                class="px-2 py-0.5 text-[10px] font-bold uppercase rounded hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-600 dark:text-amber-400 transition"
                                            >
                                                Read
                                            </button>
                                            <span class="text-gray-300 dark:text-gray-600 text-xs">|</span>
                                            <button
                                                type="button"
                                                wire:click="setGroupPermissions(@js($group['group_name']), 'full_access')"
                                                title="Set all in {{ $group['group_name'] }} to Full Access"
                                                class="px-2 py-0.5 text-[10px] font-bold uppercase rounded hover:bg-lime-50 dark:hover:bg-lime-950/40 text-lime-600 dark:text-lime-400 transition"
                                            >
                                                Full
                                            </button>
                                        </div>

                                        {{-- Expand Chevron --}}
                                        <div
                                            class="w-6 h-6 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-transform duration-200"
                                            :class="isOpen(@js($group['group_name'])) ? 'rotate-180' : ''"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Group Body (Expandable) --}}
                                <div x-show="isOpen(@js($group['group_name']))" x-collapse x-cloak>
                                    <div class="border-t border-gray-100 dark:border-gray-700/60">
                                        {{-- Desktop Table Header --}}
                                        <div class="hidden lg:grid grid-cols-[1fr_repeat(3,7rem)] gap-x-4 px-4 py-2
                                                    bg-gray-50/50 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-700/40
                                                    text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                            <span>Module Name</span>
                                            <span class="text-center">Restrict</span>
                                            <span class="text-center">Read Only</span>
                                            <span class="text-center">Full Access</span>
                                        </div>

                                        {{-- Modules List --}}
                                        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach ($group['modules'] as $row)
                                                <div
                                                    x-show="search === '' || '{{ strtolower(addslashes($row['module'])) }}'.includes(search.toLowerCase())"
                                                    class="grid grid-cols-1 lg:grid-cols-[1fr_repeat(3,7rem)] gap-x-4
                                                           items-center px-4 py-2.5
                                                           hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-colors"
                                                >
                                                    {{-- Module Name --}}
                                                    <div class="flex items-center gap-2.5 mb-2 lg:mb-0">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-500 shrink-0"></span>
                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            {{ $row['module'] }}
                                                        </span>
                                                    </div>

                                                    {{-- Permission Dot Toggles --}}
                                                    <div class="lg:contents grid grid-cols-3 gap-2">
                                                        @foreach ([
                                                            ['key' => 'restrict',    'color_on' => 'bg-red-500 ring-2 ring-red-300',   'color_hover' => 'hover:bg-red-100 dark:hover:bg-red-900/30'],
                                                            ['key' => 'readonly',    'color_on' => 'bg-amber-500 ring-2 ring-amber-300', 'color_hover' => 'hover:bg-amber-100 dark:hover:bg-amber-900/30'],
                                                            ['key' => 'full_access', 'color_on' => 'bg-lime-500 ring-2 ring-lime-300',  'color_hover' => 'hover:bg-lime-100 dark:hover:bg-lime-900/30'],
                                                        ] as $opt)
                                                            <div class="flex flex-col items-center gap-1">
                                                                <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 lg:hidden">
                                                                    {{ str_replace('_', ' ', $opt['key']) }}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    wire:click="setPermission({{ $row['id'] }}, '{{ $opt['key'] }}')"
                                                                    title="{{ ucfirst(str_replace('_', ' ', $opt['key'])) }}"
                                                                    class="w-full lg:w-auto flex justify-center cursor-pointer"
                                                                >
                                                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full transition-all
                                                                        {{ $row[$opt['key']]
                                                                            ? $opt['color_on'] . ' shadow-md'
                                                                            : 'bg-gray-200 dark:bg-gray-700 ' . $opt['color_hover'] }}">
                                                                        @if ($row[$opt['key']])
                                                                            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414L7.414 16l-4.121-4.121a1 1 0 011.414-1.414L7.414 13.172l7.879-7.879a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                            </svg>
                                                                        @endif
                                                                    </span>
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-sm text-gray-400">No modules found.</div>
                        @endforelse

                        {{-- No search results empty state --}}
                        <div
                            x-show="!hasAnyMatch()"
                            x-cloak
                            class="py-12 text-center text-sm text-gray-400 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8"
                        >
                            <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
                            </svg>
                            <p>No modules match "<span x-text="search" class="font-medium text-gray-600 dark:text-gray-300"></span>"</p>
                        </div>
                    </div>

                    {{-- ── Legend ────────────────────────────────────── --}}
                    <div class="flex flex-wrap items-center gap-4 px-1 pt-1">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> Restrict
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span> Read Only
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-3 h-3 rounded-full bg-lime-500 inline-block"></span> Full Access
                        </div>
                    </div>
                </div>
            </x-ts-tab.items>

            {{-- TAB 2: Signatories --}}
            <x-ts-tab.items tab="SIGNATORIES" title="Signatories">
                <div class="p-4 space-y-4">
                    @forelse ($signatoryRows as $branchRow)
                        <div class="rounded-xl border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                            <div class="flex items-center gap-3 px-4 py-3
                                        bg-gradient-to-r from-slate-700 to-slate-800
                                        dark:from-slate-800 dark:to-slate-900">
                                <div class="w-7 h-7 rounded-md bg-white/10 flex items-center justify-center">
                                    <x-ts-icon name="building-office-2" class="w-4 h-4 text-white/80" />
                                </div>
                                <span class="text-sm font-semibold text-white tracking-wide">
                                    {{ $branchRow['branch_name'] }}
                                </span>
                            </div>

                            <div class="divide-y divide-gray-100 dark:divide-gray-700/40">
                                <div class="grid grid-cols-[1fr_repeat(2,8rem)] gap-x-4 px-4 py-2
                                            bg-gray-50 dark:bg-gray-800/50
                                            text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                    <span>Module</span>
                                    <span class="text-center">Reviewer</span>
                                    <span class="text-center">Approver</span>
                                </div>

                                @foreach ($branchRow['modules'] as $moduleRow)
                                    <div class="grid grid-cols-[1fr_repeat(2,8rem)] gap-x-4
                                                items-center px-4 py-3
                                                odd:bg-white even:bg-gray-50/40
                                                dark:odd:bg-gray-800/30 dark:even:bg-gray-800/10
                                                hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-colors">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $moduleRow['module'] }}
                                        </span>

                                        <div class="flex justify-center">
                                            <button
                                                wire:click="setSignatoryRole({{ $moduleRow['branch_id'] }}, {{ $moduleRow['module_id'] }}, 'REVIEWER', {{ $moduleRow['reviewer'] ? 'false' : 'true' }})"
                                                title="{{ $moduleRow['reviewer'] ? 'Remove Reviewer' : 'Set as Reviewer' }}"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full transition-all
                                                    {{ $moduleRow['reviewer']
                                                        ? 'bg-blue-500 ring-2 ring-blue-300 shadow-sm'
                                                        : 'bg-gray-200 dark:bg-gray-700 hover:bg-blue-100' }}">
                                                    @if ($moduleRow['reviewer'])
                                                        <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414L7.414 16l-4.121-4.121a1 1 0 011.414-1.414L7.414 13.172l7.879-7.879a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </span>
                                            </button>
                                        </div>

                                        <div class="flex justify-center">
                                            <button
                                                wire:click="setSignatoryRole({{ $moduleRow['branch_id'] }}, {{ $moduleRow['module_id'] }}, 'APPROVER', {{ $moduleRow['approver'] ? 'false' : 'true' }})"
                                                title="{{ $moduleRow['approver'] ? 'Remove Approver' : 'Set as Approver' }}"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full transition-all
                                                    {{ $moduleRow['approver']
                                                        ? 'bg-violet-500 ring-2 ring-violet-300 shadow-sm'
                                                        : 'bg-gray-200 dark:bg-gray-700 hover:bg-violet-100' }}">
                                                    @if ($moduleRow['approver'])
                                                        <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414L7.414 16l-4.121-4.121a1 1 0 011.414-1.414L7.414 13.172l7.879-7.879a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-sm text-gray-400">No active branches found.</div>
                    @endforelse

                    <div class="flex flex-wrap items-center gap-4 px-1 pt-1">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Reviewer
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-3 h-3 rounded-full bg-violet-500 inline-block"></span> Approver
                        </div>
                        <span class="text-[10px] text-gray-400 italic">Reviewer and Approver are mutually exclusive per module.</span>
                    </div>
                </div>
            </x-ts-tab.items>

            {{-- TAB 3: Branch Access --}}
            <x-ts-tab.items tab="BRANCH ACCESS" title="Branch Access">
                <div class="p-4 space-y-3">
                    <div class="hidden sm:grid grid-cols-[1fr_6rem] gap-x-4 px-4 pb-1
                                text-[10px] font-bold uppercase tracking-widest text-gray-400">
                        <span>Branch</span>
                        <span class="text-center">Allowed</span>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700/60
                                rounded-xl border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                        @forelse ($branchAccessRows as $row)
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_6rem] gap-x-4
                                        items-center px-4 py-3
                                        odd:bg-white even:bg-gray-50/60
                                        dark:odd:bg-gray-800/40 dark:even:bg-gray-800/20
                                        hover:bg-green-50/40 dark:hover:bg-green-900/10 transition-colors">
                                <div class="flex items-center gap-3 mb-3 sm:mb-0">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600
                                                flex items-center justify-center shrink-0">
                                        <x-ts-icon name="building-storefront" class="w-4 h-4 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $row['branch_name'] }}
                                        </p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">
                                            {{ $row['allowed'] ? 'Access Granted' : 'No Access' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex sm:justify-center">
                                    <button
                                        wire:click="toggleBranchAccess({{ $row['id'] }}, {{ $row['allowed'] ? 'false' : 'true' }})"
                                        title="{{ $row['allowed'] ? 'Revoke access' : 'Grant access' }}"
                                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full
                                               transition-colors duration-200
                                               {{ $row['allowed'] ? 'bg-lime-500 hover:bg-lime-400' : 'bg-gray-300 dark:bg-gray-600 hover:bg-gray-400' }}"
                                        role="switch"
                                        aria-checked="{{ $row['allowed'] ? 'true' : 'false' }}"
                                    >
                                        <span class="sr-only">{{ $row['allowed'] ? 'Allowed' : 'Not Allowed' }}</span>
                                        <span class="inline-block h-4 w-4 rounded-full bg-white shadow
                                                     transition-transform duration-200
                                                     {{ $row['allowed'] ? 'translate-x-6' : 'translate-x-1' }}">
                                        </span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-sm text-gray-400">No active branches found.</div>
                        @endforelse
                    </div>

                    @php
                        $allowedCount = collect($branchAccessRows)->where('allowed', true)->count();
                        $totalCount   = collect($branchAccessRows)->count();
                    @endphp
                    <div class="flex items-center gap-2 px-1 pt-1">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full
                                     {{ $allowedCount > 0 ? 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ $allowedCount }} / {{ $totalCount }} branches accessible
                        </span>
                    </div>
                </div>
            </x-ts-tab.items>

        </x-ts-tab>
    @else
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700
                    flex flex-col items-center justify-center gap-4 py-16 px-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                <x-ts-icon name="shield-check" class="w-8 h-8 text-gray-300 dark:text-gray-600" />
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No employee selected</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                    Select an employee above to manage their access settings.
                </p>
            </div>
        </div>
    @endif

    <x-ts-back-to-top lg />
</div>
