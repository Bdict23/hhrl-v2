<?php

namespace App\Services\Event;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\EventMenu;
use App\Models\BanquetEvent\EventLiquidation;
use App\Models\DataManagement\Recipe;
use App\Models\Restaurant\RecipePackage;
use App\Models\Transaction\AdditionalFund;
use App\Models\Transaction\AdvancesForLiquidation;
use Illuminate\Validation\ValidationException;

class BanquetEventService
{
    protected $liquidation;
    protected $event;
    protected $branch;

    public function __construct(
        EventLiquidation $liquidation,
        Event $event,
        Branch $branch,
    ) {
        $this->liquidation = $liquidation;
        $this->event = $event;
        $this->branch = $branch;
    }

    public function createEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $venues = [];
            $services = [];
            $menu = [];

            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->event->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'BEO-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            $event = $this->event->create([
                'reference'         => $reference,
                'branch_id'         => $branchId,
                'event_name'        => $data['event_name'],
                'customer_id'        => $data['customer_id'],
                'event_address'     => $data['event_address'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'],
                'arrival_time'      => $data['arrival_time'],
                'departure_time'    => $data['departure_time'],
                'created_by'        => $data['prepared_by'],
                'guest_count'       => $data['guest_count'],
                'status'            => $data['status'] == 'FINAL' ? 'CONFIRMED' : 'PENDING',
                'notes'             => $data['note'],
                'total_amount'      => $data['venue_total_amount'] + $data['service_total_amount'] + $data['menu_total_amount'],
                'reviewer_id'       => $data['reviewer_id'],
                'approver_id'       => $data['approver_id'],
            ]);

            foreach ($data['venues'] as $item) {
                $venues[] = [
                    'venue_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'start_date'    => $data['start_date'],
                    'start_time'    => $data['arrival_time'],
                    'end_date'      => $data['end_date'],
                    'end_time'      => $data['departure_time'],
                ];
            }

            foreach ($data['services'] as $item) {
                $services[] = [
                    'service_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ];
            }

            foreach ($data['menu'] as $item) {
                $menu[] = [
                    'menu_id'       => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'note'          => $item['note'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ];
            }
            $event->venues()->createMany($venues);
            $event->services()->createMany($services);
            foreach ($menu as $index => $menuData) {
                $eventMenu = $event->menus()->create($menuData);
                $this->syncPackageRecipes($eventMenu, $data['menu'][$index]);
            }

            return $event;
        });
    }

    public function updateEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $event = $this->event->findOrFail($data['event_id']);

            $event->update([
                'event_name'        => $data['event_name'],
                'customer_id'       => $data['customer_id'],
                'event_address'     => $data['event_address'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'],
                'arrival_time'      => $data['arrival_time'],
                'departure_time'    => $data['departure_time'],
                'guest_count'       => $data['guest_count'],
                'status'            => $data['status'] == 'FINAL' ? 'CONFIRMED' : 'PENDING',
                'notes'             => $data['note'],
                'total_amount'      => $data['venue_total_amount'] + $data['service_total_amount'] + $data['menu_total_amount'],
                'reviewer_id'       => $data['reviewer_id'],
                'approver_id'       => $data['approver_id'],
            ]);

            $existingPackageIdsByMenu = [];
            $existingPackageSnapshotsByMenu = [];
            foreach ($event->menus()->with('packageRecipes')->get() as $existingMenu) {
                $snapshots = $existingMenu->packageRecipes->map(static fn (EventMenuPackage $package): array => [
                    'recipe_package_id' => $package->recipe_package_id,
                    'recipe_id' => $package->recipe_id,
                    'package_code' => $package->package_code,
                    'package_name' => $package->package_name,
                    'description' => $package->description,
                    'note' => $package->note,
                    'quantity' => $package->quantity,
                ])->all();
                $existingPackageIdsByMenu[$existingMenu->menu_id] = array_values(array_unique(array_merge(
                    $existingPackageIdsByMenu[$existingMenu->menu_id] ?? [],
                    array_column(array_filter($snapshots, static fn (array $package): bool => $package['recipe_package_id'] !== null), 'recipe_package_id')
                )));
                $existingPackageSnapshotsByMenu[$existingMenu->menu_id] = array_merge(
                    $existingPackageSnapshotsByMenu[$existingMenu->menu_id] ?? [],
                    $snapshots
                );
            }

            // Update related venues, services, and menus
            // For simplicity, we will delete existing ones and create new ones
            $event->venues()->delete();
            $event->services()->delete();
            $event->menus()->delete();

            foreach ($data['venues'] as $item) {
                $event->venues()->create([
                    'venue_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'start_date'    => $data['start_date'],
                    'start_time'    => $data['arrival_time'],
                    'end_date'      => $data['end_date'],
                    'end_time'      => $data['departure_time'],
                ]);
            }

            foreach ($data['services'] as $item) {
                $event->services()->create([
                    'service_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ]);
            }

            foreach ($data['menu'] as $item) {
                $eventMenu = $event->menus()->create([
                    'menu_id'       => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'note'          => $item['note'],
                ]);
                $this->syncPackageRecipes(
                    $eventMenu,
                    $item,
                    $existingPackageIdsByMenu[$item['id']] ?? [],
                    $existingPackageSnapshotsByMenu[$item['id']] ?? []
                );
            }

            return $event;
        });
    }

    public function confirmEvent(int $eventId): Event
    {
        $event = $this->event->findOrFail($eventId);
        $event->status = 'CONFIRMED';
        $event->save();

        return $event;
    }

    /**
     * Persist only package recipes that belong to the selected banquet recipe.
     *
     * @param array<string, mixed> $menuData
     */
    private function syncPackageRecipes(
        EventMenu $eventMenu,
        array $menuData,
        array $existingPackageIds = [],
        array $existingPackageSnapshots = []
    ): void {
        $selectedPackages = $menuData['package_recipes'] ?? [];
        if (!is_array($selectedPackages)) {
            throw ValidationException::withMessages([
                'selectedFoodRows' => 'The selected package recipes are invalid.',
            ]);
        }

        if ($selectedPackages === []) {
            return;
        }

        $parentRecipe = Recipe::query()->find($eventMenu->menu_id);
        if ($parentRecipe?->recipe_type !== 'Banquet') {
            throw ValidationException::withMessages([
                'selectedFoodRows' => 'Only banquet recipes can include package recipes.',
            ]);
        }

        $snapshotKey = static fn (?int $recipeId, ?string $code, ?string $name): string => $recipeId !== null
            ? 'recipe:'.$recipeId
            : 'details:'.sha1(($code ?? '').'|'.($name ?? ''));
        $existingSnapshotsByKey = [];
        foreach ($existingPackageSnapshots as $snapshot) {
            $key = $snapshotKey(
                isset($snapshot['recipe_id']) ? (int) $snapshot['recipe_id'] : null,
                $snapshot['package_code'] ?? null,
                $snapshot['package_name'] ?? null
            );
            $existingSnapshotsByKey[$key][] = $snapshot;
        }

        $packageIds = [];
        $staleSnapshots = [];
        foreach ($selectedPackages as $package) {
            if (
                !is_array($package)
                || (isset($package['note']) && !is_string($package['note']))
                || (isset($package['recipe_id']) && filter_var($package['recipe_id'], FILTER_VALIDATE_INT) === false)
                || (isset($package['code']) && !is_string($package['code']))
                || (isset($package['name']) && !is_string($package['name']))
            ) {
                throw ValidationException::withMessages([
                    'selectedFoodRows' => 'The selected package recipes are invalid.',
                ]);
            }

            $packageId = filter_var($package['recipe_package_id'] ?? null, FILTER_VALIDATE_INT);
            if ($packageId !== false && $packageId > 0) {
                $packageIds[] = $packageId;
                continue;
            }

            $recipeId = isset($package['recipe_id'])
                ? (int) $package['recipe_id']
                : null;
            $key = $snapshotKey($recipeId, $package['code'] ?? null, $package['name'] ?? null);
            if (empty($existingSnapshotsByKey[$key])) {
                throw ValidationException::withMessages([
                    'selectedFoodRows' => 'The selected package recipes are invalid.',
                ]);
            }

            $snapshot = array_shift($existingSnapshotsByKey[$key]);
            $snapshot['recipe_package_id'] = null;
            $snapshot['note'] = mb_substr($package['note'] ?? '', 0, 1000);
            $staleSnapshots[] = $snapshot;
        }

        if (count(array_unique($packageIds)) !== count($packageIds)) {
            throw ValidationException::withMessages([
                'selectedFoodRows' => 'A package recipe cannot be included more than once.',
            ]);
        }

        $packages = RecipePackage::query()
            ->with('recipe')
            ->where('package_id', $eventMenu->menu_id)
            ->whereIn('id', $packageIds)
            ->where(function ($query) use ($existingPackageIds): void {
                $query->whereHas('recipe', static fn ($recipeQuery) => $recipeQuery->where('status', 'AVAILABLE'))
                    ->when($existingPackageIds !== [], static fn ($query) => $query->orWhereIn('id', $existingPackageIds));
            })
            ->whereHas('recipe')
            ->orderBy('id')
            ->get();

        if ($packages->count() !== count($packageIds)) {
            throw ValidationException::withMessages([
                'selectedFoodRows' => 'One or more selected recipes are not part of this package.',
            ]);
        }

        $notesByPackageId = collect($selectedPackages)
            ->filter(static fn (array $package): bool => is_numeric($package['recipe_package_id'] ?? null))
            ->mapWithKeys(static fn (array $package): array => [
                (int) $package['recipe_package_id'] => mb_substr($package['note'] ?? '', 0, 1000),
            ]);

        $snapshots = $packages->map(static function (RecipePackage $package) use ($notesByPackageId): array {
            return [
                'recipe_package_id' => $package->id,
                'recipe_id' => $package->recipe_id,
                'package_code' => $package->recipe->menu_code,
                'package_name' => $package->recipe->menu_name,
                'description' => $package->recipe->menu_description,
                'note' => mb_substr($notesByPackageId[$package->id] ?? '', 0, 1000),
                'quantity' => $package->quantity,
            ];
        })->all();

        $eventMenu->packageRecipes()->createMany(array_merge($staleSnapshots, $snapshots));
    }

    public function rollbackEvent(int $eventId): Event
    {
        $event = $this->event->findOrFail($eventId);
        $event->status = 'PENDING';
        $event->save();

        return $event;
    }

    public static function getLiquidationData(int $id)
    {
        $data = EventLiquidation::with('cashReturn')->findOrFail($id);
        return $data;
    }
    public static function getEventAddedFunds(int $eventID)
    {
        $data = AdvancesForLiquidation::where('event_id', $eventID);
        return AdditionalFund::whereIn('advances_liquidation_id', $data->pluck('id'))->get();
    }
}
