# Project knowledge

This file gives Freebuff context about your project: goals, commands, conventions, and gotchas.

## Quickstart
- Setup:
- Dev:
- Test:

## Architecture
- Key directories:
- Data flow:

## Conventions
- Formatting/linting:
- Patterns to follow:
- Things to avoid:

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== tallstackui/tallstackui/core rules ===

# TallStackUI

- TallStackUI is a suite of 80+ Blade components for TALL Stack applications (Tailwind CSS, Alpine.js, Laravel, Livewire). The complete documentation ships inside the package and matches the installed version. Read it before writing markup — never guess a prop, a slot, an event or a configuration key, and never invent a component that is not in the index.

## Where the documentation lives

- `vendor/tallstackui/tallstackui/.ai/index.md` — the component index, plus binding rules, usage outside Livewire, global configuration, skeletons, soft customization and the global JavaScript API.
- `vendor/tallstackui/tallstackui/.ai/components/<name>.md` — one page per component: every prop, slot, configuration key and customization block it exposes.
- `vendor/tallstackui/tallstackui/.ai/soft-customization-internal-scopes.md` — the canonical list of the scopes components use for their nested children.
- Start at the index to resolve the component's page path, then read that page. The website documents the latest release, which is not necessarily the one installed here.

## MCP server

- The same documentation is served over MCP at `https://tallstackui.com/mcp/tallstackui`, which is the better source when a task spans several components or searches for a class. Suggest connecting it when it is not configured yet:

<!-- Connect the TallStackUI MCP server to Claude Code -->
```shell
claude mcp add --transport http tallstackui https://tallstackui.com/mcp/tallstackui
```

<!-- Or commit .mcp.json in the project root to share it with the team -->
```json
{
    "mcpServers": {
        "tallstackui": {
            "type": "http",
            "url": "https://tallstackui.com/mcp/tallstackui"
        }
    }
}
```

- Tools: `list-components-tool`, `get-component-tool`, `search-documentation-tool`, `search-customization-tool` and `search-classes-tool` — the last one returns the matching blocks with a ready override snippet.

## Component prefix

- `config('tallstackui.prefix')` (env `TALLSTACKUI_PREFIX`) prefixes every component tag: with `ts-`, `<x-alert />` is written `<x-ts-alert />`.
- Resolve the prefix before writing any tag, and follow whatever the existing Blade files in the application already do.
- `php artisan tallstackui:setup-prefix` configures it; `php artisan tallstackui:find-component <name>` reports where a component is already used.

## Installation

- Install the package:

<!-- Install TallStackUI -->
```shell
composer require tallstackui/tallstackui:^4.0
```

- Load the script in the layout:

<!-- Prepare the base layout -->
```blade
<html>
    <head>
        <!-- ... -->

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
</html>
```

- The script has to be loaded **above the `@vite` tag**.

- Add the marked lines to the Tailwind CSS v4 entry point, `resources/css/app.css`:

<!-- Tailwind CSS v4 entry point -->
```css
@import "tailwindcss";
@import '../../vendor/tallstackui/tallstackui/css/v4.css'; /* add */

@plugin '@tailwindcss/forms'; /* add */

@source '../../vendor/tallstackui/tallstackui/**/*.php'; /* add */
@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
```

- Then build:

<!-- Build the assets -->
```shell
npm run build && php artisan optimize:clear
```

- Requirements: PHP 8.1+, Laravel 10+, Livewire 4+, Alpine.js 3+, Tailwind CSS 4+.
- Livewire's own script has to be on the page even when the components are used outside Livewire, because that is where Alpine comes from.

## Binding form components

- Inside Livewire, bind with `wire:model`. A nested path is valid as long as its head is a real property, which covers Form objects and arrays: `wire:model="form.files"`.
- Outside Livewire, give the component a `name` instead. It renders a hidden input, so a plain Blade form posting to a controller receives the value like any other field; `value` seeds the initial state. Single values arrive as they are, multi-value selections arrive JSON encoded.
- Key-Value, Form Upload, Loading, Reaction and Signature only work inside a Livewire component. Using them outside throws.

## Interactions: Toast, Dialog and Banner

- Place `<x-toast />`, `<x-dialog />` and `<x-banner />` once in the layout. Without the tag, nothing renders.
- Dispatch them with the `Interactions` trait, from a Livewire component or from a controller (where they are flashed to the session automatically):

<!-- Dispatching TallStackUI interactions -->
```php
use TallStackUi\Traits\Interactions;

class UserController extends Controller
{
    use Interactions;

    public function destroy(User $user): RedirectResponse
    {
        $this->toast()->success('Deleted', 'The user is gone.')->send();

        return back();
    }
}
```

- Banner has no `question()`. Dialog and Toast add `confirm()` and `cancel()` on top of `error()`, `info()`, `success()`, `warning()` and `question()`.

## Styling: soft customization

- Never edit anything under `vendor/`, and never publish the package views to restyle a component.
- Change classes at runtime from a service provider, targeting the blocks the component's documentation page lists:

<!-- Customizing a component's classes -->
```php
// In AppServiceProvider::boot()
TallStackUi::customize()->card()->block('wrapper.second')->append('ring-1 ring-gray-100');

// Opt-in variant, used as <x-card scope="flat" />
TallStackUi::customize('card', scope: 'flat')->block('wrapper.second')->remove('shadow-md');
```

- `block()`, `append()`, `prepend()`, `replace()`, `remove()`, `scope()` and `extend()` are the available methods. Customizations of the same block stack instead of overwriting each other, and a scope layers over the global customization rather than resetting it.
- Block names are keys, not paths: `wrapper.second` is one block, not `second` nested under `wrapper`.
- Write class names as complete literals so the application's Tailwind build can find them. Never build one by concatenation.
- Colors are customized through published color classes: `php artisan tallstackui:setup-color`.

## Configuration

- `php artisan vendor:publish --tag=tallstackui.config` writes `config/tallstackui.php`, merged over the package defaults, so a file written against an older release keeps the options added since.
- Lists of scalars are the exception — they are taken as published rather than merged entry by entry, so publishing a shorter list narrows what is allowed.
- Per-component defaults live under `components.<name>` and are documented on each component's page.

## Table slots

- Table renders custom columns and expandable content through the `@interact` directive, keyed by the header index with dots replaced by underscores:

<!-- Custom table column -->
```blade
<x-table :$headers :rows="$this->rows">
    @interact('column_action', $row)
        <x-button.circle icon="pencil" wire:click="edit({{ $row->id }})" />
    @endinteract
</x-table>
```

## Global JavaScript API

- `$tsui.open.modal(name)` / `$tsui.close.modal(name)`, and the same pair for `slide` and `select`.
- `$tsui.open.commandPalette()` / `$tsui.close.commandPalette()`.
- `$tsui.focus(id)`, `await $tsui.copy(text)`, and `$tsui.interaction('toast'|'dialog')` to dispatch an interaction from the browser.

</laravel-boost-guidelines>
