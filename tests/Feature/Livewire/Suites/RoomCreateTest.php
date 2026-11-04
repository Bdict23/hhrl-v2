<?php

declare(strict_types=1);

use App\Models\Suite\Amenity;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    if (! Schema::hasTable('room_categories')) {
        Schema::create('room_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('room_amenities')) {
        Schema::create('room_amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('rooms')) {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('room_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedSmallInteger('capacity_adults')->default(2);
            $table->unsignedSmallInteger('capacity_children')->default(0);
            $table->boolean('allows_pets')->default(false);
            $table->decimal('pet_fee', 10, 2)->default(0);
            $table->decimal('base_price', 10, 2);
            $table->unsignedSmallInteger('floor_number')->nullable();
            $table->string('status')->default('available');
            $table->string('booking_type')->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (! Schema::hasTable('room_room_amenities')) {
        Schema::create('room_room_amenities', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('amenity_id');
            $table->primary(['room_id', 'amenity_id']);
        });
    }

    if (! Schema::hasTable('room_photos')) {
        Schema::create('room_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });
    }
});

it('creates a room with its amenities and cover photo', function () {
    Storage::fake('public');

    $category = RoomCategory::query()->create([
        'name' => 'Deluxe',
        'slug' => 'deluxe',
        'is_active' => true,
    ]);
    $amenity = Amenity::query()->create(['name' => 'Wi-Fi']);

    Livewire::test('suites.rooms.room-create')
        ->set([
            'category_id' => $category->id,
            'room_number' => 'DR-01',
            'name' => 'Deluxe Room',
            'capacity_adults' => '2',
            'capacity_children' => '1',
            'allows_pets' => true,
            'pet_fee' => '300',
            'base_price' => '2500',
            'floor_number' => '1',
            'booking_type' => 'both',
            'status' => 'available',
            'is_active' => true,
            'amenities' => [(string) $amenity->id],
            'photos' => [UploadedFile::fake()->image('cover.png')],
        ])
        ->call('createRoom')
        ->assertHasNoErrors()
        ->assertRedirect(route('rooms.list'));

    $room = Room::query()->where('room_number', 'DR-01')->firstOrFail();
    $photo = $room->photos()->firstOrFail();

    expect($room->gallery)->toBe([$photo->path])
        ->and($room->allows_pets)->toBeTrue()
        ->and($room->capacity_children)->toBe(1)
        ->and($photo->is_cover)->toBeTrue();

    $this->assertDatabaseHas('room_room_amenities', [
        'room_id' => $room->id,
        'amenity_id' => $amenity->id,
    ]);
    Storage::disk('public')->assertExists($photo->path);
});

it('rejects invalid room details without creating a room', function () {
    $inactiveCategory = RoomCategory::query()->create([
        'name' => 'Closed Category',
        'slug' => 'closed-category',
        'is_active' => false,
    ]);

    Livewire::test('suites.rooms.room-create')
        ->set([
            'category_id' => $inactiveCategory->id,
            'room_number' => '',
            'name' => '',
            'capacity_adults' => '0',
            'base_price' => '-1',
            'status' => 'unknown',
        ])
        ->call('createRoom')
        ->assertHasErrors([
            'category_id' => 'exists',
            'room_number' => 'required',
            'name' => 'required',
            'capacity_adults' => 'min',
            'base_price' => 'min',
            'status' => 'in',
        ]);

    expect(Room::query()->count())->toBe(0);
});

it('rejects a room number that already exists', function () {
    $category = RoomCategory::query()->create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);

    Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'ST-01',
        'name' => 'Existing Room',
        'capacity_adults' => 2,
        'base_price' => 1000,
    ]);

    Livewire::test('suites.rooms.room-create')
        ->set([
            'category_id' => $category->id,
            'room_number' => 'ST-01',
            'name' => 'Duplicate Room',
        ])
        ->call('createRoom')
        ->assertHasErrors(['room_number' => 'unique']);

    expect(Room::query()->count())->toBe(1);
});
