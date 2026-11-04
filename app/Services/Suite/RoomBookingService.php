<?php

namespace App\Services\Suite;

use App\Events\BookingCancelled;
use App\Events\CheckInUpdated;
use App\Models\Suite\HousekeepingLog;
use App\Models\Suite\Room;
use App\Models\Suite\RoomAddonService;
use App\Models\Suite\RoomBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class RoomBookingService
{
    public function checkIn(RoomBooking $booking): RoomBooking
    {
        $checkedInBooking = DB::transaction(function () use ($booking): RoomBooking {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($lockedBooking->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only pending or confirmed bookings can be checked in.',
                ]);
            }

            $lockedBooking->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            Room::query()->whereKey($lockedBooking->room_id)->update(['status' => 'occupied']);

            if ($lockedBooking->user_id !== null) {
                User::query()
                    ->whereKey($lockedBooking->user_id)
                    ->increment('visit_count', 1, ['last_visited_at' => now()]);
            }

            return $lockedBooking->fresh(['room']);
        });

        $this->broadcastWithoutFailingTheBooking(new CheckInUpdated($checkedInBooking));

        return $checkedInBooking;
    }

    /**
     * @param array{settle_payment?: bool, payment_method?: string|null, housekeeper_id?: int|string|null, housekeeping_notes?: string|null} $data
     */
    public function checkout(RoomBooking $booking, array $data): RoomBooking
    {
        $checkedOutBooking = DB::transaction(function () use ($booking, $data): RoomBooking {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== 'checked_in') {
                throw ValidationException::withMessages([
                    'booking' => 'Only checked-in bookings can be checked out.',
                ]);
            }

            $settlePayment = (bool) ($data['settle_payment'] ?? false);
            $balanceDue = $this->balanceDueInCents($lockedBooking);

            if ($settlePayment && $balanceDue > 0) {
                $paymentMethod = $data['payment_method'] ?? null;

                if (! in_array($paymentMethod, ['cash', 'card', 'gcash', 'bank_transfer'], true)) {
                    throw ValidationException::withMessages([
                        'checkoutPaymentMethod' => 'Choose a valid payment method to collect the remaining balance.',
                    ]);
                }

                $lockedBooking->update([
                    'amount_paid' => number_format(
                        ($this->amountPaidInCents($lockedBooking) + $balanceDue) / 100,
                        2,
                        '.',
                        '',
                    ),
                    'payment_status' => 'paid',
                    'payment_method' => $paymentMethod,
                ]);
            }

            $lockedBooking->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
            ]);

            $room = Room::query()->lockForUpdate()->findOrFail($lockedBooking->room_id);
            $room->update(['status' => 'housekeeping']);

            $housekeeperId = $data['housekeeper_id'] ?? null;
            $housekeeper = null;

            if ($housekeeperId !== null && $housekeeperId !== '') {
                $housekeeper = User::query()
                    ->whereKey($housekeeperId)
                    ->where('role', 'housekeeper')
                    ->first();

                if (! $housekeeper) {
                    throw ValidationException::withMessages([
                        'housekeeperId' => 'Select a housekeeper from the staff list.',
                    ]);
                }
            }

            $notes = trim((string) ($data['housekeeping_notes'] ?? ''));

            HousekeepingLog::query()->create([
                'room_id' => $room->id,
                'user_id' => $housekeeper?->id,
                'staff_name' => $housekeeper?->name ?? 'Unassigned',
                'status' => 'dirty',
                'notes' => $notes !== ''
                    ? $notes
                    : "Checked out: {$lockedBooking->booking_code}. Please inspect, clean, and confirm ready.",
                'cleaning_timestamp' => now(),
            ]);

            return $lockedBooking->fresh(['room']);
        });

        $this->broadcastWithoutFailingTheBooking(new CheckInUpdated($checkedOutBooking));

        return $checkedOutBooking;
    }

    /**
     * @param array{amount: int|float|string, payment_method: string} $data
     */
    public function settlePayment(RoomBooking $booking, array $data): RoomBooking
    {
        return DB::transaction(function () use ($booking, $data): RoomBooking {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Payments cannot be recorded for a cancelled booking.',
                ]);
            }

            Validator::make($data, [
                'payment_method' => ['required', 'string', 'in:cash,card,gcash,bank_transfer'],
                'amount' => ['required', 'numeric', 'gt:0'],
            ])->validate();

            $amountCents = (int) round((float) $data['amount'] * 100);
            $balanceDueCents = $this->balanceDueInCents($lockedBooking);

            if ($amountCents < 1 || $amountCents > $balanceDueCents) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Payment must be greater than zero and cannot exceed the current balance.',
                ]);
            }

            $newAmountPaidCents = $this->amountPaidInCents($lockedBooking) + $amountCents;
            $totalPriceCents = (int) round((float) $lockedBooking->total_price * 100);

            $lockedBooking->update([
                'amount_paid' => number_format($newAmountPaidCents / 100, 2, '.', ''),
                'payment_status' => $newAmountPaidCents >= $totalPriceCents ? 'paid' : 'partial',
                'payment_method' => $data['payment_method'],
            ]);

            return $lockedBooking->fresh();
        });
    }

    /**
     * @param array{addon_service_id?: int|string|null, item_name?: string|null, unit_price?: int|float|string|null, quantity: int|string} $data
     */
    public function addExtraCharge(RoomBooking $booking, array $data): RoomBooking
    {
        return DB::transaction(function () use ($booking, $data): RoomBooking {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (in_array($lockedBooking->status, ['cancelled', 'checked_out'], true)) {
                throw ValidationException::withMessages([
                    'addonServiceId' => 'Extra services cannot be added to a cancelled or checked-out booking.',
                ]);
            }

            Validator::make($data, [
                'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            ])->validate();

            $serviceId = $data['addon_service_id'] ?? null;
            $addonService = null;

            if ($serviceId !== null && $serviceId !== '') {
                $addonService = RoomAddonService::query()
                    ->whereKey($serviceId)
                    ->where('is_active', true)
                    ->first();

                if (! $addonService) {
                    throw ValidationException::withMessages([
                        'addonServiceId' => 'Choose an available service, or select the custom item option.',
                    ]);
                }
            } else {
                Validator::make($data, [
                    'item_name' => ['required', 'string', 'max:255'],
                    'unit_price' => ['required', 'numeric', 'min:0'],
                ])->validate();
            }

            $itemName = $addonService?->name ?? trim((string) $data['item_name']);
            $unitPrice = (float) ($addonService?->price ?? $data['unit_price']);
            $quantity = (int) $data['quantity'];
            $subtotal = round($unitPrice * $quantity, 2);

            $lockedBooking->addons()->create([
                'addon_service_id' => $addonService?->id,
                'item_name' => $itemName,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
            ]);

            $addonsTotalCents = (int) round((float) $lockedBooking->addons()->sum('subtotal') * 100);
            $basePriceCents = (int) round((float) $lockedBooking->base_price * 100);
            $petFeeCents = (int) round((float) $lockedBooking->pet_fee_total * 100);
            $discountCents = (int) round((float) $lockedBooking->discount_amount * 100);
            $totalPriceCents = max(0, $basePriceCents + $addonsTotalCents + $petFeeCents - $discountCents);
            $amountPaidCents = $this->amountPaidInCents($lockedBooking);

            $lockedBooking->update([
                'addons_total' => number_format($addonsTotalCents / 100, 2, '.', ''),
                'total_price' => number_format($totalPriceCents / 100, 2, '.', ''),
                'payment_status' => match (true) {
                    $amountPaidCents >= $totalPriceCents => 'paid',
                    $amountPaidCents > 0 => 'partial',
                    default => 'unpaid',
                },
            ]);

            return $lockedBooking->fresh(['addons.addonService']);
        });
    }

    public function completeHousekeeping(RoomBooking $booking, ?User $staff = null): HousekeepingLog
    {
        return DB::transaction(function () use ($booking, $staff): HousekeepingLog {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== 'checked_out') {
                throw ValidationException::withMessages([
                    'housekeeping' => 'Housekeeping can only be completed after guest checkout.',
                ]);
            }

            $room = Room::query()->lockForUpdate()->findOrFail($lockedBooking->room_id);

            if ($room->status !== 'housekeeping') {
                throw ValidationException::withMessages([
                    'housekeeping' => 'This room is not awaiting housekeeping.',
                ]);
            }

            $log = $room->housekeepingLogs()->lockForUpdate()->first();

            if (! $log || $log->status !== 'dirty') {
                throw ValidationException::withMessages([
                    'housekeeping' => 'There is no pending housekeeping task to complete for this room.',
                ]);
            }

            $log->update([
                'status' => 'clean',
                'user_id' => $staff?->id ?? $log->user_id,
                'staff_name' => $staff?->name ?? $log->staff_name,
                'completed_at' => now(),
            ]);
            $room->update(['status' => 'available']);

            return $log->fresh();
        });
    }

    public function cancel(RoomBooking $booking): RoomBooking
    {
        $cancelledBooking = DB::transaction(function () use ($booking): RoomBooking {
            $lockedBooking = RoomBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($lockedBooking->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only pending or confirmed bookings can be cancelled.',
                ]);
            }

            $lockedBooking->update(['status' => 'cancelled']);

            $hasActiveBooking = RoomBooking::query()
                ->where('room_id', $lockedBooking->room_id)
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->exists();

            if (! $hasActiveBooking) {
                Room::query()
                    ->whereKey($lockedBooking->room_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available']);
            }

            return $lockedBooking->fresh();
        });

        $this->broadcastWithoutFailingTheBooking(new BookingCancelled($cancelledBooking));

        return $cancelledBooking;
    }

    private function amountPaidInCents(RoomBooking $booking): int
    {
        return (int) round((float) ($booking->amount_paid ?? 0) * 100);
    }

    private function balanceDueInCents(RoomBooking $booking): int
    {
        return max(
            0,
            (int) round((float) $booking->total_price * 100) - $this->amountPaidInCents($booking),
        );
    }

    private function broadcastWithoutFailingTheBooking(CheckInUpdated|BookingCancelled $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
