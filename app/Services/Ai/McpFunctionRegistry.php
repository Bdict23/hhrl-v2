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
                'type'     => 'function',
                'function' => [
                    'name'        => 'list_bookings',
                    'description' => <<<'DESC'
ALWAYS call this tool — NEVER guess — when the admin wants to browse or filter multiple bookings:
• "show me all pending bookings", "list unpaid GCash bookings"
• "bookings for today / for October 1", "show cancelled bookings"
• "search bookings for Juan dela Cruz / 09171234567"
• "how many bookings are paid vs unpaid?", "ipakita ang mga pending booking"
• Any listing, count, or filter question about bookings

All parameters are optional and combinable. Default page size is 10.

PARAMETERS:
  status         (string enum) — filter by lifecycle status:
      "pending"   = awaiting payment from customer
      "confirmed" = payment verified, booking is active
      "cancelled" = booking was declined or cancelled
      "completed" = court session has ended
  payment_status (string enum) — filter by payment state:
      "unpaid"  = no payment yet
      "paid"    = payment confirmed
      "refunded" = payment was refunded (usually after cancellation)
  payment_method (string enum) — how the customer pays:
      "cash" | "gcash" | "maya" | "bank_transfer"
  date           (string "Y-m-d") — only bookings with at least one slot on this date
      Example: "2026-10-01"
  search         (string) — free-text across:
      reference_code (e.g. LYR-A1B2C3), customer_name, customer_phone
  per_page       (integer, default 10, max 100) — results per page

Returns a JSON object:
  total    (integer) — total matching records across all pages
  showing  (integer) — number of records returned in this response
  bookings (array)  — list of booking objects, each with:
    reference_code  (string, e.g. "LYR-A1B2C3")
    customer_name   (string)
    customer_phone  (string)
    status          (string: pending|confirmed|cancelled|completed)
    payment_status  (string: unpaid|paid|refunded)
    payment_method  (string: cash|gcash|maya|bank_transfer)
    total_amount    (float, PHP ₱)
    slots_summary   (array of {court, date Y-m-d, time string})
    created_at      (string "Y-m-d H:i")

FORMAT: Present as a table or bullet list. Always show total count upfront.
DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'status' => [
                                'type'        => 'string',
                                'enum'        => ['pending', 'confirmed', 'cancelled', 'completed'],
                                'description' => 'Booking lifecycle status. pending=awaiting payment; confirmed=active/paid; cancelled=declined; completed=done.',
                            ],
                            'payment_status' => [
                                'type'        => 'string',
                                'enum'        => ['unpaid', 'paid', 'refunded'],
                                'description' => 'Payment state of the booking.',
                            ],
                            'payment_method' => [
                                'type'        => 'string',
                                'enum'        => ['cash', 'gcash', 'maya', 'bank_transfer'],
                                'description' => 'Customer payment method.',
                            ],
                            'date' => [
                                'type'        => 'string',
                                'description' => 'Return only bookings with at least one court slot on this date (Y-m-d). Example: "2026-10-01".',
                            ],
                            'search' => [
                                'type'        => 'string',
                                'description' => 'Full-text search on reference_code (LYR-XXXXXX), customer_name, or customer_phone.',
                            ],
                            'per_page' => [
                                'type'        => 'integer',
                                'description' => 'Number of results per page. Default 10, maximum 100.',
                                'default'     => 10,
                                'minimum'     => 1,
                                'maximum'     => 100,
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
                'type'     => 'function',
                'function' => [
                    'name'        => 'find_booking',
                    'description' => <<<'DESC'
ALWAYS call this tool — NEVER guess — when the admin wants to look up a SPECIFIC booking:
• "find booking LYR-A1B2C3", "lookup reference code LYR-XXXXXX"
• "search for the booking of 09171234567"
• "what is the status of LYR-XYZ123?", "hanapi ang booking ni 0917-xxx-xxxx"
• "check booking [reference code]", any single-booking lookup

Use this instead of list_bookings when a specific reference code or phone is given.

Reference code format: LYR- followed by exactly 6 alphanumeric characters (e.g. LYR-A1B2C3).
Phone searches may return multiple bookings for the same customer.

PARAMETER (required):
  query (string) — a reference code (e.g. "LYR-A1B2C3", case-insensitive)
                   OR a phone number / fragment (e.g. "09171234567", "1234")

Returns a JSON object:
  found    (boolean) — whether any matching booking was found
  count    (integer) — number of matched bookings
  message  (string)  — present directly to admin if found=false
  bookings (array, when found=true) — each object contains:
    reference_code  (string)
    customer_name   (string)
    customer_phone  (string)
    customer_email  (string)
    status          (string: pending|confirmed|cancelled|completed)
    payment_status  (string: unpaid|paid|refunded)
    payment_method  (string)
    total_amount    (float, PHP ₱)
    subtotal_amount (float, PHP ₱)
    notes           (string|null) — customer notes
    admin_notes     (string|null) — staff internal notes
    created_at      (string "Y-m-d H:i")
    slots (array of {court, date Y-m-d, time string, slot_price float PHP ₱})

If found=false, inform the admin and suggest double-checking the reference code format.
FORMAT: Display total_amount as ₱X,XXX.00.
DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Booking reference code (e.g. "LYR-A1B2C3") or customer phone number (e.g. "09171234567"). Case-insensitive for reference codes.',
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
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_availability',
                    'description' => <<<'DESC'
ALWAYS call this tool — NEVER guess — when the admin asks about court availability or schedules:
• "available courts today / tomorrow / on October 5"
• "is Court 1 free at 3 PM?", "show the schedule for Saturday"
• "how many slots are still open today?", "unsay available courts karon"
• "show me the availability matrix", "check schedule for [any date]"
• "anong libre na time slot sa [date]?"
• "bakante ang court 5 karun 4pm?", "naay bakante sa court X?", "available ba ang court"

PARAMETER (required):
  date (string "Y-m-d") — the date to check. Example: "2026-10-01".
       Always infer from context; use today's date if the admin does not specify.

Returns a JSON object:
  date            (string "Y-m-d") — the queried date
  date_formatted  (string) — human-readable, e.g. "Thursday, October 1, 2026"
  is_closed       (boolean) — if true, the facility is CLOSED that day; inform admin and stop
  total_available (integer) — total number of available slot×court combinations
  courts (array) — active courts, each with:
      id           (integer)
      name         (string)
      hourly_rate  (float, PHP ₱ per hour)
      surface_type (string|null)
      is_indoor    (boolean|null)
  time_slots (array) — hourly slots for that day, each with:
      key          (string "HH:00", e.g. "08:00")
      label        (string, e.g. "8:00 AM - 9:00 AM")
      start_label  (string)
      end_label    (string)
      hour         (integer)
      is_next_day  (boolean) — true if slot crosses midnight
  slot_states (array) — per-time summary of each court's state:
      time         (string, e.g. "8:00 AM - 9:00 AM")
      courts (array of {court_id, court_name, state, state_label}):
        state values: "available" | "pending" (awaiting payment) |
                      "booked" (confirmed) | "blocked" (maintenance/private event) |
                      "open_play"

FORMAT: Present as a grid/table per court. Show hourly_rate as ₱X,XXX.00/hr.
        If is_closed=true, tell the admin the venue is closed that day.
DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'date' => [
                                'type'        => 'string',
                                'description' => 'Date to check availability for (Y-m-d). Example: "2026-10-01". Defaults to today if omitted.',
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
                'type'     => 'function',
                'function' => [
                    'name'        => 'update_booking_status',
                    'description' => <<<'DESC'
ONLY call this tool when the admin EXPLICITLY instructs a status change on a specific booking.
Valid explicit triggers (reference code must be present):
• "confirm booking LYR-XXXXXX" → status = "confirmed"
• "approve LYR-XXXXXX"         → status = "confirmed"
• "cancel booking LYR-XXXXXX"  → status = "cancelled"
• "decline LYR-XXXXXX"         → status = "cancelled"
• "mark LYR-XXXXXX as completed" → status = "completed"
• "reset LYR-XXXXXX to pending"  → status = "pending"

IMPORTANT RULES:
1. NEVER call this without an explicit reference_code AND target status from the admin.
2. If the admin only ASKS about a booking (not changing it), use find_booking instead.
3. Side effects handled automatically by the system:
   - "confirmed" → payment_status becomes "paid"
   - "completed" → payment_status becomes "paid"
   - "cancelled" → payment_status becomes "refunded"
   - "pending"   → payment_status unchanged

PARAMETERS:
  reference_code (string, required) — booking reference in format LYR-XXXXXX.
      Case-insensitive; automatically normalised to uppercase.
      Pattern: LYR- followed by exactly 6 alphanumeric characters.
  status         (string enum, required) — new status to apply:
      "pending"   — reset to awaiting payment (rare, admin discretion)
      "confirmed" — approve the booking (marks payment as paid)
      "cancelled" — cancel/decline the booking (marks payment as refunded)
      "completed" — mark the court session as done (marks payment as paid)
  admin_notes    (string, optional) — internal staff note attached to the booking.
      Visible only to admins. Recommend max ~500 characters.

Returns a JSON object:
  success        (boolean) — true if the update succeeded
  error          (string)  — present to admin if success=false
  reference_code (string)  — the booking that was updated
  customer_name  (string)  — the customer's name
  new_status     (string)  — status after the update
  payment_status (string)  — payment status after the update
  message        (string)  — human-readable confirmation; present this verbatim to the admin

On success: confirm in a friendly Bisaya-English tone.
On failure: show the error and suggest verifying the reference code.
DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'reference_code' => [
                                'type'        => 'string',
                                'description' => 'Booking reference code in format LYR-XXXXXX (6 alphanumeric chars). Example: "LYR-A1B2C3". Case-insensitive.',
                                'pattern'     => '^[Ll][Yy][Rr]-[A-Za-z0-9]{6}$',
                                'example'     => 'LYR-A1B2C3',
                            ],
                            'status' => [
                                'type'        => 'string',
                                'enum'        => ['pending', 'confirmed', 'cancelled', 'completed'],
                                'description' => 'New lifecycle status. confirmed/completed → payment=paid. cancelled → payment=refunded.',
                            ],
                            'admin_notes' => [
                                'type'        => 'string',
                                'description' => 'Optional internal staff note. Not visible to the customer. Max ~500 chars.',
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
