<?php

namespace Tests\Feature\Quotes;

use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_quote_list_page_to_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertSee('Danh sách báo giá');
    }

    public function test_it_shows_the_quote_calculator_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('quotes.create'))
            ->assertOk()
            ->assertSee('Tính giá bộ nội thất');
    }

    public function test_preview_without_any_product_or_fee_bounces_back_with_an_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.preview'), [])
            ->assertRedirect(route('quotes.create'))
            ->assertSessionHasErrors('main');
    }

    public function test_confirm_without_a_preview_first_redirects_back_to_the_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('quotes.confirm'))
            ->assertRedirect(route('quotes.create'))
            ->assertSessionHasErrors('main');
    }

    public function test_it_previews_then_confirms_then_stores_a_quote_with_a_main_item_any_number_of_addons_and_fees(): void
    {
        $user = User::factory()->create();

        $table = Product::factory()->create([
            'sku' => 'BAN-140',
            'name' => 'Ban 1m4',
            'regular_price' => 4_000_000,
            'sale_price' => null,
        ]);

        $chairA = Product::factory()->create(['sku' => 'GHE-A', 'regular_price' => 1_000_000, 'sale_price' => null]);
        $chairB = Product::factory()->create(['sku' => 'GHE-B', 'regular_price' => 500_000, 'sale_price' => null]);
        $mirror = Product::factory()->create(['sku' => 'GUONG-A', 'regular_price' => 200_000, 'sale_price' => null]);

        $payload = [
            'main' => ['product_id' => $table->id, 'quantity' => 1, 'length_cm' => 140],
            'addons' => [
                ['product_id' => $chairA->id, 'quantity' => 4],
                ['product_id' => $chairB->id, 'quantity' => 2],
                ['product_id' => $mirror->id, 'quantity' => 1],
            ],
            'fees' => [
                ['label' => 'Phí ship', 'amount' => 300_000],
                ['label' => 'Phí lắp đặt', 'amount' => 200_000],
            ],
            'notes' => 'Giao trước thứ 6',
        ];

        $session = $this->actingAs($user);

        $session->post(route('quotes.preview'), $payload)
            ->assertRedirect(route('quotes.confirm'));

        // 4_000_000 + (1_000_000*4) + (500_000*2) + 200_000 = 9_200_000 subtotal
        $session->get(route('quotes.confirm'))
            ->assertOk()
            ->assertSee('Ban 1m4')
            ->assertSee('9.200.000')
            ->assertSee('9.700.000');

        $response = $session->post(route('quotes.store'), [
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0909123456',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('quotes', [
            'user_id' => $user->id,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0909123456',
            'subtotal' => 9_200_000.00,
            'fee_total' => 500_000.00,
            'grand_total' => 9_700_000.00,
            'notes' => 'Giao trước thứ 6',
        ]);

        $this->assertDatabaseHas('quote_items', [
            'role' => 'main',
            'sku' => 'BAN-140',
            'quantity' => 1,
            'length_cm' => 140,
            'line_total' => 4_000_000.00,
        ]);

        $this->assertDatabaseHas('quote_items', [
            'role' => 'addon',
            'sku' => 'GHE-A',
            'quantity' => 4,
            'line_total' => 4_000_000.00,
        ]);

        $this->assertDatabaseHas('quote_items', [
            'role' => 'addon',
            'sku' => 'GUONG-A',
            'quantity' => 1,
            'line_total' => 200_000.00,
        ]);

        $this->assertDatabaseHas('quote_items', [
            'role' => 'fee',
            'product_name' => 'Phí ship',
            'line_total' => 300_000.00,
        ]);

        // main + 3 addons + 2 fees
        $this->assertDatabaseCount('quote_items', 6);

        // The session draft is cleared once the quote is stored, so a
        // second confirm without a fresh preview bounces back again.
        $session->get(route('quotes.confirm'))->assertRedirect(route('quotes.create'));
    }

    public function test_storing_a_quote_requires_customer_name_and_phone(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create(['sku' => 'BAN-140', 'regular_price' => 4_000_000, 'sale_price' => null]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => ['product_id' => $table->id, 'quantity' => 1],
        ])->assertRedirect(route('quotes.confirm'));

        $session->post(route('quotes.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['customer_name', 'customer_phone']);

        $session->post(route('quotes.store'), ['customer_name' => 'Chỉ có tên'])
            ->assertSessionHasErrors('customer_phone')
            ->assertSessionDoesntHaveErrors('customer_name');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_it_saves_a_manually_overridden_main_item_price(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create(['sku' => 'BAN-140', 'regular_price' => 4_000_000, 'sale_price' => null]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => [
                'product_id' => $table->id,
                'quantity' => 1,
                'unit_price_override' => 3_200_000,
            ],
        ])->assertRedirect(route('quotes.confirm'));

        $session->post(route('quotes.store'), [
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0909123456',
        ])->assertRedirect();

        $this->assertDatabaseHas('quote_items', [
            'role' => 'main',
            'sku' => 'BAN-140',
            'unit_price' => 3_200_000.00,
            'line_total' => 3_200_000.00,
            'formula_type' => 'manual_override',
        ]);

        $this->assertDatabaseHas('quotes', ['subtotal' => 3_200_000.00]);
    }

    public function test_it_allows_a_quote_with_only_addons_and_no_main_product(): void
    {
        $user = User::factory()->create();
        $chair = Product::factory()->create(['sku' => 'GHE-A', 'regular_price' => 1_000_000, 'sale_price' => null]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'addons' => [
                ['product_id' => $chair->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('quotes.confirm'));

        $session->post(route('quotes.store'), [
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0909123456',
        ])->assertRedirect();

        $this->assertDatabaseHas('quotes', [
            'user_id' => $user->id,
            'subtotal' => 2_000_000.00,
        ]);

        $this->assertDatabaseMissing('quote_items', ['role' => 'main']);
    }

    public function test_going_back_to_the_form_prefills_the_previously_entered_selection(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create(['sku' => 'BAN-140', 'name' => 'Ban 1m4', 'regular_price' => 4_000_000, 'sale_price' => null]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => ['product_id' => $table->id, 'quantity' => 2, 'length_cm' => 150],
            'notes' => 'Ghi chú test',
        ])->assertRedirect(route('quotes.confirm'));

        $response = $session->get(route('quotes.create'))
            ->assertOk()
            ->assertSee('Ban 1m4');

        // The prefill payload is embedded as an escaped JSON blob wrapped in
        // JSON.parse('...') (Blade's @js() directive), so the raw HTML
        // contains the doubly-escaped unicode form rather than the literal
        // accented text.
        $this->assertStringContainsString('Ghi ch\\\\u00fa test', $response->getContent());

        // Regression: the custom size must survive the round trip even
        // though this product prices via the default catalog_price formula
        // (which doesn't need length_cm for its own math).
        $this->assertStringContainsString('\\u0022lengthCm\\u0022:150', $response->getContent());
    }

    public function test_it_shows_the_pdf_for_a_saved_quote(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create(['sku' => 'BAN-140', 'name' => 'Ban 1m4', 'regular_price' => 4_000_000, 'sale_price' => null]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => ['product_id' => $table->id, 'quantity' => 1],
        ]);
        $session->post(route('quotes.store'), ['customer_name' => 'Trần Thị B', 'customer_phone' => '0911222333']);

        $quote = Quote::query()->firstOrFail();

        $response = $session->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_product_image_is_saved_on_the_quote_item_and_shown_on_the_confirm_and_detail_screens(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create([
            'sku' => 'BAN-140',
            'name' => 'Ban 1m4',
            'regular_price' => 4_000_000,
            'sale_price' => null,
            'image_url' => 'https://example.com/images/ban-1m4.jpg',
        ]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => ['product_id' => $table->id, 'quantity' => 1],
        ])->assertRedirect(route('quotes.confirm'));

        $session->get(route('quotes.confirm'))
            ->assertOk()
            ->assertSee('https://example.com/images/ban-1m4.jpg', false);

        $session->post(route('quotes.store'), ['customer_name' => 'Trần Thị B', 'customer_phone' => '0911222333'])->assertRedirect();

        $this->assertDatabaseHas('quote_items', [
            'role' => 'main',
            'sku' => 'BAN-140',
            'image_url' => 'https://example.com/images/ban-1m4.jpg',
        ]);

        $quote = Quote::query()->firstOrFail();

        $session->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('https://example.com/images/ban-1m4.jpg', false);
    }

    public function test_the_pdf_still_renders_when_a_product_image_url_is_unreachable(): void
    {
        $user = User::factory()->create();
        $table = Product::factory()->create([
            'sku' => 'BAN-140',
            'name' => 'Ban 1m4',
            'regular_price' => 4_000_000,
            'sale_price' => null,
            // Reserved/non-routable-in-tests domain: the PDF must still
            // render successfully even when this fetch fails or times out —
            // a broken/slow image host must never break the whole PDF.
            'image_url' => 'https://img.invalid.test/ban-1m4.jpg',
        ]);

        $session = $this->actingAs($user);
        $session->post(route('quotes.preview'), [
            'main' => ['product_id' => $table->id, 'quantity' => 1],
        ]);
        $session->post(route('quotes.store'), ['customer_name' => 'Trần Thị B', 'customer_phone' => '0911222333']);

        $quote = Quote::query()->firstOrFail();

        $response = $session->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_quote_list_can_be_searched_and_paginated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $table = Product::factory()->create(['regular_price' => 1_000_000, 'sale_price' => null]);

        foreach (range(1, 3) as $i) {
            Quote::factory()->create([
                'user_id' => $user->id,
                'customer_name' => 'Nguyễn Văn A'.$i,
                'customer_phone' => '090000000'.$i,
            ]);
        }

        Quote::factory()->create([
            'user_id' => $other->id,
            'customer_name' => 'Khách khác',
            'customer_phone' => '0888888888',
        ]);

        $response = $this->actingAs($user)
            ->get(route('quotes.index', ['customer' => 'Nguyễn Văn A', 'per_page' => 10]));

        $response->assertOk();
        $response->assertSee('Nguyễn Văn A1');
        $response->assertDontSee('Khách khác');
    }
}
