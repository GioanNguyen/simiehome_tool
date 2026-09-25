<?php

use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductPricePreviewController;
use App\Http\Controllers\ProductSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

// No public marketing/welcome page for this internal tool — send guests
// straight to login and already-authenticated users straight to their
// dashboard, so nobody ever lands on the default Laravel starter page.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/products/search', ProductSearchController::class)->name('products.search');
    Route::get('/products/price-preview', ProductPricePreviewController::class)->name('products.price-preview');

    // Quotes: open to every authenticated role (Admin/Sales/Manager) — the
    // per-quote/per-list "own quotes only" restriction for Sales is enforced
    // inside QuoteController, not at the route level, since it depends on
    // who created each individual quote.
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes/preview', [QuoteController::class, 'preview'])->name('quotes.preview');
    Route::get('/quotes/confirm', [QuoteController::class, 'confirm'])->name('quotes.confirm');
    Route::post('/quotes/confirm', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin only: catalog/pricing/import management.
    Route::middleware('can:manage-catalog')->group(function () {
        Route::get('/admin/product-imports', [ProductImportController::class, 'index'])->name('admin.product-imports.index');
        Route::post('/admin/product-imports', [ProductImportController::class, 'store'])->name('admin.product-imports.store');

        Route::get('/admin/products', [AdminProductController::class, 'index'])->name('admin.products.index');
        Route::patch('/admin/products/{product}', [AdminProductController::class, 'update'])->name('admin.products.update');

        Route::get('/admin/pricing-rules', [PricingRuleController::class, 'index'])->name('admin.pricing-rules.index');
        Route::post('/admin/pricing-rules', [PricingRuleController::class, 'store'])->name('admin.pricing-rules.store');
        Route::patch('/admin/pricing-rules/{pricingRule}', [PricingRuleController::class, 'update'])->name('admin.pricing-rules.update');
    });

    // Admin only: create users, assign roles.
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    });
});

require __DIR__.'/auth.php';
