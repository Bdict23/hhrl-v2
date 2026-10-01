<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Court;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\BookingService;

/**
 * Maps DeepSeek function-call names to actual MCP tool logic.
 * Each entry returns a JSON-encodable array for the "tool" role message.
 */
class McpFunctionRegistry
{
    /** The admin user whose token authorises protected tool calls. */
    private User $admin;

    public function __construct(User $admin)
    {
        $this->admin = $admin;
    }

    /**
     * OpenAI-compatible tool definitions exposed to DeepSeek.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_dashboard_stats',
                    'description' => 'Get the admin KPI snapshot: pending bookings, confirmed, revenue today and this month, active courts.',
                    'parameters'  => ['type' => 'object', 'properties' => (object) [], 'required' => []],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'list_bookings',
                    'description' => 'List bookings with optional filters. Use this to answer questions like "show me pending bookings" or "unpaid GCash bookings".',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'status'         => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'cancelled', 'completed'], 'description' => 'Filter by booking status'],
                            'payment_status' => ['type' => 'string', 'enum' => ['unpaid', 'paid', 'refunded'], 'description' => 'Filter by payment status'],
                            'payment_method' => ['type' => 'string', 'enum' => ['cash', 'gcash', 'maya', 'bank_transfer'], 'description' => 'Filter by payment method'],
                            'date'           => ['type' => 'string', 'description' => 'Only bookings with slots on this date (Y-m-d)'],
                            'search'         => ['type' => 'string', 'description' => 'Search by reference code, customer name, or phone'],
                            'per_page'       => ['type' => 'integer', 'description' => 'Results per page (default 10)'],
                        ],
                        'required'   => [],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'find_booking',
                    'description' => 'Find a specific booking by reference code (LYR-XXXXXX) or customer phone number.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Reference code or phone number fragment'],
                        ],
                        'required'   => ['query'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_availability',
                    'description' => 'Get the court availability schedule matrix for a given date. Shows which slots are free, booked, or blocked.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'date' => ['type' => 'string', 'description' => 'Date in Y-m-d format, e.g. 2026-10-01'],
                        ],
                        'required'   => ['date'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'update_booking_status',
                    'description' => 'Change a booking status to confirmed, cancelled, or completed. Use this when the admin says "confirm booking LYR-XXXXXX" or "cancel this booking".',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'reference_code' => ['type' => 'string', 'description' => 'Booking reference code, e.g. LYR-A1B2C3'],
                            'status'         => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'cancelled', 'completed']],
                            'admin_notes'    => ['type' => 'string', 'description' => 'Optional staff notes'],
                        ],
                        'required'   => ['reference_code', 'status'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dispatch a function call by name and return the result array.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function dispatch(string $name, array $args): array
    {
        return match ($name) {
            'get_dashboard_stats'    => $this->getDashboardStats(),
            'list_bookings'          => $this->listBookings($args),
            'find_booking'           => $this->findBooking($args),
            'get_availability'       => $this->getAvailability($args),
            'update_booking_status'  => $this->updateBookingStatus($args),
            default                  => ['error' => "Unknown function: {$name}"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function getDashboardStats(): array
    {
        return [
            'pending_bookings'   => Booking::where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'completed_today'    => Booking::where('status', 'completed')
                ->whereDate('updated_at', Carbon::today())->count(),
            'active_courts'      => Court::where('is_active', true)->count(),
            'total_courts'       => Court::count(),
            'today_revenue'      => (float) Booking::whereIn('status', ['confirmed', 'completed'])
                ->whereDate('updated_at', Carbon::today())->sum('total_amount'),
            'month_revenue'      => (float) Booking::whereIn('status', ['confirmed', 'completed'])
                ->whereBetween('updated_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])->sum('total_amount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listBookings(array $args): array
    {
        $query = Booking::with(['slots.court'])
            ->when($args['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($args['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($args['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($args['date'] ?? null, fn ($q, $v) => $q->whereHas('slots', fn ($s) => $s->where('date', $v)))
            ->when($args['search'] ?? null, function ($q, $term): void {
                $like = "%{$term}%";
                $q->where(fn ($sub) => $sub
                    ->where('reference_code', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like));
            })
            ->latest();

        $paginated = $query->paginate($args['per_page'] ?? 10);

        return [
            'total'    => $paginated->total(),
            'bookings' => collect($paginated->items())->map(fn (Booking $b) => [
                'reference_code'  => $b->reference_code,
                'customer_name'   => $b->customer_name,
                'customer_phone'  => $b->customer_phone,
                'status'          => $b->status,
                'payment_status'  => $b->payment_status,
                'payment_method'  => $b->payment_method,
                'total_amount'    => (float) $b->total_amount,
                'slots_summary'   => $b->slots->map(fn (BookingSlot $s) => [
                    'court' => $s->court?->name,
                    'date'  => $s->date instanceof \Carbon\Carbon ? $s->date->format('Y-m-d') : (string) $s->date,
                    'time'  => $s->formatted_time ?? "{$s->start_time}–{$s->end_time}",
                ])->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function findBooking(array $args): array
    {
        $query  = (string) ($args['query'] ?? '');
        $service = app(BookingService::class);

        $bookings = $service->findBookingsByPhone($query);

        if ($bookings->isEmpty()) {
            $single = $service->findBooking($query);
            if ($single !== null) {
                $bookings = $bookings->push($single);
            }
        }

        if ($bookings->isEmpty()) {
            return ['found' => false, 'message' => 'No booking matched that reference code or phone number.'];
        }

        return [
            'found'    => true,
            'count'    => $bookings->count(),
            'bookings' => $bookings->map(fn (Booking $b) => [
                'reference_code'  => $b->reference_code,
                'customer_name'   => $b->customer_name,
                'customer_phone'  => $b->customer_phone,
                'status'          => $b->status,
                'payment_status'  => $b->payment_status,
                'total_amount'    => (float) $b->total_amount,
                'slots'           => $b->slots->map(fn (BookingSlot $s) => [
                    'court' => $s->court?->name,
                    'date'  => $s->date instanceof \Carbon\Carbon ? $s->date->format('Y-m-d') : (string) $s->date,
                    'time'  => $s->formatted_time ?? "{$s->start_time}–{$s->end_time}",
                ])->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getAvailability(array $args): array
    {
        $matrix = app(BookingService::class)
            ->getMatrixSchedule(Carbon::parse($args['date'] ?? now()->toDateString()));

        return [
            'date'            => $matrix['date'],
            'date_formatted'  => $matrix['date_formatted'],
            'is_closed'       => $matrix['is_closed'],
            'total_available' => $matrix['total_available'],
            'courts'          => collect($matrix['courts'])->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'hourly_rate' => (float) $c->hourly_rate,
            ])->values()->all(),
            'time_slots'      => $matrix['time_slots'],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function updateBookingStatus(array $args): array
    {
        $booking = Booking::where(
            'reference_code',
            mb_strtoupper((string) ($args['reference_code'] ?? ''))
        )->first();

        if ($booking === null) {
            return ['success' => false, 'error' => "Booking {$args['reference_code']} not found."];
        }

        $updated = app(BookingService::class)->updateBookingStatus(
            $booking,
            (string) ($args['status'] ?? 'pending'),
            $args['admin_notes'] ?? null,
        );

        return [
            'success'        => true,
            'reference_code' => $updated->reference_code,
            'new_status'     => $updated->status,
            'payment_status' => $updated->payment_status,
            'message'        => "Booking {$updated->reference_code} is now {$updated->status}.",
        ];
    }
}
