<?php

declare(strict_types=1);

use App\Models\Suite\Room;
use App\Models\Suite\RoomAddonService;
use App\Models\Suite\RoomBooking;
use App\Models\Suite\RoomCategory;
use App\Models\User;
use App\Services\Suite\RoomBookingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->nullable();
        $table->string('emp_id')->nullable();
        $table->unsignedInteger('visit_count')->default(0);
        $table->timestamp('last_visited_at')->nullable();
        $table->timestamps();
    });

    Schema::create('room_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    Schema::create('rooms', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id');
        $table->string('room_number')->unique();
        $table->string('name');
        $table->string('status')->default('available');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('room_photos', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('room_id');
        $table->string('path');
        $table->string('caption')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_cover')->default(false);
        $table->timestamps();
    });

    Schema::create('room_addon_services', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('icon')->nullable();
        $table->string('pricing_type')->nullable();
        $table->decimal('price', 10, 2);
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });

    Schema::create('room_bookings', function (Blueprint $table) {
        $table->id();
        $table->string('booking_code')->unique();
        $table->unsignedBigInteger('room_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('discount_id')->nullable();
        $table->string('guest_name');
        $table->string('guest_email')->nullable();
        $table->string('guest_phone')->nullable();
        $table->dateTime('check_in');
        $table->dateTime('check_out');
        $table->string('booking_type')->default('overnight');
        $table->unsignedInteger('adults')->default(1);
        $table->unsignedInteger('children')->default(0);
        $table->unsignedInteger('pets')->default(0);
        $table->decimal('base_price', 10, 2)->default(0);
        $table->decimal('addons_total', 10, 2)->default(0);
        $table->decimal('pet_fee_total', 10, 2)->default(0);
        $table->decimal('discount_amount', 10, 2)->default(0);
        $table->decimal('total_price', 10, 2)->default(0);
        $table->decimal('amount_paid', 10, 2)->default(0);
        $table->string('status')->default('pending');
        $table->string('payment_status')->default('unpaid');
        $table->string('payment_method')->nullable();
        $table->string('payment_proof')->nullable();
        $table->boolean('is_walk_in')->default(false);
        $table->text('special_requests')->nullable();
        $table->string('qr_code_path')->nullable();
        $table->dateTime('checked_in_at')->nullable();
        $table->dateTime('checked_out_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('room_booking_addons', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('addon_service_id')->nullable();
        $table->string('item_name')->nullable();
        $table->unsignedInteger('quantity');
        $table->decimal('unit_price', 10, 2);
        $table->decimal('subtotal', 10, 2);
        $table->timestamps();
    });

    Schema::create('room_housekeeping_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('room_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('staff_name')->nullable();
        $table->string('status');
        $table->text('notes')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamp('cleaning_timestamp')->nullable();
        $table->timestamps();
    });
});

it('adds catalog services at their stored price and settles the remaining balance', function () {
    $category = RoomCategory::query()->create(['name' => 'Standard', 'slug' => 'standard']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'S-01',
        'name' => 'Standard Room',
    ]);
    $booking = RoomBooking::query()->create([
        'room_id' => $room->id,
        'guest_name' => 'Guest',
        'check_in' => now(),
        'check_out' => now()->addDay(),
        'base_price' => 100,
        'total_price' => 100,
        'amount_paid' => 80,
        'status' => 'checked_in',
        'payment_status' => 'partial',
    ]);
    $service = RoomAddonService::query()->create([
        'name' => 'Breakfast',
        'price' => 15,
        'is_active' => true,
    ]);
    $bookingService = app(RoomBookingService::class);

    $updatedBooking = $bookingService->addExtraCharge($booking, [
        'addon_service_id' => $service->id,
        'item_name' => 'Tampered label',
        'unit_price' => '0.01',
        'quantity' => 2,
    ]);

    expect($updatedBooking->total_price)->toBe('130.00')
        ->and($updatedBooking->addons_total)->toBe('30.00')
        ->and($updatedBooking->balance_due)->toBe(50.0);
    $this->assertDatabaseHas('room_booking_addons', [
        'booking_id' => $booking->id,
        'addon_service_id' => $service->id,
        'item_name' => 'Breakfast',
        'unit_price' => '15.00',
        'subtotal' => '30.00',
    ]);

    $settledBooking = $bookingService->settlePayment($updatedBooking, [
        'amount' => '50.00',
        'payment_method' => 'cash',
    ]);

    expect($settledBooking->amount_paid)->toBe('130.00')
        ->and($settledBooking->payment_status)->toBe('paid')
        ->and($settledBooking->balance_due)->toBe(0.0);
});

it('checks in, checks out into housekeeping, and makes the room available after cleaning', function () {
    $category = RoomCategory::query()->create(['name' => 'Standard', 'slug' => 'standard']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'S-02',
        'name' => 'Standard Room',
    ]);
    $booking = RoomBooking::query()->create([
        'room_id' => $room->id,
        'guest_name' => 'Guest',
        'check_in' => now(),
        'check_out' => now()->addDay(),
        'base_price' => 100,
        'total_price' => 100,
        'amount_paid' => 25,
        'status' => 'confirmed',
        'payment_status' => 'partial',
    ]);
    $housekeeper = User::query()->create([
        'name' => 'Room Attendant',
        'email' => 'housekeeper@example.test',
        'password' => 'password',
        'role' => 'housekeeper',
    ]);
    $service = app(RoomBookingService::class);

    $checkedInBooking = $service->checkIn($booking);
    $checkedOutBooking = $service->checkout($checkedInBooking, [
        'settle_payment' => true,
        'payment_method' => 'card',
        'housekeeper_id' => $housekeeper->id,
        'housekeeping_notes' => 'Replace linens.',
    ]);
    $housekeepingLog = $room->housekeepingLogs()->firstOrFail();

    expect($checkedInBooking->status)->toBe('checked_in')
        ->and($checkedOutBooking->status)->toBe('checked_out')
        ->and($checkedOutBooking->payment_status)->toBe('paid')
        ->and($room->fresh()->status)->toBe('housekeeping')
        ->and($housekeepingLog->status)->toBe('dirty')
        ->and($housekeepingLog->user_id)->toBe($housekeeper->id)
        ->and($housekeepingLog->notes)->toBe('Replace linens.');

    $completedLog = $service->completeHousekeeping($checkedOutBooking, $housekeeper);

    expect($completedLog->status)->toBe('clean')
        ->and($completedLog->completed_at)->not->toBeNull()
        ->and($room->fresh()->status)->toBe('available');
});

it('shows the booking, validates payment, and requests confirmation before saving', function () {
    $category = RoomCategory::query()->create(['name' => 'Standard', 'slug' => 'standard']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'S-03',
        'name' => 'Standard Room',
    ]);
    $booking = RoomBooking::query()->create([
        'room_id' => $room->id,
        'guest_name' => 'Guest',
        'check_in' => now(),
        'check_out' => now()->addDay(),
        'base_price' => 100,
        'total_price' => 100,
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    $component = Livewire::test('suites.booking.booking-show', ['id' => $booking->id])
        ->assertSee($booking->booking_code)
        ->set('paymentAmount', '0')
        ->set('paymentMethod', 'cash')
        ->call('recordPayment')
        ->assertHasErrors(['paymentAmount' => 'gt'])
        ->assertSee('Payment must be greater than zero.');

    $component
        ->set('paymentAmount', '100')
        ->call('settlePaymentAction')
        ->assertDispatched('ts-ui:dialog', function (string $event, array $params) use ($booking): bool {
            return $event === 'ts-ui:dialog'
                && $params['type'] === 'question'
                && $params['title'] === 'Record this payment?'
                && str_contains($params['description'], $booking->booking_code);
        });

    expect($booking->fresh()->amount_paid)->toBe('0.00');

    $component->call('recordPayment')->assertHasNoErrors();

    expect($booking->fresh()->amount_paid)->toBe('100.00')
        ->and($booking->fresh()->payment_status)->toBe('paid');
});

it('rejects settlement above the current balance without changing payment state', function () {
    $category = RoomCategory::query()->create(['name' => 'Standard', 'slug' => 'standard']);
    $room = Room::query()->create([
        'category_id' => $category->id,
        'room_number' => 'S-04',
        'name' => 'Standard Room',
    ]);
    $booking = RoomBooking::query()->create([
        'room_id' => $room->id,
        'guest_name' => 'Guest',
        'check_in' => now(),
        'check_out' => now()->addDay(),
        'base_price' => 100,
        'total_price' => 100,
        'amount_paid' => 50,
        'status' => 'checked_in',
        'payment_status' => 'partial',
    ]);

    expect(fn () => app(RoomBookingService::class)->settlePayment($booking, [
        'amount' => '50.01',
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    expect($booking->fresh()->amount_paid)->toBe('50.00')
        ->and($booking->fresh()->payment_status)->toBe('partial');
});
