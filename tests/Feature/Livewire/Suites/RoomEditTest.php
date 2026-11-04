<?php

declare(strict_types=1);

use App\Models\Suite\Amenity;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

it('updates room details, amenities, and adds photos', function () {
    Storage::fake('public');

    $category = RoomCategory::query()->create([
        'name' => 'Deluxe',
        'slug' => 'deluxe',
        'is_active' => true,
    ]);
    $wifi = Amenity::query()->create(['name' => 'Wi-Fi']);
    $pool = Amenity::query()->create(['name' => 'Pool Access']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'DL-01',
        'name' => 'Old Room Name',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 1500,
    ]);
    $oldPhoto = $room->photos()->create([
        'path' => 'rooms/'.$room->id.'/existing.jpg',
        'sort_order' => 3,
        'is_cover' => true,
    ]);
    Storage::disk('public')->put($oldPhoto->path, 'existing photo');
    $room->amenities()->attach($wifi);

    Livewire::test('suites.rooms.room-edit', ['id' => $room->id])
        ->set([
            'room_number' => 'DL-01',
            'name' => 'Updated Deluxe Room',
            'description' => 'Updated room description',
            'capacity_adults' => '3',
            'capacity_children' => '2',
            'allows_pets' => true,
            'pet_fee' => '450',
            'base_price' => '3200',
            'floor_number' => '2',
            'booking_type' => 'overnight',
            'status' => 'maintenance',
            'is_active' => false,
            'amenities' => [(string) $pool->id],
            'photos' => [UploadedFile::fake()->image('new-room.png')],
        ])
        ->call('updateRoom')
        ->assertHasNoErrors()
        ->assertRedirect(route('room.show', ['id' => $room->id]));

    $room->refresh();
    $photos = $room->photos()->get();

    expect($room->name)->toBe('Updated Deluxe Room')
        ->and($room->description)->toBe('Updated room description')
        ->and($room->capacity_adults)->toBe(3)
        ->and($room->capacity_children)->toBe(2)
        ->and($room->allows_pets)->toBeTrue()
        ->and($room->pet_fee)->toBe('450.00')
        ->and($room->base_price)->toBe('3200.00')
        ->and($room->floor_number)->toBe(2)
        ->and($room->booking_type)->toBe('overnight')
        ->and($room->status)->toBe('maintenance')
        ->and($room->is_active)->toBeFalse()
        ->and($room->amenities->modelKeys())->toBe([$pool->id])
        ->and($photos)->toHaveCount(2)
        ->and($photos->first()->is_cover)->toBeTrue()
        ->and($photos->last()->is_cover)->toBeFalse()
        ->and($photos->last()->sort_order)->toBe(4)
        ->and($room->gallery)->toBe($photos->pluck('path')->all());

    Storage::disk('public')->assertExists($oldPhoto->path);
    Storage::disk('public')->assertExists($photos->last()->path);
});

it('rejects a duplicate room number without updating the room', function () {
    $category = RoomCategory::query()->create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'ST-01',
        'name' => 'Room One',
        'capacity_adults' => 2,
        'base_price' => 1000,
    ]);
    Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'ST-02',
        'name' => 'Room Two',
        'capacity_adults' => 2,
        'base_price' => 1000,
    ]);

    Livewire::test('suites.rooms.room-edit', ['id' => $room->id])
        ->set('room_number', 'ST-02')
        ->set('name', 'Should Not Save')
        ->call('updateRoom')
        ->assertHasErrors(['room_number' => 'unique']);

    expect($room->fresh()->name)->toBe('Room One')
        ->and($room->fresh()->room_number)->toBe('ST-01');
});

it('deletes only a room photo and promotes the next photo to cover', function () {
    Storage::fake('public');

    $category = RoomCategory::query()->create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'ST-01',
        'name' => 'Room One',
        'capacity_adults' => 2,
        'base_price' => 1000,
    ]);
    $cover = $room->photos()->create([
        'path' => 'rooms/'.$room->id.'/cover.jpg',
        'sort_order' => 0,
        'is_cover' => true,
    ]);
    $nextPhoto = $room->photos()->create([
        'path' => 'rooms/'.$room->id.'/second.jpg',
        'sort_order' => 1,
    ]);
    Storage::disk('public')->put($cover->path, 'cover');
    Storage::disk('public')->put($nextPhoto->path, 'second');

    Livewire::test('suites.rooms.room-edit', ['id' => $room->id])
        ->call('removePhotoAction', $cover->id)
        ->call('destroyPhoto')
        ->assertHasNoErrors();

    $room->refresh();

    expect($room->photos()->count())->toBe(1)
        ->and($room->photos()->firstOrFail()->is_cover)->toBeTrue()
        ->and($room->gallery)->toBe([$nextPhoto->path]);

    Storage::disk('public')->assertMissing($cover->path);
    Storage::disk('public')->assertExists($nextPhoto->path);
});
