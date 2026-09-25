<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function backdate(Quote $quote, Carbon $when): void
    {
        $quote->created_at = $when;
        $quote->save();
    }

    public function test_admin_sees_quote_counts_and_totals_across_every_user(): void
    {
        $admin = User::factory()->admin()->create();
        $salesA = User::factory()->sales()->create();
        $salesB = User::factory()->sales()->create();

        // Two quotes today (different users), one earlier this month, one
        // last year — only the first two should count toward "today", the
        // first three toward "month", and the year total should include
        // everything from this year but not last year's.
        $today1 = Quote::factory()->create(['user_id' => $salesA->id, 'grand_total' => 1_000_000]);
        $today2 = Quote::factory()->create(['user_id' => $salesB->id, 'grand_total' => 2_000_000]);
        $earlierThisMonth = Quote::factory()->create(['user_id' => $salesA->id, 'grand_total' => 500_000]);
        $this->backdate($earlierThisMonth, now()->startOfMonth()->addHours(2));
        $lastYear = Quote::factory()->create(['user_id' => $salesB->id, 'grand_total' => 900_000]);
        $this->backdate($lastYear, now()->subYear());

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['today']['count']);
        $this->assertSame(3_000_000.0, $stats['today']['total']);

        $this->assertSame(3, $stats['month']['count']);
        $this->assertSame(3_500_000.0, $stats['month']['total']);

        $this->assertSame(3, $stats['year']['count']);
        $this->assertSame(3_500_000.0, $stats['year']['total']);
    }

    public function test_sales_only_sees_their_own_quote_counts(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        Quote::factory()->create(['user_id' => $sales->id, 'grand_total' => 1_000_000]);
        Quote::factory()->create(['user_id' => $other->id, 'grand_total' => 5_000_000]);

        $response = $this->actingAs($sales)->get(route('dashboard'));
        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['today']['count']);
        $this->assertSame(1_000_000.0, $stats['today']['total']);
    }

    public function test_sales_does_not_see_the_team_activity_table(): void
    {
        $sales = User::factory()->sales()->create();

        $response = $this->actingAs($sales)->get(route('dashboard'));

        $response->assertOk();
        $this->assertFalse($response->viewData('canViewAll'));
        $response->assertDontSee('Hoạt động nhân viên sale');
    }

    public function test_admin_sees_the_team_activity_table_with_correct_per_person_numbers(): void
    {
        $admin = User::factory()->admin()->create();
        $salesA = User::factory()->sales()->create(['name' => 'Nguyễn Văn A']);
        $salesB = User::factory()->sales()->create(['name' => 'Trần Thị B']);

        Quote::factory()->create(['user_id' => $salesA->id, 'grand_total' => 1_000_000]);
        Quote::factory()->create(['user_id' => $salesA->id, 'grand_total' => 2_000_000]);
        Quote::factory()->create(['user_id' => $salesB->id, 'grand_total' => 500_000]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertSee('Hoạt động nhân viên sale');

        $activity = $response->viewData('salesActivity')->keyBy('id');

        $this->assertSame(2, $activity[$salesA->id]->quotes_month_count);
        $this->assertSame(3_000_000.0, (float) $activity[$salesA->id]->quotes_month_total);
        $this->assertSame(1, $activity[$salesB->id]->quotes_month_count);
        $this->assertSame(500_000.0, (float) $activity[$salesB->id]->quotes_month_total);
    }

    public function test_manager_can_also_view_the_dashboard_with_full_visibility(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        Quote::factory()->create(['user_id' => $sales->id, 'grand_total' => 1_000_000]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $this->assertTrue($response->viewData('canViewAll'));
        $this->assertSame(1, $response->viewData('stats')['today']['count']);
    }

    public function test_a_quote_from_an_admin_or_manager_does_not_appear_in_the_sales_activity_table(): void
    {
        $admin = User::factory()->admin()->create();
        Quote::factory()->create(['user_id' => $admin->id, 'grand_total' => 1_000_000]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $activity = $response->viewData('salesActivity');
        $this->assertFalse($activity->contains('id', $admin->id));
    }
}
