<?php

namespace Tests\Feature\Admin;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The three roles from the planning doc (§17): Admin (full access, incl.
 * user management), Sales (own quotes only, no /admin/* access), Manager
 * (sees every quote, no /admin/* access).
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_cannot_reach_any_admin_catalog_screen(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)->get(route('admin.product-imports.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.pricing-rules.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_manager_cannot_reach_any_admin_catalog_screen(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('admin.product-imports.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.pricing-rules.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_reach_every_admin_catalog_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.product-imports.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.pricing-rules.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_sales_only_sees_their_own_quotes_in_the_list(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        Quote::factory()->create(['user_id' => $sales->id, 'quote_code' => 'QT-MINE0001']);
        Quote::factory()->create(['user_id' => $other->id, 'quote_code' => 'QT-OTHER001']);

        $response = $this->actingAs($sales)->get(route('quotes.index'));

        $response->assertOk()
            ->assertSee('QT-MINE0001')
            ->assertDontSee('QT-OTHER001');

        // Tampering with the user_id filter can't bypass the restriction —
        // the list is scoped server-side regardless of query params.
        $this->actingAs($sales)
            ->get(route('quotes.index', ['user_id' => $other->id]))
            ->assertOk()
            ->assertDontSee('QT-OTHER001');
    }

    public function test_admin_and_manager_see_every_quote_in_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();

        Quote::factory()->create(['user_id' => $sales->id, 'quote_code' => 'QT-SALES0001']);

        $this->actingAs($admin)->get(route('quotes.index'))->assertOk()->assertSee('QT-SALES0001');
        $this->actingAs($manager)->get(route('quotes.index'))->assertOk()->assertSee('QT-SALES0001');
    }

    public function test_sales_cannot_view_or_print_another_users_quote(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();
        $quote = Quote::factory()->create(['user_id' => $other->id]);

        $this->actingAs($sales)->get(route('quotes.show', $quote))->assertForbidden();
        $this->actingAs($sales)->get(route('quotes.pdf', $quote))->assertForbidden();

        // Their own quote is still fine.
        $ownQuote = Quote::factory()->create(['user_id' => $sales->id]);
        $this->actingAs($sales)->get(route('quotes.show', $ownQuote))->assertOk();
    }

    public function test_admin_and_manager_can_view_any_users_quote(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        $quote = Quote::factory()->create(['user_id' => $sales->id]);

        $this->actingAs($admin)->get(route('quotes.show', $quote))->assertOk();
        $this->actingAs($manager)->get(route('quotes.show', $quote))->assertOk();
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->sales()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), ['role' => User::ROLE_MANAGER])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => User::ROLE_MANAGER]);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), ['role' => User::ROLE_SALES])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => User::ROLE_ADMIN]);
    }

    public function test_non_admin_cannot_change_anyones_role(): void
    {
        $sales = User::factory()->sales()->create();
        $target = User::factory()->sales()->create();

        $this->actingAs($sales)
            ->patch(route('admin.users.update', $target), ['role' => User::ROLE_ADMIN])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => User::ROLE_SALES]);
    }

    public function test_admin_can_create_a_new_user_with_a_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nguyễn Sales Mới',
                'email' => 'sales-moi@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
                'role' => User::ROLE_MANAGER,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Nguyễn Sales Mới',
            'email' => 'sales-moi@example.com',
            'role' => User::ROLE_MANAGER,
        ]);

        $newUser = User::query()->where('email', 'sales-moi@example.com')->firstOrFail();
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertTrue(Hash::check('a-strong-password', $newUser->password));
    }

    public function test_new_user_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'trung@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Trùng Email',
                'email' => 'trung@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
                'role' => User::ROLE_SALES,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_non_admin_cannot_create_a_new_user(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)
            ->post(route('admin.users.store'), [
                'name' => 'Không được phép',
                'email' => 'khong-duoc-phep@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'khong-duoc-phep@example.com']);
    }
}
