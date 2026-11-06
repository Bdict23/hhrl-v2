<?php

use App\Services\Restaurant\MenuControllerService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('branches', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id');
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->unsignedBigInteger('emp_id')->nullable();
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('category_name');
    });

    Schema::create('menus', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id');
        $table->unsignedBigInteger('category_id')->nullable();
        $table->string('status');
        $table->string('menu_name');
        $table->string('menu_code')->nullable();
        $table->string('menu_type')->nullable();
        $table->string('menu_image')->nullable();
    });

    Schema::create('price_levels', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('menu_id')->nullable();
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->string('price_type');
        $table->decimal('amount', 10, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('branch_menus', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('branch_id');
        $table->string('control_name')->nullable();
        $table->boolean('is_available')->default(false);
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->boolean('mon')->default(false);
        $table->boolean('tue')->default(false);
        $table->boolean('wed')->default(false);
        $table->boolean('thu')->default(false);
        $table->boolean('fri')->default(false);
        $table->boolean('sat')->default(false);
        $table->boolean('sun')->default(false);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('branch_menu_recipes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('branch_menu_id');
        $table->unsignedBigInteger('menu_id');
        $table->integer('bal_qty')->default(0);
        $table->timestamps();
    });
});

it('creates a menu schedule with selected days and recipes', function (): void {
    $branchId = DB::table('branches')->insertGetId(['company_id' => 12]);
    $recipeId = DB::table('menus')->insertGetId([
        'company_id' => 12,
        'status' => 'AVAILABLE',
        'menu_name' => 'Vegetable Soup',
    ]);

    $schedule = app(MenuControllerService::class)->create([
        'branch_id' => $branchId,
        'controlName' => 'Weekday Lunch',
        'effectiveDate' => '2026-11-01',
        'endDate' => '2026-11-30',
        'status' => 'active',
        'selectedDays' => ['mon', 'wed', 'fri'],
        'menuItems' => [$recipeId],
        'created_by' => null,
        'notes' => null,
    ]);

    expect((bool) $schedule->is_available)->toBeTrue()
        ->and((bool) $schedule->mon)->toBeTrue()
        ->and((bool) $schedule->tue)->toBeFalse()
        ->and((bool) $schedule->wed)->toBeTrue()
        ->and((bool) $schedule->fri)->toBeTrue();

    $this->assertDatabaseHas('branch_menu_recipes', [
        'branch_menu_id' => $schedule->id,
        'menu_id' => $recipeId,
    ]);
});

it('creates a schedule through the Livewire form and redirects to its view', function (): void {
    $branchId = DB::table('branches')->insertGetId(['company_id' => 14]);
    $recipeId = DB::table('menus')->insertGetId([
        'company_id' => 14,
        'status' => 'AVAILABLE',
        'menu_name' => 'Lunch Special',
    ]);
    $user = User::query()->create([
        'name' => 'Restaurant User',
        'email' => 'restaurant@example.test',
        'password' => 'password',
        'branch_id' => $branchId,
    ]);

    $this->actingAs($user);

    Livewire::test('restaurant.menu-controller.menu-controller-create')
        ->set([
            'controlName' => 'Lunch Schedule',
            'effectiveDate' => '2026-11-05',
            'endDate' => '2026-11-30',
            'status' => 'active',
            'selectedDays' => ['mon', 'wed'],
            'menuItems' => [$recipeId],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurant.menu-controller-view', ['id' => 1]));

    $this->assertDatabaseHas('branch_menus', [
        'id' => 1,
        'branch_id' => $branchId,
        'control_name' => 'Lunch Schedule',
        'is_available' => true,
    ]);
    $this->assertDatabaseHas('branch_menu_recipes', [
        'branch_menu_id' => 1,
        'menu_id' => $recipeId,
    ]);

    Livewire::test('restaurant.menu-controller.menu-controller-edit', ['id' => 1])
        ->set([
            'controlName' => 'Updated Lunch Schedule',
            'effectiveDate' => '2026-11-06',
            'endDate' => '2026-12-01',
            'status' => 'inactive',
            'selectedDays' => ['sat'],
            'menuItems' => [$recipeId],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('restaurant.menu-controller-view', ['id' => 1]));

    $this->assertDatabaseHas('branch_menus', [
        'id' => 1,
        'control_name' => 'Updated Lunch Schedule',
        'is_available' => false,
        'start_date' => '2026-11-06',
        'sat' => true,
        'mon' => false,
    ]);
});

it('updates schedule details and replaces the included recipes', function (): void {
    $branchId = DB::table('branches')->insertGetId(['company_id' => 18]);
    $firstRecipeId = DB::table('menus')->insertGetId([
        'company_id' => 18,
        'status' => 'AVAILABLE',
        'menu_name' => 'First Recipe',
    ]);
    $secondRecipeId = DB::table('menus')->insertGetId([
        'company_id' => 18,
        'status' => 'AVAILABLE',
        'menu_name' => 'Second Recipe',
    ]);

    $service = app(MenuControllerService::class);
    $schedule = $service->create([
        'branch_id' => $branchId,
        'controlName' => 'Original Schedule',
        'effectiveDate' => '2026-11-01',
        'endDate' => null,
        'status' => 'active',
        'selectedDays' => ['tue'],
        'menuItems' => [$firstRecipeId],
        'created_by' => null,
        'notes' => null,
    ]);

    $updated = $service->update([
        'id' => $schedule->id,
        'branch_id' => $branchId,
        'controlName' => 'Updated Schedule',
        'effectiveDate' => '2026-11-05',
        'endDate' => '2026-12-01',
        'status' => 'inactive',
        'selectedDays' => ['sat', 'sun'],
        'menuItems' => [$secondRecipeId],
        'notes' => 'Weekend service',
    ]);

    expect($updated->control_name)->toBe('Updated Schedule')
        ->and((bool) $updated->is_available)->toBeFalse()
        ->and((bool) $updated->sat)->toBeTrue()
        ->and((bool) $updated->sun)->toBeTrue()
        ->and((bool) $updated->tue)->toBeFalse();

    $this->assertDatabaseMissing('branch_menu_recipes', [
        'branch_menu_id' => $schedule->id,
        'menu_id' => $firstRecipeId,
    ]);
    $this->assertDatabaseHas('branch_menu_recipes', [
        'branch_menu_id' => $schedule->id,
        'menu_id' => $secondRecipeId,
    ]);
});

it('changes availability only for a schedule in the requested branch', function (): void {
    $branchId = DB::table('branches')->insertGetId(['company_id' => 21]);
    $otherBranchId = DB::table('branches')->insertGetId(['company_id' => 21]);
    $recipeId = DB::table('menus')->insertGetId([
        'company_id' => 21,
        'status' => 'AVAILABLE',
        'menu_name' => 'House Salad',
    ]);
    $service = app(MenuControllerService::class);
    $schedule = $service->create([
        'branch_id' => $branchId,
        'controlName' => 'Dinner Schedule',
        'effectiveDate' => '2026-11-01',
        'endDate' => null,
        'status' => 'active',
        'selectedDays' => ['sun'],
        'menuItems' => [$recipeId],
        'created_by' => null,
        'notes' => null,
    ]);

    $inactiveSchedule = $service->setAvailability($schedule->id, $branchId, false);

    expect((bool) $inactiveSchedule->is_available)->toBeFalse();

    $this->assertDatabaseHas('branch_menus', [
        'id' => $schedule->id,
        'branch_id' => $branchId,
        'is_available' => false,
    ]);

    $activeSchedule = $service->setAvailability($schedule->id, $branchId, true);

    expect((bool) $activeSchedule->is_available)->toBeTrue();

    $this->assertDatabaseHas('branch_menus', [
        'id' => $schedule->id,
        'branch_id' => $branchId,
        'is_available' => true,
    ]);

    expect(fn () => $service->setAvailability($schedule->id, $otherBranchId, true))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('rejects recipes that are not available to the branch company', function (): void {
    $branchId = DB::table('branches')->insertGetId(['company_id' => 31]);
    $foreignRecipeId = DB::table('menus')->insertGetId([
        'company_id' => 32,
        'status' => 'AVAILABLE',
        'menu_name' => 'Foreign Recipe',
    ]);

    expect(fn () => app(MenuControllerService::class)->create([
        'branch_id' => $branchId,
        'controlName' => 'Invalid Schedule',
        'effectiveDate' => '2026-11-01',
        'endDate' => null,
        'status' => 'active',
        'selectedDays' => ['mon'],
        'menuItems' => [$foreignRecipeId],
        'created_by' => null,
        'notes' => null,
    ]))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('branch_menus', 0);
    $this->assertDatabaseCount('branch_menu_recipes', 0);
});
