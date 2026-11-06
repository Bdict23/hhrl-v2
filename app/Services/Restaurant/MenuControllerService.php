<?php

namespace App\Services\Restaurant;

use App\Models\Business\Branch;
use App\Models\Business\RecipeController;
use App\Models\DataManagement\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuControllerService
{
    private const DAYS = [
        'mon' => 'mon',
        'tue' => 'tue',
        'wed' => 'wed',
        'thu' => 'thu',
        'fri' => 'fri',
        'sat' => 'sat',
        'sun' => 'sun',
    ];

    /**
     * @param array{
     *     branch_id: int,
     *     controlName: string,
     *     effectiveDate: string,
     *     endDate: ?string,
     *     status: string,
     *     selectedDays: array<int, string>,
     *     menuItems: array<int, int|string>,
     *     created_by: ?int,
     *     notes?: string|null
     * } $data
     */
    public function create(array $data): RecipeController
    {
        return DB::transaction(function () use ($data): RecipeController {
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $menuIds = $this->validatedMenuIds($data['menuItems'], $branch->company_id);

            $controller = RecipeController::query()->create([
                ...$this->scheduleAttributes($data),
                'branch_id' => $branch->id,
                'created_by' => $data['created_by'],
            ]);

            $this->replaceRecipes($controller, $menuIds);

            return $controller;
        });
    }

    /**
     * @param array{
     *     id: int,
     *     branch_id: int,
     *     controlName: string,
     *     effectiveDate: string,
     *     endDate: ?string,
     *     status: string,
     *     selectedDays: array<int, string>,
     *     menuItems: array<int, int|string>,
     *     notes?: string|null
     * } $data
     */
    public function update(array $data): RecipeController
    {
        return DB::transaction(function () use ($data): RecipeController {
            $controller = RecipeController::query()
                ->where('branch_id', $data['branch_id'])
                ->findOrFail($data['id']);
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $menuIds = $this->validatedMenuIds($data['menuItems'], $branch->company_id);

            $controller->update($this->scheduleAttributes($data));
            $this->replaceRecipes($controller, $menuIds);

            return $controller;
        });
    }

    public function findForBranch(int $id, int $branchId): RecipeController
    {
        return RecipeController::query()
            ->with([
                'preparedBy',
                'Branchrecipes.recipe.category',
                'Branchrecipes.recipe.rate',
            ])
            ->where('branch_id', $branchId)
            ->findOrFail($id);
    }

    public function setAvailability(int $id, int $branchId, bool $isAvailable): RecipeController
    {
        $controller = RecipeController::query()
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        $controller->update(['is_available' => $isAvailable]);

        return $controller;
    }

    public function removeSchedule(int $id, int $branchId): void
    {
        $controller = RecipeController::query()
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        $controller->delete();
    }

    /**
     * @param array{
     *     controlName: string,
     *     status: string,
     *     effectiveDate: string,
     *     endDate: ?string,
     *     selectedDays: array<int, string>,
     *     notes?: string|null
     * } $data
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $data): array
    {
        $attributes = [
            'control_name' => $data['controlName'],
            'is_available' => $data['status'] === 'active',
            'start_date' => $data['effectiveDate'],
            'end_date' => $data['endDate'],
            'notes' => $data['notes'] ?? null,
        ];

        foreach (self::DAYS as $day) {
            $attributes[$day] = in_array($day, $data['selectedDays'], true);
        }

        return $attributes;
    }

    /**
     * @param array<int, int> $menuIds
     * @return array<int, int>
     */
    private function validatedMenuIds(array $menuIds, int $companyId): array
    {
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));

        if ($menuIds === []) {
            throw ValidationException::withMessages([
                'menuItems' => 'Select at least one available recipe.',
            ]);
        }

        $availableCount = Recipe::query()
            ->where('company_id', $companyId)
            ->where('status', 'AVAILABLE')
            ->whereIn('id', $menuIds)
            ->count();

        if ($availableCount !== count($menuIds)) {
            throw ValidationException::withMessages([
                'menuItems' => 'One or more selected recipes are no longer available.',
            ]);
        }

        return $menuIds;
    }

    /**
     * @param array<int, int> $menuIds
     */
    private function replaceRecipes(RecipeController $controller, array $menuIds): void
    {
        $controller->Branchrecipes()->delete();

        $controller->Branchrecipes()->createMany(
            array_map(
                static fn(int $menuId): array => ['menu_id' => $menuId, 'bal_qty' => 0],
                $menuIds,
            ),
        );
    }
}
