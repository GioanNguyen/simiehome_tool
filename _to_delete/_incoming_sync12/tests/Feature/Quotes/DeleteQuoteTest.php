<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_quote(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($admin)
            ->delete(route('quotes.destroy', $quote))
            ->assertRedirect(route('quotes.index'));

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
    }

    public function test_deleting_a_quote_also_deletes_its_line_items(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = Quote::factory()->create();
        $item = $quote->items()->create([
            'role' => 'main',
            'product_name' => 'Bàn Basic',
            'unit_price' => 1_000_000,
            'line_total' => 1_000_000,
        ]);

        $this->actingAs($admin)->delete(route('quotes.destroy', $quote));

        $this->assertDatabaseMissing('quote_items', ['id' => $item->id]);
    }

    public function test_deleting_a_quote_shows_a_confirmation_message(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = Quote::factory()->create(['quote_code' => 'QT-DELETE01']);

        $response = $this->actingAs($admin)->delete(route('quotes.destroy', $quote));

        $response->assertRedirect(route('quotes.index'));
        $this->assertSame('Đã xoá báo giá QT-DELETE01.', session('status'));
    }

    public function test_manager_cannot_delete_a_quote(): void
    {
        $manager = User::factory()->manager()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($manager)
            ->delete(route('quotes.destroy', $quote))
            ->assertForbidden();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    public function test_sales_cannot_delete_a_quote_even_their_own(): void
    {
        $sales = User::factory()->sales()->create();
        $quote = Quote::factory()->create(['user_id' => $sales->id]);

        $this->actingAs($sales)
            ->delete(route('quotes.destroy', $quote))
            ->assertForbidden();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    public function test_the_delete_button_only_shows_for_admin_on_the_quote_list(): void
    {
        // The "Xem" link and the delete form's action share the same
        // /quotes/{id} URL (only the HTTP verb differs), so look for the
        // method-spoofing input the delete form renders instead — that's
        // unique to the delete form itself.
        Quote::factory()->create(['quote_code' => 'QT-VISIBLE1']);
        $methodSpoof = 'name="_method" value="DELETE"';

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('quotes.index'))->assertSee($methodSpoof, false);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('quotes.index'))->assertDontSee($methodSpoof, false);
    }

    public function test_the_delete_button_only_shows_for_admin_on_the_quote_detail_page(): void
    {
        $quote = Quote::factory()->create();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('quotes.show', $quote))->assertSee('Xoá báo giá');

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('quotes.show', $quote))->assertDontSee('Xoá báo giá');
    }
}
