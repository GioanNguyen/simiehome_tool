<?php

namespace Tests\Feature\Admin;

use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_pricing_rules_to_authenticated_users(): void
    {
        PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'length_ratio',
            'surcharge_amount' => 500_000,
            'active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.pricing-rules.index'))
            ->assertOk()
            ->assertSee('Bàn - Tables')
            ->assertSee('length_ratio');
    }

    public function test_it_creates_a_category_level_rule(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.pricing-rules.store'), [
                'category' => 'Ghế - Chairs',
                'formula_type' => 'quantity',
                'surcharge_amount' => 0,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertDatabaseHas('pricing_rules', [
            'category' => 'Ghế - Chairs',
            'product_id' => null,
            'formula_type' => 'quantity',
        ]);
    }

    public function test_it_creates_a_product_level_override_rule(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.pricing-rules.store'), [
                'product_id' => $product->id,
                'formula_type' => 'fixed',
                'surcharge_amount' => 0,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertDatabaseHas('pricing_rules', [
            'product_id' => $product->id,
            'category' => null,
            'formula_type' => 'fixed',
        ]);
    }

    public function test_it_updates_an_existing_rule(): void
    {
        $rule = PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'length_ratio',
            'surcharge_amount' => 500_000,
            'active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.pricing-rules.update', $rule), [
                'formula_type' => 'length_ratio',
                'surcharge_amount' => 750_000,
                'active' => false,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertDatabaseHas('pricing_rules', [
            'id' => $rule->id,
            'surcharge_amount' => 750_000.00,
            'active' => 0,
        ]);
    }

    public function test_it_creates_a_custom_formula_rule_with_a_valid_expression(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.pricing-rules.store'), [
                'category' => 'Bàn - Tables',
                'formula_type' => 'custom_formula',
                'surcharge_amount' => 0,
                'expression' => 'base_price + length_cm * 5000',
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $rule = PricingRule::query()->where('category', 'Bàn - Tables')->firstOrFail();

        $this->assertSame('custom_formula', $rule->formula_type);
        $this->assertSame('base_price + length_cm * 5000', $rule->formula_config['expression']);
    }

    public function test_it_rejects_a_custom_formula_rule_with_an_invalid_expression(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.pricing-rules.store'), [
                'category' => 'Bàn - Tables',
                'formula_type' => 'custom_formula',
                'surcharge_amount' => 0,
                'expression' => 'width_cm * 2',
            ])
            ->assertSessionHasErrors('expression');

        $this->assertDatabaseCount('pricing_rules', 0);
    }

    public function test_it_rejects_a_custom_formula_rule_with_no_expression(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.pricing-rules.store'), [
                'category' => 'Bàn - Tables',
                'formula_type' => 'custom_formula',
                'surcharge_amount' => 0,
            ])
            ->assertSessionHasErrors('expression');

        $this->assertDatabaseCount('pricing_rules', 0);
    }

    public function test_it_updates_a_rules_expression_when_editing_a_custom_formula_rule(): void
    {
        $rule = PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price'],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.pricing-rules.update', $rule), [
                'formula_type' => 'custom_formula',
                'surcharge_amount' => 0,
                'expression' => 'base_price + length_cm * 6000',
                'active' => true,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertSame(
            'base_price + length_cm * 6000',
            $rule->fresh()->formula_config['expression'],
        );
    }

    public function test_switching_a_rule_away_from_custom_formula_clears_its_expression(): void
    {
        $rule = PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'custom_formula',
            'formula_config' => ['expression' => 'base_price'],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.pricing-rules.update', $rule), [
                'formula_type' => 'catalog_price',
                'surcharge_amount' => 0,
                'active' => true,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertNull($rule->fresh()->formula_config);
    }

    public function test_editing_an_unrelated_field_does_not_wipe_formula_config_for_other_formula_types(): void
    {
        $rule = PricingRule::query()->create([
            'category' => 'Bàn - Tables',
            'formula_type' => 'fixed',
            'formula_config' => ['fixed_price' => 700_000],
            'surcharge_amount' => 0,
            'active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.pricing-rules.update', $rule), [
                'formula_type' => 'fixed',
                'surcharge_amount' => 50_000,
                'active' => true,
            ])
            ->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertSame(700_000, $rule->fresh()->formula_config['fixed_price']);
    }
}
