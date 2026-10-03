# Project Knowledge

## What this is
A **Laravel 13** hospitality/operations management app (built on the TallStackUI starter kit) for **LYR Pickle Club**. It combines a **pickleball court booking** system with **restaurant, events, inventory, and transactions/accounting** modules — all in one admin panel.

Stack: PHP ^8.2, Laravel 13, **Livewire 4 + Volt**, TallStackUI 4 (component library), Laravel Reverb (broadcast), Sanctum (auth), Tailwind CSS 4 / Vite 6, barryvdh/laravel-dompdf (receipts), simplesoftwareio/simple-qrcode, and Laravel `ai` + `mcp` for the AI assistant.

## Key directories
- `app/Livewire/` – classic Livewire class components (Users, Profile, Home/Dashboard, Inventory/Cardex, shared Traits/Alert).
- `resources/views/components/**/⚡*.blade.php` – **Volt single-file Livewire components** (the vast majority of the UI). Routes are registered with `Volt::route(...)` in `routes/web.php`.
- `app/Services/` – domain logic, grouped by module:
  - `PickleCourt/BookingService.php` (booking + availability matrix + pricing)
  - `Ai/` → `AiService.php` (OpenAI-compatible chat) + `McpFunctionRegistry.php` (AI tool defs & dispatch)
  - `DataManagement/`, `Event/`, `Inventory/`, `Transaction/`
- `app/Models/` – core `Booking`, `BookingSlot`, `Court` (table `pickle_courts`), `OperatingHour`, `PricingRule`, `SlotOverride`; plus subfolders `Accounting/`, `Ai/`, `BanquetEvent/`, `Business/`, `DataManagement/`, `Inventory/`, `Restaurant/`, `Settings/`, `Transaction/`, `Validation/`.
- `app/Mcp/Servers/lyrpickleball.php` – Laravel MCP `Server` subclass (currently a stub: empty `$tools/$resources/$prompts`; real tool logic lives in `McpFunctionRegistry`).
- `routes/` – `web.php` (all Volt + REST routes), `ai.php` (MCP wiring), `auth.php`, `channels.php`, `console.php`, `api.php`.
- `config/` – `ai.php` (Groq/OpenAI-compatible), `reverb.php`, `sanctum.php`, `session.php`, `custom.php` (`proof_of_payment_url`).
- `database/migrations/` – ~30 migrations (users, bookings, courts, pricing, inventory, transactions/accounting, events, notifications).
- `tests/` – Pest unit + feature tests (`Pest.php`, `TestCase.php`).

## Commands
- Install PHP: `composer install` (PHP ^8.2)
- Install JS: `npm install` (Node per `.nvmrc` = 25)
- Dev (all-in-one): `composer dev` → concurrently runs `php artisan serve`, `queue:listen`, `npm run dev`
- Build assets: `npm run build`
- Test: `composer test` → `./vendor/bin/pest --parallel`
- Lint/format: `composer format` → `./vendor/bin/pint` (preset `psr12`)
- Static analysis: `composer analyse` → `./vendor/bin/phpstan analyse` (level 5, paths = `app/`, Larastan + Carbon extensions)
- CI gate: `composer ci` → `pint --test` + `phpstan analyse` + `pest --parallel`

## Architecture highlights
- **Volt-first UI**: almost every screen is a single-file `⚡*.blade.php` component under `resources/views/components/<module>/`, wired in `routes/web.php`. There is no traditional `app/Http/Controllers` CRUD layer for these — they live in the Volt components + `app/Services`.
- **Modules**: Pickle Court (dashboard, courts, bookings, operating hours, pricing rules, carousel, receipts), Restaurant (recipes), Events (booking, liquidation, budget), Inventory (purchase orders, receiving, withdrawal, backorder, fixed asset, cardex), Transactions (advances for liquidation, employees advances, acknowledgement receipts, petty cash vouchers, cash returns, reimbursements, revolving fund), Settings/Data Management, Users/Profile.
- **AI assistant**: `AiService` is an **OpenAI-compatible, non-streaming** chat client (default backend **Groq** via `config/ai.php`, env `GROQ_*`, default model `llama-3.3-70b-versatile`). It detects `tool_calls`, and the Volt component `ai.ai-assistant-page` loops tool calls through `McpFunctionRegistry::dispatch` (≤3 iterations). `$onChunk` is called once with the full content.
- **MCP**: `routes/ai.php` registers both an HTTP endpoint `/mcp/lyrpickleball` (`Mcp::web`) and a local server `lyrpickleball` (`Mcp::local`). The server class is a scaffold — the actual tool implementations and their (anti-hallucination) descriptions live in `App\Services\Ai\McpFunctionRegistry`.
- **Booking domain**: `Booking` (reference_code `LYR-XXXXXX`; statuses `pending|confirmed|cancelled|completed`; payment statuses `unpaid|paid|refunded`; payment methods `cash|gcash|maya|bank_transfer`) has many `BookingSlot`s, each linked to a `Court` (table `pickle_courts`). `BookingService::getMatrixSchedule` builds a date×court availability grid honoring `OperatingHour`, `SlotOverride`, and dynamic `PricingRule` (multiplier/flat), with lock-for-update concurrency safety at checkout.
- **Locale/tone**: business timezone is **Asia/Manila**; admin UI copy is Bisaya-English code-switched ("Kumusta", "pila", "unsay", "naa", "dili") and monetary values are displayed as `₱X,XXX.00`.

## Conventions & gotchas
- Every PHP file starts with `declare(strict_types=1)`; code style is **PSR-12** (Pint preset `psr12`); PHPStan level 5 must pass.
- **Volt `⚡` naming**: single-file components are named with a `⚡` prefix (e.g. `⚡ai-assistant-page.blade.php`) and referenced by dot-path (`ai.ai-assistant-page`). TallStackUI components use the `ts-` prefix (`<x-ts-*>`).
- `McpFunctionRegistry` returns **plain JSON-encodable arrays** (never Eloquent models), keeps tool `description`s exhaustive to prevent hallucination, and `dispatch()` is a `match` returning a curated error payload for unknown functions.
- Stateful/filterable list queries use `->when($args['...'] ?? null, fn ($q, $v) => ...)` closures; money is cast via `decimal:2` / `(float)`.
- Test env (`phpunit.xml`): `APP_ENV=testing`, array cache/session, `QUEUE_CONNECTION=sync`, array mail. `DB_CONNECTION`/`DB_DATABASE` lines are **commented out** — tests run against the configured default (sqlite), not `:memory:`.
- `.env` defaults: `DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `BROADCAST_CONNECTION=log`.
- `composer.lock` is committed — avoid `composer update` unless intentional.
- **`app/Mcp`, `routes/ai.php`, and the `stubs/` + `.agents/` scaffolding are new/untracked** relative to the main history (the AI/MCP feature is recent).
- There is a stray `count()`/`get()` oddity at repo root (likely accidental files) worth ignoring.
- Merge gate: `pint --test`, `phpstan analyse`, and `pest --parallel` must all pass.
