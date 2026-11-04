<?php

declare(strict_types=1);

use App\Models\Suite\Amenity;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('room_resort_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->string('group')->default('general');
        $table->timestamps();
    });
    Schema::create('room_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('icon')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::create('room_amenities', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('icon')->nullable();
        $table->timestamps();
    });
    Schema::create('rooms', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('category_id');
        $table->string('room_number')->unique();
        $table->string('name');
        $table->unsignedSmallInteger('capacity_adults')->default(2);
        $table->decimal('base_price', 10, 2);
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('room_room_amenities', function (Blueprint $table): void {
        $table->unsignedBigInteger('room_id');
        $table->unsignedBigInteger('amenity_id');
        $table->primary(['room_id', 'amenity_id']);
    });
});

it('creates a room category with its slug and settings', function () {
    Livewire::test('suites.suite-settings')
        ->call('openCategoryModal')
        ->set('categoryName', 'Ocean View')
        ->assertSet('categorySlug', 'ocean-view')
        ->set([
            'categoryDescription' => 'Rooms overlooking the ocean.',
            'categorySortOrder' => 2,
            'categoryIsActive' => true,
        ])
        ->call('saveCategoryAction')
        ->call('createCategory')
        ->assertHasNoErrors()
        ->assertSet('createCategoryModal', false);

    $category = RoomCategory::query()->where('slug', 'ocean-view')->firstOrFail();

    expect($category->name)->toBe('Ocean View')
        ->and($category->description)->toBe('Rooms overlooking the ocean.')
        ->and($category->sort_order)->toBe(2)
        ->and($category->is_active)->toBeTrue();
});

it('rejects a duplicate category slug without creating a category', function () {
    RoomCategory::query()->create([
        'name' => 'Existing Category',
        'slug' => 'ocean-view',
    ]);

    Livewire::test('suites.suite-settings')
        ->set([
            'categoryName' => 'New Category',
            'categorySlug' => 'ocean-view',
        ])
        ->call('createCategory')
        ->assertHasErrors(['categorySlug' => 'unique']);

    expect(RoomCategory::query()->count())->toBe(1);
});

it('creates an amenity using its validated name', function () {
    Livewire::test('suites.suite-settings')
        ->call('openAmenityModal')
        ->set('amenityName', 'Smart TV')
        ->call('saveAmenityAction')
        ->call('createAmenity')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('room_amenities', ['name' => 'Smart TV']);
});

it('updates an amenity name', function () {
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);

    Livewire::test('suites.suite-settings')
        ->call('editAmenityAction', $amenity->id)
        ->set('amenityName', 'Wireless Internet')
        ->call('saveAmenityUpdateAction')
        ->call('updateAmenity')
        ->assertHasNoErrors();

    expect($amenity->fresh()->name)->toBe('Wireless Internet');
});

it('allows an amenity to keep its current name when editing', function () {
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);

    Livewire::test('suites.suite-settings')
        ->call('editAmenityAction', $amenity->id)
        ->call('updateAmenity')
        ->assertHasNoErrors();

    expect($amenity->fresh()->name)->toBe('Wi-Fi');
});

it('rejects an amenity name already used by another amenity', function () {
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);
    Amenity::query()->create(['name' => 'Pool Access']);

    Livewire::test('suites.suite-settings')
        ->call('editAmenityAction', $amenity->id)
        ->set('amenityName', 'Pool Access')
        ->call('updateAmenity')
        ->assertHasErrors(['amenityName' => 'unique']);

    expect($amenity->fresh()->name)->toBe('Wi-Fi');
});

it('does not delete an amenity linked to a soft-deleted room', function () {
    $category = RoomCategory::query()->create([
        'name' => 'Standard',
        'slug' => 'standard',
    ]);
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'ST-01',
        'name' => 'Standard Room',
        'base_price' => 1000,
    ]);
    $room->amenities()->attach($amenity);
    $room->delete();

    Livewire::test('suites.suite-settings')
        ->call('deleteAmenityAction', $amenity->id)
        ->call('deleteAmenity');

    $this->assertModelExists($amenity);
    $this->assertDatabaseHas('room_room_amenities', [
        'room_id' => $room->id,
        'amenity_id' => $amenity->id,
    ]);
});

it('deletes an amenity that is not assigned to a room', function () {
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);

    Livewire::test('suites.suite-settings')
        ->call('deleteAmenityAction', $amenity->id)
        ->call('deleteAmenity');

    $this->assertModelMissing($amenity);
});
