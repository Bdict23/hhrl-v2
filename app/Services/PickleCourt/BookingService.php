<?php

declare(strict_types=1);

namespace App\Services\PickleCourt;

use App\Events\PickleBooking;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\OperatingHour;
use App\Models\PricingRule;
use App\Models\SlotOverride;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Get operating hours for a specific date's day of week.
     */
    public function getOperatingHoursForDate(Carbon $date): OperatingHour
    {
        $dayOfWeek = strtolower($date->format('l'));

        /** @var OperatingHour|null $hours */
        $hours = OperatingHour::where('day_of_week', $dayOfWeek)->first();

        if (! $hours) {
            $hours = new OperatingHour([
                'day_of_week'  => $dayOfWeek,
                'opening_time' => '06:00:00',
                'closing_time' => '00:00:00',
                'is_closed'    => false,
            ]);
        }

        return $hours;
    }

    /**
     * Generate list of hourly time slots between opening and closing hours.
     * Supports overnight hours crossing midnight (e.g., 16:00 to 02:00 or 16:00 to 11:00).
     */
    public function generateTimeSlots(string $openingTime, string $closingTime): array
    {
        $slots = [];

        $startHour = (int) substr($openingTime, 0, 2);
        $closingHour = (int) substr($closingTime, 0, 2);

        // Normalize closing at 24:00 to 00:00 or 24
        if (str_starts_with($closingTime, '24')) {
            $closingHour = 24;
        }

        // If closing time is less than or equal to start time, or closing is 00:00, it extends into the next day / 24hr cycle
        if ($closingHour <= $startHour) {
            $closingHour += 24;
        }

        for ($hour = $startHour; $hour < $closingHour; $hour++) {
            $actualStartHour = $hour % 24;
            $actualNextHour = ($hour + 1) % 24;

            $slotStart = sprintf('%02d:00:00', $actualStartHour);
            $slotEnd = sprintf('%02d:00:00', $actualNextHour);

            $carbonStart = Carbon::createFromTime($actualStartHour, 0, 0);
            $carbonEnd = Carbon::createFromTime($actualNextHour, 0, 0);

            $startLabel = $carbonStart->format('g:i A');
            $endLabel = $carbonEnd->format('g:i A');
            $isNextDay = $hour >= 24;

            $slots[] = [
                'key'         => sprintf('%02d:00', $actualStartHour),
                'start_time'  => $slotStart,
                'end_time'    => $slotEnd,
                'start_label' => $startLabel,
                'end_label'   => $endLabel,
                'label'       => "{$startLabel} - {$endLabel}",
                'hour'        => $actualStartHour,
                'is_next_day' => $isNextDay,
            ];
        }

        return $slots;
    }

    /**
     * Calculate dynamic slot price based on court base rate and active pricing rules.
     */
    public function calculateSlotPrice(Court $court, Carbon $date, string $startTime, string $endTime): array
    {
        $baseRate = (float) $court->hourly_rate;
        $rules = PricingRule::active()->get();

        $appliedRule = null;
        $finalPrice = $baseRate;
        $isPeak = false;
        $adjustment = 0.0;

        foreach ($rules as $rule) {
            if ($rule->appliesTo($date, $startTime, $endTime)) {
                $appliedRule = $rule;
                $isPeak = true;

                if ($rule->type === 'multiplier') {
                    $multiplier = (float) $rule->rate_adjustment;
                    $finalPrice = round($baseRate * $multiplier, 2);
                    $adjustment = $finalPrice - $baseRate;
                } else {
                    $flat = (float) $rule->rate_adjustment;
                    $finalPrice = round($baseRate + $flat, 2);
                    $adjustment = $flat;
                }
                break;
            }
        }

        return [
            'base_rate'   => $baseRate,
            'price'       => $finalPrice,
            'is_peak'     => $isPeak,
            'rule_name'   => $appliedRule?->name,
            'rule_type'   => $appliedRule?->type,
            'adjustment'  => $adjustment,
        ];
    }

    /**
     * Get the consolidated matrix schedule for a given date.
     */
    public function getMatrixSchedule(Carbon $date): array
    {
        $dateString = $date->format('Y-m-d');
        $operatingHours = $this->getOperatingHoursForDate($date);

        if ($operatingHours->is_closed) {
            return [
                'date'            => $dateString,
                'date_formatted'  => $date->format('l, F j, Y'),
                'is_closed'       => true,
                'courts'          => [],
                'time_slots'      => [],
                'cells'           => [],
                'total_available' => 0,
            ];
        }

        $courts = Court::active()->ordered()->get();
        $timeSlots = $this->generateTimeSlots($operatingHours->opening_time, $operatingHours->closing_time);

        // Fetch overrides for this date
        $overrides = SlotOverride::where('date', $dateString)->get();

        // Fetch active booking slots for this date
        $bookingSlots = BookingSlot::with('booking')
            ->where('date', $dateString)
            ->whereHas('booking', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed']);
            })
            ->get();

        $cells = [];
        $totalAvailable = 0;

        foreach ($timeSlots as $slot) {
            $slotKey = $slot['key'];
            $cells[$slotKey] = [];

            foreach ($courts as $court) {
                $courtId = $court->id;

                // 1. Check for slot override
                $matchedOverride = $overrides->first(function (SlotOverride $o) use ($courtId, $dateString, $slot) {
                    return $o->intersectsSlot($courtId, $dateString, $slot['start_time'], $slot['end_time']);
                });

                if ($matchedOverride) {
                    if ($matchedOverride->isOpenPlay()) {
                        $cells[$slotKey][$courtId] = [
                            'state'            => 'open_play',
                            'state_label'      => 'Open Play',
                            'override_id'      => $matchedOverride->id,
                            'title'            => $matchedOverride->title ?? 'Open Play Rally',
                            'reason'           => $matchedOverride->reason,
                            'fee_per_person'   => $matchedOverride->fee_per_person ?? 150.00,
                            'max_participants' => $matchedOverride->max_participants,
                            'start_time'       => $matchedOverride->start_time,
                            'end_time'         => $matchedOverride->end_time,
                            'court_name'       => $court->name,
                            'is_clickable'     => false,
                            'can_view_details' => true,
                        ];
                        continue;
                    }

                    // Maintenance, Blocked, Private Event, Unavailable
                    $cells[$slotKey][$courtId] = [
                        'state'            => 'blocked',
                        'type'             => $matchedOverride->type,
                        'state_label'      => match ($matchedOverride->type) {
                            'maintenance'   => 'Maintenance',
                            'private_event' => 'Private Event',
                            default         => 'Blocked',
                        },
                        'override_id'      => $matchedOverride->id,
                        'title'            => $matchedOverride->title,
                        'reason'           => $matchedOverride->reason ?? 'Court currently unavailable.',
                        'is_clickable'     => false,
                        'can_view_details' => true,
                    ];
                    continue;
                }

                // 2. Check for active booking slots
                $matchedBookingSlot = $bookingSlots->first(function (BookingSlot $bs) use ($courtId, $slot) {
                    $bStart = substr($bs->start_time, 0, 5);
                    $sStart = substr($slot['start_time'], 0, 5);
                    return (int) $bs->court_id === (int) $courtId && $bStart === $sStart;
                });

                if ($matchedBookingSlot && $matchedBookingSlot->booking) {
                    $booking = $matchedBookingSlot->booking;
                    $isPending = $booking->isPending();

                    $cells[$slotKey][$courtId] = [
                        'state'            => $isPending ? 'pending' : 'booked',
                        'state_label'      => $isPending ? 'Awaiting Payment' : 'Reserved',
                        'booking_id'       => $booking->id,
                        'reference_code'   => $booking->reference_code,
                        'customer_name'    => $booking->customer_name,
                        'is_clickable'     => false,
                        'can_view_details' => false,
                    ];
                    continue;
                }

                // 3. Slot is Available
                $priceData = $this->calculateSlotPrice($court, $date, $slot['start_time'], $slot['end_time']);
                $totalAvailable++;

                $cells[$slotKey][$courtId] = [
                    'state'        => 'available',
                    'state_label'  => 'Available',
                    'court_id'     => $courtId,
                    'court_name'   => $court->name,
                    'date'         => $dateString,
                    'start_time'   => $slot['start_time'],
                    'end_time'     => $slot['end_time'],
                    'time_label'   => $slot['label'],
                    'start_label'  => $slot['start_label'],
                    'base_rate'    => $priceData['base_rate'],
                    'price'        => $priceData['price'],
                    'is_peak'      => $priceData['is_peak'],
                    'rule_name'    => $priceData['rule_name'],
                    'adjustment'   => $priceData['adjustment'],
                    'is_clickable' => true,
                ];
            }
        }

        return [
            'date'            => $dateString,
            'date_formatted'  => $date->format('l, F j, Y'),
            'is_closed'       => false,
            'courts'          => $courts,
            'time_slots'      => $timeSlots,
            'cells'           => $cells,
            'total_available' => $totalAvailable,
        ];
    }

    /**
     * Atomic, concurrency-safe checkout and booking creation.
     *
     * @param array{customer_name: string, customer_email: string, customer_phone: string, payment_method?: string, notes?: string} $customerData
     * @param array<array{court_id: int, date: string, start_time: string, end_time: string}> $selectedSlots
     * @throws ValidationException
     */
    public function createBooking(array $customerData, array $selectedSlots): Booking
    {
        if (empty($selectedSlots)) {
            throw ValidationException::withMessages([
                'slots' => 'Please select at least one court time slot.',
            ]);
        }

        return DB::transaction(function () use ($customerData, $selectedSlots) {
            $totalAmount = 0.0;
            $subtotalAmount = 0.0;
            $preparedSlots = [];

            foreach ($selectedSlots as $slot) {
                $courtId = (int) $slot['court_id'];
                $dateStr = (string) $slot['date'];
                $startTime = (string) $slot['start_time'];
                $endTime = (string) $slot['end_time'];

                $court = Court::find($courtId);
                if (! $court || ! $court->is_active) {
                    throw ValidationException::withMessages([
                        'slots' => "The selected court is no longer active.",
                    ]);
                }

                $slotDate = Carbon::parse($dateStr);

                // Lock & check existing slot overrides (maintenance, blocked, unavailable)
                $hasOverride = SlotOverride::where('date', $dateStr)
                    ->where(function ($q) use ($courtId) {
                        $q->whereNull('court_id')->orWhere('court_id', $courtId);
                    })
                    ->lockForUpdate()
                    ->get()
                    ->contains(function (SlotOverride $o) use ($courtId, $dateStr, $startTime, $endTime) {
                        return $o->intersectsSlot($courtId, $dateStr, $startTime, $endTime) && ! $o->isOpenPlay();
                    });

                if ($hasOverride) {
                    throw ValidationException::withMessages([
                        'slots' => "One or more selected slots on {$court->name} are currently blocked or under maintenance.",
                    ]);
                }

                // Lock & check existing booking slots
                $conflict = BookingSlot::where('court_id', $courtId)
                    ->where('date', $dateStr)
                    ->where('start_time', $startTime)
                    ->whereHas('booking', function ($q) {
                        $q->whereIn('status', ['pending', 'confirmed']);
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'slots' => "Slot {$startTime} on {$court->name} was just booked by another user. Please select another slot.",
                    ]);
                }

                // Dynamic pricing
                $priceInfo = $this->calculateSlotPrice($court, $slotDate, $startTime, $endTime);
                $slotPrice = (float) $priceInfo['price'];
                $baseRate = (float) $priceInfo['base_rate'];

                $subtotalAmount += $baseRate;
                $totalAmount += $slotPrice;

                $preparedSlots[] = [
                    'court_id'   => $courtId,
                    'date'       => $dateStr,
                    'start_time' => $startTime,
                    'end_time'   => $endTime,
                    'slot_type'  => 'regular',
                    'price'      => $slotPrice,
                ];
            }

            $discountOrSurcharge = $totalAmount - $subtotalAmount;

            $booking = Booking::create([
                'reference_code'        => Booking::generateReferenceCode(),
                'customer_name'         => mb_trim($customerData['customer_name']),
                'customer_email'        => mb_trim($customerData['customer_email']),
                'customer_phone'        => mb_trim($customerData['customer_phone']),
                'total_amount'          => $totalAmount,
                'subtotal_amount'       => $subtotalAmount,
                'discount_or_surcharge' => $discountOrSurcharge,
                'payment_method'        => $customerData['payment_method'] ?? 'gcash',
                'proof_of_payment_path' => $customerData['proof_of_payment_path'] ?? null,
                'payment_status'        => 'unpaid',
                'status'                => 'pending',
                'notes'                 => $customerData['notes'] ?? null,
            ]);

            foreach ($preparedSlots as $pSlot) {
                $booking->slots()->create($pSlot);
            }

            $users = User::all();
            if ($users->isNotEmpty()) {
                Notification::send($users, new GeneralNotification(
                    "New Pending Payment - Voucher {$booking->reference_code}",
                    "Customer {$booking->customer_name} placed booking voucher {$booking->reference_code} awaiting payment approval.",
                    route('admin.dashboard'),
                    'pending_payment',
                    $booking->id,
                    $booking->reference_code
                ));
            }

            PickleBooking::dispatch($booking);

            return $booking->load(['slots.court']);
        });
    }

    /**
     * Find booking by Reference Code or Phone Number.
     */
    public function findBooking(string $query): ?Booking
    {
        $trimmed = mb_trim($query);
        if (empty($trimmed)) {
            return null;
        }

        return Booking::with(['slots.court'])
            ->where(function ($q) use ($trimmed) {
                $q->whereRaw('LOWER(reference_code) = ?', [strtolower($trimmed)])
                    ->orWhere('customer_phone', 'like', '%' . $trimmed . '%');
            })
            ->latest()
            ->first();
    }

    /**
     * Find all bookings by phone number.
     *
     * @return Collection<int, Booking>
     */
    public function findBookingsByPhone(string $phone): Collection
    {
        $trimmed = mb_trim($phone);
        if (empty($trimmed)) {
            return new Collection();
        }

        return Booking::with(['slots.court'])
            ->where('customer_phone', 'like', '%' . $trimmed . '%')
            ->latest()
            ->get();
    }

    /**
     * Update booking status (confirm, cancel, complete).
     */
    public function updateBookingStatus(Booking $booking, string $status, ?string $adminNotes = null): Booking
    {
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (! in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $paymentStatus = match ($status) {
            'confirmed', 'completed' => 'paid',
            'cancelled'              => 'refunded',
            default                  => $booking->payment_status,
        };

        $booking->update([
            'status'         => $status,
            'payment_status' => $paymentStatus,
            'admin_notes'    => $adminNotes ?? $booking->admin_notes,
        ]);

        $notificationTitle = match ($status) {
            'confirmed' => "Payment Approved - Voucher {$booking->reference_code}",
            'cancelled' => "Declined - Voucher {$booking->reference_code}",
            default     => "Court Updated - Voucher {$booking->reference_code}",
        };

        $notificationType = match ($status) {
            'confirmed' => 'payment_approved',
            'cancelled' => 'cancellation_requested',
            default     => 'court_updated',
        };

        $notificationMessage = match ($status) {
            'confirmed' => "Booking voucher {$booking->reference_code} has been approved and marked as paid.",
            'cancelled' => "Booking voucher {$booking->reference_code} has been cancelled/declined.",
            default     => "Booking voucher {$booking->reference_code} status updated to {$status}.",
        };

        $users = User::all();
        if ($users->isNotEmpty()) {
            Notification::send($users, new GeneralNotification(
                $notificationTitle,
                $notificationMessage,
                route('admin.dashboard'),
                $notificationType,
                $booking->id,
                $booking->reference_code
            ));
        }

        PickleBooking::dispatch($booking);

        return $booking;
    }
}
