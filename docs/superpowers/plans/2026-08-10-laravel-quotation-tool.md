# Laravel Quotation Tool Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an internal Laravel quotation tool that syncs WooCommerce products into Laravel, supports fast quote entry with autocomplete, calculates dining set pricing, and stores quote history.

**Architecture:** A server-rendered Laravel 12 app using Blade and Alpine.js, with pricing logic isolated in a small domain service and WooCommerce synchronization handled by scheduled jobs. Product data is stored locally in Laravel so quoting remains fast and available even if WordPress is temporarily unavailable.

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Alpine.js, MySQL for production, SQLite for local/testing where useful, Laravel Scheduler, Laravel Queue, Pest/PHPUnit, Vite

## Global Constraints

- Use Laravel 12 and keep the UI server-rendered with Blade plus Alpine.js.
- Keep the MVP internal-only behind authentication.
- Sync WooCommerce product data into Laravel on a schedule instead of querying WordPress during quote entry.
- Implement the confirmed formula exactly: `table_total = (table_base_price * table_size_cm / 100) + table_sku_surcharge`, `chair_total = chair_base_price * quantity`, `set_total = table_total + chair_type_1_total + chair_type_2_total + manual_extra_fee`.
- Keep the implementation minimal and extensible; do not add public quoting, complex RBAC, PDF generation, CRM, or two-way sync.

---

## File Structure

- Create: `composer.json`, `artisan`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `vite.config.js`, `package.json`
- Create: `app/Domain/Pricing/QuoteCalculator.php`
- Create: `app/Domain/Pricing/QuoteCalculationResult.php`
- Create: `app/Http/Controllers/QuoteController.php`
- Create: `app/Http/Controllers/ProductSearchController.php`
- Create: `app/Http/Controllers/Admin/SyncRunController.php`
- Create: `app/Http/Controllers/Admin/PricingRuleController.php`
- Create: `app/Jobs/SyncWooCommerceProductsJob.php`
- Create: `app/Services/WooCommerce/WooCommerceClient.php`
- Create: `app/Models/Product.php`
- Create: `app/Models/ProductPricingRule.php`
- Create: `app/Models/Quote.php`
- Create: `app/Models/QuoteItem.php`
- Create: `app/Models/SyncRun.php`
- Create: `database/migrations/*_create_products_table.php`
- Create: `database/migrations/*_create_product_pricing_rules_table.php`
- Create: `database/migrations/*_create_quotes_table.php`
- Create: `database/migrations/*_create_quote_items_table.php`
- Create: `database/migrations/*_create_sync_runs_table.php`
- Create: `resources/views/quotes/index.blade.php`
- Create: `resources/views/quotes/show.blade.php`
- Create: `resources/views/admin/sync-runs/index.blade.php`
- Create: `resources/views/admin/pricing-rules/index.blade.php`
- Create: `resources/js/quote-calculator.js`
- Create: `tests/Feature/Auth/LoginTest.php`
- Create: `tests/Feature/Products/ProductSearchTest.php`
- Create: `tests/Feature/Quotes/CreateQuoteTest.php`
- Create: `tests/Feature/Admin/RunWooCommerceSyncTest.php`
- Create: `tests/Unit/Domain/Pricing/QuoteCalculatorTest.php`

### Task 1: Bootstrap Laravel Application

**Files:**
- Create: Laravel skeleton files in the repo root
- Modify: `.env.example`, `package.json`, `composer.json`
- Test: `tests/Feature/ExampleTest.php` or framework smoke tests

**Interfaces:**
- Consumes: empty repository
- Produces: working Laravel app with auth scaffolding, Vite assets, and test runner

- [ ] **Step 1: Scaffold the Laravel project**

```bash
composer create-project laravel/laravel . "^12.0"
```

- [ ] **Step 2: Add authentication scaffolding**

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
```

- [ ] **Step 3: Configure the app for local development**

Update `.env.example` and `.env` to include:

```dotenv
APP_NAME="Quotation Tool"
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

- [ ] **Step 4: Run the baseline migrations**

Run: `php artisan migrate`
Expected: database tables for users, sessions, jobs, and cache are created successfully

- [ ] **Step 5: Verify the scaffold works**

Run: `php artisan test`
Expected: baseline Laravel tests pass

- [ ] **Step 6: Commit**

```bash
git add .
git commit -m "chore: bootstrap laravel quotation app"
```

### Task 2: Add Data Model and Pricing Domain

**Files:**
- Create: `app/Domain/Pricing/QuoteCalculator.php`
- Create: `app/Domain/Pricing/QuoteCalculationResult.php`
- Create: `app/Models/Product.php`
- Create: `app/Models/ProductPricingRule.php`
- Create: `app/Models/Quote.php`
- Create: `app/Models/QuoteItem.php`
- Create: `app/Models/SyncRun.php`
- Create: `database/migrations/*`
- Test: `tests/Unit/Domain/Pricing/QuoteCalculatorTest.php`

**Interfaces:**
- Consumes: Laravel base app
- Produces:
  - `QuoteCalculator::calculate(array $payload): QuoteCalculationResult`
  - Eloquent models for `Product`, `ProductPricingRule`, `Quote`, `QuoteItem`, `SyncRun`

- [ ] **Step 1: Write the failing pricing tests**

```php
it('calculates a table total using size and surcharge', function () {
    $calculator = new QuoteCalculator();

    $result = $calculator->calculate([
        'table_base_price' => 4_000_000,
        'table_size_cm' => 140,
        'table_surcharge' => 500_000,
        'chair_1_base_price' => 0,
        'chair_1_quantity' => 0,
        'chair_2_base_price' => 0,
        'chair_2_quantity' => 0,
        'manual_extra_fee' => 0,
    ]);

    expect($result->tableTotal)->toBe(6_100_000.0);
    expect($result->grandTotal)->toBe(6_100_000.0);
});

it('adds chair totals and manual fees into the grand total', function () {
    $calculator = new QuoteCalculator();

    $result = $calculator->calculate([
        'table_base_price' => 4_000_000,
        'table_size_cm' => 140,
        'table_surcharge' => 500_000,
        'chair_1_base_price' => 1_000_000,
        'chair_1_quantity' => 4,
        'chair_2_base_price' => 500_000,
        'chair_2_quantity' => 2,
        'manual_extra_fee' => 300_000,
    ]);

    expect($result->grandTotal)->toBe(11_400_000.0);
});
```

- [ ] **Step 2: Run the unit test to verify it fails**

Run: `php artisan test tests/Unit/Domain/Pricing/QuoteCalculatorTest.php`
Expected: FAIL because the calculator classes do not exist yet

- [ ] **Step 3: Create the migrations and models**

Create tables for products, pricing rules, quotes, quote items, and sync runs with fields matching the spec, including:

```php
$table->string('sku')->index();
$table->decimal('base_price', 15, 2)->nullable();
$table->json('attributes_json')->nullable();
```

- [ ] **Step 4: Implement the minimal pricing domain**

```php
final class QuoteCalculator
{
    public function calculate(array $payload): QuoteCalculationResult
    {
        $tableTotal = (($payload['table_base_price'] ?? 0) * ($payload['table_size_cm'] ?? 0) / 100)
            + ($payload['table_surcharge'] ?? 0);

        $chair1Total = ($payload['chair_1_base_price'] ?? 0) * ($payload['chair_1_quantity'] ?? 0);
        $chair2Total = ($payload['chair_2_base_price'] ?? 0) * ($payload['chair_2_quantity'] ?? 0);
        $manualExtraFee = $payload['manual_extra_fee'] ?? 0;

        return new QuoteCalculationResult(
            tableTotal: $tableTotal,
            chair1Total: $chair1Total,
            chair2Total: $chair2Total,
            manualExtraFee: $manualExtraFee,
            grandTotal: $tableTotal + $chair1Total + $chair2Total + $manualExtraFee,
        );
    }
}
```

- [ ] **Step 5: Run the unit tests and migrations**

Run: `php artisan migrate:fresh --env=testing && php artisan test tests/Unit/Domain/Pricing/QuoteCalculatorTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Domain app/Models database/migrations tests/Unit
git commit -m "feat: add pricing domain and quotation data model"
```

### Task 3: Implement WooCommerce Sync

**Files:**
- Create: `app/Services/WooCommerce/WooCommerceClient.php`
- Create: `app/Jobs/SyncWooCommerceProductsJob.php`
- Modify: `config/services.php`, `routes/console.php`, `app/Console/Kernel.php` if needed by app version
- Test: `tests/Feature/Admin/RunWooCommerceSyncTest.php`

**Interfaces:**
- Consumes:
  - `WooCommerceClient::fetchProducts(int $page = 1): array`
  - `Product` and `SyncRun` models
- Produces:
  - `SyncWooCommerceProductsJob::handle(WooCommerceClient $client): void`

- [ ] **Step 1: Write the failing sync test**

```php
it('syncs woocommerce products into the local catalog', function () {
    Http::fake([
        'https://example.com/wp-json/wc/v3/products*' => Http::response([
            ['id' => 10, 'sku' => 'BAN-140', 'name' => 'Ban 1m4', 'price' => '4000000', 'status' => 'publish', 'attributes' => []],
        ], 200),
    ]);

    $this->post(route('admin.sync-runs.store'))->assertRedirect();

    $this->assertDatabaseHas('products', [
        'wordpress_product_id' => 10,
        'sku' => 'BAN-140',
        'name' => 'Ban 1m4',
    ]);
});
```

- [ ] **Step 2: Run the sync test to verify it fails**

Run: `php artisan test tests/Feature/Admin/RunWooCommerceSyncTest.php`
Expected: FAIL because the route, job, and client do not exist yet

- [ ] **Step 3: Implement the WooCommerce client and sync job**

Core behavior:

```php
public function fetchProducts(int $page = 1): array
{
    return Http::withBasicAuth(config('services.woocommerce.key'), config('services.woocommerce.secret'))
        ->get(config('services.woocommerce.base_url').'/wp-json/wc/v3/products', [
            'per_page' => 100,
            'page' => $page,
        ])
        ->throw()
        ->json();
}
```

- [ ] **Step 4: Add sync route and scheduler entry**

Expose:

```php
Route::post('/admin/sync-runs', [SyncRunController::class, 'store'])->name('admin.sync-runs.store');
```

Schedule:

```php
Schedule::job(new SyncWooCommerceProductsJob())->hourly();
```

- [ ] **Step 5: Run the feature test**

Run: `php artisan test tests/Feature/Admin/RunWooCommerceSyncTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Jobs app/Services config/services.php routes tests/Feature/Admin
git commit -m "feat: sync woocommerce products into laravel"
```

### Task 4: Build Product Search and Quote Saving

**Files:**
- Create: `app/Http/Controllers/ProductSearchController.php`
- Create: `app/Http/Controllers/QuoteController.php`
- Create: `tests/Feature/Products/ProductSearchTest.php`
- Create: `tests/Feature/Quotes/CreateQuoteTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes:
  - `ProductSearchController::__invoke(Request $request): JsonResponse`
  - `QuoteCalculator::calculate(array $payload): QuoteCalculationResult`
- Produces:
  - `QuoteController::store(StoreQuoteRequest $request): RedirectResponse`
  - `/products/search` JSON endpoint

- [ ] **Step 1: Write the failing product search test**

```php
it('returns matching products by name or sku', function () {
    Product::factory()->create(['sku' => 'BAN-140', 'name' => 'Ban 1m4']);
    Product::factory()->create(['sku' => 'GHE-A', 'name' => 'Ghe A']);

    $response = $this->getJson(route('products.search', ['q' => 'BAN']));

    $response->assertOk()
        ->assertJsonFragment(['sku' => 'BAN-140'])
        ->assertJsonMissing(['sku' => 'GHE-A']);
});
```

- [ ] **Step 2: Write the failing create quote test**

```php
it('stores a quote snapshot with calculated totals', function () {
    $table = Product::factory()->create(['sku' => 'BAN-140', 'name' => 'Ban 1m4', 'base_price' => 4000000]);
    $chair = Product::factory()->create(['sku' => 'GHE-A', 'name' => 'Ghe A', 'base_price' => 1000000]);
    ProductPricingRule::factory()->create(['product_id' => $table->id, 'sku' => 'BAN-140', 'surcharge_amount' => 500000]);

    $this->actingAs(User::factory()->create())
        ->post(route('quotes.store'), [
            'table_product_id' => $table->id,
            'table_size_cm' => 140,
            'chair_1_product_id' => $chair->id,
            'chair_1_quantity' => 4,
            'manual_extra_fee' => 300000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('quotes', [
        'table_sku' => 'BAN-140',
        'grand_total' => 10_400_000.00,
    ]);
});
```

- [ ] **Step 3: Run both tests to verify they fail**

Run: `php artisan test tests/Feature/Products/ProductSearchTest.php tests/Feature/Quotes/CreateQuoteTest.php`
Expected: FAIL because controllers, routes, factories, and persistence do not exist yet

- [ ] **Step 4: Implement the search endpoint and quote persistence**

Use:

```php
Product::query()
    ->where('name', 'like', "%{$query}%")
    ->orWhere('sku', 'like', "%{$query}%")
    ->limit(10)
    ->get(['id', 'sku', 'name', 'base_price', 'product_type']);
```

And save a quote snapshot using the calculator result and `quote_items` rows for chair lines and manual fee.

- [ ] **Step 5: Run the feature tests**

Run: `php artisan test tests/Feature/Products/ProductSearchTest.php tests/Feature/Quotes/CreateQuoteTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http routes/web.php tests/Feature
git commit -m "feat: add product search and quote persistence"
```

### Task 5: Build the Quote Calculator UI

**Files:**
- Create: `resources/views/quotes/index.blade.php`
- Create: `resources/views/quotes/show.blade.php`
- Create: `resources/js/quote-calculator.js`
- Modify: `resources/js/app.js`, `resources/css/app.css`, `routes/web.php`
- Test: `tests/Feature/Quotes/CreateQuoteTest.php`

**Interfaces:**
- Consumes:
  - `/products/search?q=...`
  - `quotes.store`
- Produces:
  - authenticated quote calculator page at `quotes.index`

- [ ] **Step 1: Write the failing UI feature test**

```php
it('shows the quote calculator page to authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('quotes.index'))
        ->assertOk()
        ->assertSee('Tinh gia bo ban an');
});
```

- [ ] **Step 2: Run the UI test to verify it fails**

Run: `php artisan test tests/Feature/Quotes/CreateQuoteTest.php --filter=shows`
Expected: FAIL because the page and route do not exist yet

- [ ] **Step 3: Implement the Blade page and Alpine calculator**

The page must include:

```html
<input name="table_query" />
<input name="table_size_cm" type="number" />
<input name="chair_1_query" />
<input name="chair_1_quantity" type="number" />
<input name="chair_2_query" />
<input name="chair_2_quantity" type="number" />
<input name="manual_extra_fee" type="number" />
```

The Alpine component must:

- fetch search suggestions with debounce
- store selected product ids
- compute preview totals in the browser using the same formula
- submit the final form to the server

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`
Expected: Vite build succeeds

- [ ] **Step 5: Run the relevant tests**

Run: `php artisan test tests/Feature/Quotes/CreateQuoteTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources routes/web.php tests/Feature/Quotes
git commit -m "feat: add internal quotation calculator ui"
```

### Task 6: Add Admin Views for Sync Runs and Pricing Rules

**Files:**
- Create: `app/Http/Controllers/Admin/SyncRunController.php`
- Create: `app/Http/Controllers/Admin/PricingRuleController.php`
- Create: `resources/views/admin/sync-runs/index.blade.php`
- Create: `resources/views/admin/pricing-rules/index.blade.php`
- Test: `tests/Feature/Admin/RunWooCommerceSyncTest.php`

**Interfaces:**
- Consumes:
  - `SyncRun` model
  - `ProductPricingRule` model
- Produces:
  - `admin.sync-runs.index`
  - `admin.pricing-rules.index`

- [ ] **Step 1: Write the failing admin page tests**

```php
it('shows sync runs to authenticated users', function () {
    SyncRun::factory()->create(['status' => 'success']);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.sync-runs.index'))
        ->assertOk()
        ->assertSee('Sync runs');
});
```

- [ ] **Step 2: Run the admin page tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/RunWooCommerceSyncTest.php`
Expected: FAIL because the admin pages do not exist yet

- [ ] **Step 3: Implement the admin pages**

Minimum behavior:

- list recent sync runs
- provide a manual sync button
- list pricing rules by SKU
- allow updating `surcharge_amount` and `active`

- [ ] **Step 4: Run the admin feature tests**

Run: `php artisan test tests/Feature/Admin/RunWooCommerceSyncTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin resources/views/admin tests/Feature/Admin
git commit -m "feat: add admin sync and pricing rule screens"
```

### Task 7: Final Verification and Documentation

**Files:**
- Modify: `README.md`
- Modify: `.env.example`
- Test: full test suite and build

**Interfaces:**
- Consumes: completed application
- Produces: verified setup instructions and passing checks

- [ ] **Step 1: Document local setup**

Add to `README.md`:

```md
## Setup

1. composer install
2. cp .env.example .env
3. php artisan key:generate
4. touch database/database.sqlite
5. php artisan migrate
6. npm install
7. npm run build
8. php artisan serve
```

- [ ] **Step 2: Run the full verification commands**

Run: `php artisan test && npm run build`
Expected: all tests pass and assets build successfully

- [ ] **Step 3: Review the plan against the spec**

Confirm:

- auth is enforced
- WooCommerce sync is scheduled and manually triggerable
- calculator matches the confirmed formula
- quotes are stored with snapshot fields
- history/admin screens exist

- [ ] **Step 4: Commit**

```bash
git add README.md .env.example
git commit -m "docs: add setup and verification notes"
```
