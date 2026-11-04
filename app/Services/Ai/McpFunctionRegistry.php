<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Court;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\PickleCourt\BookingService;

/**
 * Maps AI function-call names to actual MCP tool logic.
 *
 * ─── ANTI-HALLUCINATION POLICY ─────────────────────────────────────────────
 * Every tool description explicitly tells the model:
 *  1. WHEN to call it (trigger phrases / question types)
 *  2. WHAT it returns (field names, types, units)
 *  3. WHAT the model must NEVER do instead (e.g. never guess/fabricate)
 * ────────────────────────────────────────────────────────────────────────────
 *
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

    // ─────────────────────────────────────────────────────────────────────────
    //  Tool Definitions exposed to the LLM
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * OpenAI-compatible tool definitions exposed to the AI model.
     *
     * IMPORTANT: The "description" field is the primary mechanism for
     * preventing hallucination. Keep descriptions exhaustive and precise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [

            // ─────────────────────────────────────────────────────────────────
            // 1. get_dashboard_stats  — TODAY's live KPI snapshot
            // ─────────────────────────────────────────────────────────────────
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_dashboard_stats',
                    'description' => <<<'DESC'
ALWAYS call this tool — NEVER guess or fabricate — when the admin asks ANY of:
• "dashboard stats", "KPIs", "overview", "summary today", "kumusta ang business today"
• "how many bookings today", "pila ka pending karon", "confirmed today"
• "revenue today", "how much did we earn today", "pila ang kita today"
• "how many courts are available/vacant right now", "libre ba ang courts karon"
• "paano ang business today", any question about TODAY's numbers

Returns a JSON object with these exact fields:
  pending_bookings   (integer) — bookings with status=pending updated today
  confirmed_bookings (integer) — bookings with status=confirmed updated today
  completed_today    (integer) — bookings with status=completed updated today
  vacant_courts      (integer) — active courts with NO booking slots today
  total_courts       (integer) — total courts in the system
  today_revenue      (float, PHP ₱) — revenue from confirmed+completed bookings updated today
  month_revenue      (float, PHP ₱) — revenue from confirmed+completed bookings this calendar month

FORMAT: Display monetary values as ₱X,XXX.00. Always cite "as of today" in your response.
DESC,
                    'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
                ],
            ],

            // ─────────────────────────────────────────────────────────────────
            // 2. get_dashboard_stats_month  — MONTHLY KPI snapshot
            // ─────────────────────────────────────────────────────────────────
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_dashboard_stats_month',
                    'description' => <<<'DESC'
ALWAYS call this tool — NEVER guess — when the admin asks about a SPECIFIC MONTH's performance:
• "stats for September", "revenue last month", "how was August?"
• "monthly summary", "this month's bookings overview"
• "pila ang revenue sa September 2026", "kumusta ang buwan?"
• Comparing months, or any historical monthly data question

If no month is specified but the question is about the CURRENT month's full overview,
call this tool with NO arguments to default to the current calendar month.

PARAMETER:
  date (optional, string "YYYY-MM"): Target month, e.g. "2026-09" for September 2026.
       Omit to use the CURRENT calendar month.

Returns a JSON object with these exact fields:
  target_month        (string) — human name e.g. "September 2026"
  target_month_short  (string) — short form e.g. "Sep 2026"
  pending_bookings    (integer) — pending bookings created in that month
  confirmed_bookings  (integer) — confirmed bookings created in that month
  completed_bookings  (integer) — completed bookings created in that month
  cancelled_bookings  (integer) — cancelled bookings created in that month
  total_bookings      (integer) — all bookings across all statuses for that month
  active_courts       (integer) — currently active courts (not month-filtered)
  total_courts        (integer) — total courts in system (not month-filtered)
  month_revenue       (float, PHP ₱) — revenue (confirmed+completed) for the target month

FORMAT: Display monetary values as ₱X,XXX.00. Always mention the month name clearly.
DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'date' => [
                                'type'        => 'string',
                                'description' => 'Target month in YYYY-MM format. Example: "2026-09" for September 2026. Omit to use the current month.',
                                'pattern'     => '^\d{4}-(0[1-9]|1[0-2])$',
                                'example'     => '2026-09',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────────────────────
            // 3. list_bookings  — paginated booking list with filters
            // ─────────────────────────────────────────────────────────────────
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_bookings',
                    'description' => <<<'DESC'
                ALWAYS call this tool when browsing, counting, or filtering multiple bookings (e.g., by status, payment, date, search term, or in Cebuano).
                Parameters: status, payment_status, payment_method, date (Y-m-d), search, per_page (default 10).
                Returns total, showing count, and an array of booking summaries. Format as a table or bullet list, showing total count upfront.
                DESC,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'confirmed', 'cancelled', 'completed'],
                                'description' => 'Filter by booking lifecycle status.',
                            ],
                            'payment_status' => [
                                'type' => 'string',
                                'enum' => ['unpaid', 'paid', 'refunded'],
                                'description' => 'Filter by payment state.',
                            ],
                            'payment_method' => [
                                'type' => 'string',
                                'enum' => ['cash', 'gcash', 'maya', 'bank_transfer'],
                                'description' => 'Filter by customer payment method.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Return bookings with slots on this date (Y-m-d).',
                            ],
                            'search' => [
                                'type' => 'string',
                                'description' => 'Search across reference_code, customer_name, or customer_phone.',
                            ],
                            'per_page' => [
                                'type' => 'integer',
                                'description' => 'Results per page (default 10, max 100).',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────────────────────
            // 4. find_booking  — look up ONE specific booking or customer
            // ─────────────────────────────────────────────────────────────────
            [
                'type' => 'function',
                'function' => [
                    'name' => 'find_booking',
                    'description' => <<<'DESC'
            ALWAYS call this tool when searching for a specific booking by reference code (e.g., LYR-A1B2C3) or phone number (English or Cebuano/Tagalog).
            Parameter: query (string, reference code or phone number/fragment).
            Returns booking details, status, payment info, and booked slots. Display amounts as ₱X,XXX.00.
            DESC,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Booking reference code (e.g., "LYR-A1B2C3") or customer phone number.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────────────────────
            // 5. get_availability  — court schedule matrix for a date
            // ─────────────────────────────────────────────────────────────────
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_availability',
                    'description' => <<<'DESC'
ALWAYS call this tool for court availability/schedules (e.g., today, specific dates, times, or in Cebuano/Tagalog).
Parameter: date (string "Y-m-d", defaults to today).
Returns venue status, courts list, time slots, and slot states (available, pending, booked, blocked, open_play). Display as a grid with ₱X,XXX.00/hr rates.
DESC,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date' => [
                                'type' => 'string',
                                'description' => 'Date to check (Y-m-d). Defaults to today.',
                            ],
                        ],
                        'required' => ['date'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────────────────────
            // 6. update_booking_status  — confirm / cancel / complete a booking
            // ─────────────────────────────────────────────────────────────────
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_booking_status',
                    'description' => <<<'DESC'
            ONLY call when the admin explicitly instructs a status change for a booking (e.g., "confirm", "cancel", "approve", "decline", or in Bisaya). Requires reference code and target status.
            Side effects: confirmed/completed sets payment to paid; cancelled sets payment to refunded.
            Parameters: reference_code (required, LYR-XXXXXX), status (required enum), admin_notes (optional).
            Returns update result, new status, and a confirmation message.
            DESC,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reference_code' => [
                                'type' => 'string',
                                'description' => 'Booking reference code in format LYR-XXXXXX (6 alphanumeric chars). Case-insensitive.',
                                'pattern' => '^[Ll][Yy][Rr]-[A-Za-z0-9]{6}$',
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'confirmed', 'cancelled', 'completed'],
                                'description' => 'New lifecycle status.',
                            ],
                            'admin_notes' => [
                                'type' => 'string',
                                'description' => 'Optional internal staff note (max ~500 chars).',
                            ],
                        ],
                        'required' => ['reference_code', 'status'],
                    ],
                ],
            ],

        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dispatcher
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch a function call by name and return the result array.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function dispatch(string $name, array $args): array
    {
        return match ($name) {
            'get_dashboard_stats'       => $this->getDashboardStats(),
            'get_dashboard_stats_month' => $this->getDashboardStatsForMonth($args),
            'list_bookings'             => $this->listBookings($args),
            'find_booking'              => $this->findBooking($args),
            'get_availability'          => $this->getAvailability($args),
            'update_booking_status'     => $this->updateBookingStatus($args),
            default                     => [
                'error'               => "Unknown function: {$name}.",
                'available_functions' => [
                    'get_dashboard_stats',
                    'get_dashboard_stats_month',
                    'list_bookings',
                    'find_booking',
                    'get_availability',
                    'update_booking_status',
                ],
            ],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Tool Implementations
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function getDashboardStats(): array
    {
        $today = Carbon::today('Asia/Manila');
        $now   = Carbon::now('Asia/Manila');

        return [
            'pending_bookings'   => Booking::where('status', 'pending')
                ->whereDate('updated_at', $today)->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')
                ->whereDate('updated_at', $today)->count(),
            'completed_today'    => Booking::where('status', 'completed')
                ->whereDate('updated_at', $today)->count(),
            'vacant_courts'      => Court::where('is_active', true)
                ->whereDoesntHave('bookingSlots', function ($q) use ($today) {
                    $q->whereDate('date', $today);
                })->count(),
            'total_courts'       => Court::count(),
            'today_revenue'      => (float) Booking::whereIn('status', ['confirmed', 'completed'])
                ->whereDate('updated_at', $today)->sum('total_amount'),
            'month_revenue'      => (float) Booking::whereIn('status', ['confirmed', 'completed'])
                ->whereBetween('updated_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ])->sum('total_amount'),
            '_meta' => [
                'as_of_date'     => $today->toDateString(),
                'as_of_datetime' => $now->toDateTimeString(),
                'currency'       => 'PHP (Philippine Peso)',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function getDashboardStatsForMonth(array $args): array
    {
        if (! empty($args['date'])) {
            // Accept "YYYY-MM" or any parseable date string
            $date = Carbon::parse($args['date'] . '-01', 'Asia/Manila');
        } else {
            $date = Carbon::now('Asia/Manila')->startOfMonth();
        }

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd   = $date->copy()->endOfMonth();

        $applyDateFilter = fn ($query, string $column = 'created_at') =>
        $query->whereBetween($column, [$monthStart, $monthEnd]);

        return [
            'target_month'       => $date->format('F Y'),
            'target_month_short' => $date->format('M Y'),

            'pending_bookings'   => $applyDateFilter(Booking::where('status', 'pending'))->count(),
            'confirmed_bookings' => $applyDateFilter(Booking::where('status', 'confirmed'))->count(),
            'completed_bookings' => $applyDateFilter(Booking::where('status', 'completed'))->count(),
            'cancelled_bookings' => $applyDateFilter(Booking::where('status', 'cancelled'))->count(),
            'total_bookings'     => $applyDateFilter(Booking::query())->count(),

            'active_courts'      => Court::where('is_active', true)->count(),
            'total_courts'       => Court::count(),

            'month_revenue'      => (float) $applyDateFilter(
                Booking::whereIn('status', ['confirmed', 'completed']),
                'updated_at'
            )->sum('total_amount'),

            '_meta' => [
                'month_start' => $monthStart->toDateString(),
                'month_end'   => $monthEnd->toDateString(),
                'currency'    => 'PHP (Philippine Peso)',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listBookings(array $args): array
    {
        $query = Booking::with(['slots.court'])
            ->when($args['status']         ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($args['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($args['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($args['date']           ?? null, fn ($q, $v) => $q->whereHas('slots', fn ($s) => $s->where('date', $v)))
            ->when($args['search']         ?? null, function ($q, $term): void {
                $like = "%{$term}%";
                $q->where(fn ($sub) => $sub
                    ->where('reference_code', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like));
            })
            ->latest();

        $perPage   = (int) ($args['per_page'] ?? 10);
        $paginated = $query->paginate(max(1, min($perPage, 100)));

        return [
            'total'        => $paginated->total(),
            'showing'      => $paginated->count(),
            'current_page' => $paginated->currentPage(),
            'bookings'     => collect($paginated->items())->map(fn (Booking $b) => [
                'reference_code'  => $b->reference_code,
                'customer_name'   => $b->customer_name,
                'customer_phone'  => $b->customer_phone,
                'status'          => $b->status,
                'payment_status'  => $b->payment_status,
                'payment_method'  => $b->payment_method,
                'total_amount'    => (float) $b->total_amount,
                'slots_summary'   => $b->slots->map(fn (BookingSlot $s) => [
                    'court' => $s->court?->name,
                    'date'  => $s->date instanceof Carbon ? $s->date->format('Y-m-d') : (string) $s->date,
                    'time'  => $s->formatted_time ?? "{$s->start_time}–{$s->end_time}",
                ])->all(),
                'created_at'      => $b->created_at?->format('Y-m-d H:i'),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function findBooking(array $args): array
    {
        $query   = (string) ($args['query'] ?? '');
        $service = app(BookingService::class);

        // Try phone first (may return multiple bookings for the same customer)
        $bookings = $service->findBookingsByPhone($query);

        // Fall back to reference-code lookup
        if ($bookings->isEmpty()) {
            $single = $service->findBooking($query);
            if ($single !== null) {
                $bookings = $bookings->push($single);
            }
        }

        if ($bookings->isEmpty()) {
            return [
                'found'   => false,
                'count'   => 0,
                'message' => "No booking found matching \"{$query}\". Please double-check the reference code (format: LYR-XXXXXX) or phone number.",
            ];
        }

        return [
            'found'    => true,
            'count'    => $bookings->count(),
            'bookings' => $bookings->map(fn (Booking $b) => [
                'reference_code'  => $b->reference_code,
                'customer_name'   => $b->customer_name,
                'customer_phone'  => $b->customer_phone,
                'customer_email'  => $b->customer_email,
                'status'          => $b->status,
                'payment_status'  => $b->payment_status,
                'payment_method'  => $b->payment_method,
                'total_amount'    => (float) $b->total_amount,
                'subtotal_amount' => (float) $b->subtotal_amount,
                'notes'           => $b->notes,
                'admin_notes'     => $b->admin_notes,
                'created_at'      => $b->created_at?->format('Y-m-d H:i'),
                'slots'           => $b->slots->map(fn (BookingSlot $s) => [
                    'court'      => $s->court?->name,
                    'date'       => $s->date instanceof Carbon ? $s->date->format('Y-m-d') : (string) $s->date,
                    'time'       => $s->formatted_time ?? "{$s->start_time}–{$s->end_time}",
                    'slot_price' => (float) $s->price,
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
        $dateString = $args['date'] ?? Carbon::now('Asia/Manila')->toDateString();
        $matrix     = app(BookingService::class)
            ->getMatrixSchedule(Carbon::parse($dateString, 'Asia/Manila'));

        return [
            'date'            => $matrix['date'],
            'date_formatted'  => $matrix['date_formatted'],
            'is_closed'       => $matrix['is_closed'],
            'total_available' => $matrix['total_available'],
            'courts'          => collect($matrix['courts'])->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'hourly_rate'  => (float) $c->hourly_rate,
                'surface_type' => $c->surface_type ?? null,
                'is_indoor'    => $c->is_indoor ?? null,
            ])->values()->all(),
            'time_slots'      => $matrix['time_slots'],
            // Compact per-slot state breakdown for each court
            'slot_states'     => $this->summariseSlotStates($matrix),
        ];
    }

    /**
     * Build a compact slot-state summary from the matrix cells.
     * Helps the LLM reason about per-time availability without parsing the full cells array.
     *
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function summariseSlotStates(array $matrix): array
    {
        $summary = [];

        foreach ($matrix['time_slots'] ?? [] as $slot) {
            $key     = $slot['key'];
            $label   = $slot['label'];
            $courts  = $matrix['cells'][$key] ?? [];
            $statuses = [];

            foreach ($courts as $courtId => $cell) {
                $statuses[] = [
                    'court_id'    => $courtId,
                    'court_name'  => $cell['court_name'] ?? $cell['name'] ?? "Court #{$courtId}",
                    'state'       => $cell['state'],
                    'state_label' => $cell['state_label'] ?? $cell['state'],
                ];
            }

            $summary[] = [
                'time'   => $label,
                'courts' => $statuses,
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function updateBookingStatus(array $args): array
    {
        $refCode = mb_strtoupper(trim((string) ($args['reference_code'] ?? '')));

        if (empty($refCode)) {
            return [
                'success' => false,
                'error'   => 'reference_code is required. Please provide the booking code (format: LYR-XXXXXX).',
            ];
        }

        $booking = Booking::where('reference_code', $refCode)->first();

        if ($booking === null) {
            return [
                'success' => false,
                'error'   => "Booking {$refCode} was not found in the system. Please double-check the reference code.",
            ];
        }

        $newStatus = (string) ($args['status'] ?? '');
        if (! in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
            return [
                'success' => false,
                'error'   => "Invalid status \"{$newStatus}\". Must be one of: pending, confirmed, cancelled, completed.",
            ];
        }

        $updated = app(BookingService::class)->updateBookingStatus(
            $booking,
            $newStatus,
            $args['admin_notes'] ?? null,
        );

        return [
            'success'        => true,
            'reference_code' => $updated->reference_code,
            'customer_name'  => $updated->customer_name,
            'new_status'     => $updated->status,
            'payment_status' => $updated->payment_status,
            'message'        => "Booking {$updated->reference_code} ({$updated->customer_name}) is now [{$updated->status}]. Payment status: {$updated->payment_status}.",
        ];
    }
}
