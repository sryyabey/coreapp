<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\SupportActivityChart;
use App\Filament\Widgets\SupportQueue;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreNotification;
use App\Models\StoreProduct;
use App\Models\SupportAgentApp;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    private function operator(array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user, 'web');

        return $user;
    }

    private function ticket(App $app, array $attributes = []): SupportTicket
    {
        return SupportTicket::factory()->create([
            'app_id' => $app->id, 'user_id' => null, 'external_user_id' => (string) Str::uuid(),
            'external_user_name' => 'Dış kullanıcı', ...$attributes,
        ]);
    }

    private function stats(): array
    {
        $stats = (new ReflectionMethod(OverviewStats::class, 'getStats'))->invoke(app(OverviewStats::class));

        return collect($stats)->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()])->all();
    }

    public function test_manage_home_uses_custom_dashboard_and_empty_permission_state(): void
    {
        $this->operator();
        $this->get('/manage')->assertOk()->assertSee('Genel Bakış')->assertDontSee('Filament documentation');
        $this->assertSame([OverviewStats::class, QuickActions::class, SupportActivityChart::class, SupportQueue::class], app(Dashboard::class)->getWidgets());
        $this->assertFalse(OverviewStats::canView());
        $this->assertFalse(SupportQueue::canView());
        $this->assertFalse(SupportActivityChart::canView());
        Livewire::test(QuickActions::class)->assertSee('erişim yetkisi verildiğinde')->assertDontSee('Satın alımlar');
    }

    public function test_statistics_only_include_permitted_sections_and_active_memberships(): void
    {
        App::factory()->count(2)->create();
        App::factory()->create(['is_active' => false]);
        $this->operator(['ViewAny:App']);
        $this->assertSame(['Aktif uygulamalar' => 2], $this->stats());
        Livewire::test(OverviewStats::class)->assertSee('Aktif uygulamalar')->assertDontSee('Canlı abonelikler');
        Livewire::test(QuickActions::class)->assertSee('Uygulama kataloğu')->assertDontSee('Mağaza bildirimleri');
        $app = App::first();
        AppUser::factory()->create(['app_id' => $app->id, 'last_seen_at' => now()]);
        AppUser::factory()->create(['app_id' => $app->id, 'is_active' => false, 'last_seen_at' => now()]);
        $this->operator(['ViewAny:AppUser']);
        $this->assertSame(['Aktif üyelikler' => 1], $this->stats());
        Livewire::test(OverviewStats::class)->assertSee('Son 7 günde 1 üyelik kullanıldı');
    }

    public function test_live_subscriptions_use_paid_access_rules(): void
    {
        $this->operator(['ViewAny:Purchase']);
        Purchase::factory()->create(['status' => 'active']);
        Purchase::factory()->create(['status' => 'grace']);
        Purchase::factory()->create(['status' => 'canceled']);
        Purchase::factory()->create(['status' => 'active', 'expires_at' => now()->subMinute()]);
        Purchase::factory()->create(['status' => 'revoked']);
        $sandbox = StoreProduct::factory()->create(['environment' => 'sandbox']);
        Purchase::factory()->create(['store_product_id' => $sandbox->id]);
        Purchase::factory()->create(['store_product_id' => $sandbox->id, 'environment' => 'production']);
        $this->assertSame(['Canlı abonelikler' => 3], $this->stats());
    }

    public function test_support_queue_statistics_alerts_and_chart_are_application_scoped(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $app = App::factory()->create();
        $other = App::factory()->create();
        $agent = $this->operator(['ViewAny:SupportTicket', 'View:SupportTicket']);
        SupportAgentApp::create(['app_id' => $app->id, 'user_id' => $agent->id]);
        $urgent = $this->ticket($app, ['priority' => 'urgent']);
        $late = $this->ticket($app, ['due_at' => now()->subHour(), 'priority' => 'normal']);
        $closed = $this->ticket($app, ['status' => 'closed', 'created_at' => now()->subDays(8), 'closed_at' => now()]);
        $hidden = $this->ticket($other, ['priority' => 'urgent', 'due_at' => now()->subDay()]);
        $this->assertSame(['Yanıt bekleyen talepler' => 2], $this->stats());
        Livewire::test(SupportQueue::class)
            ->assertCanSeeTableRecords([$late, $urgent], inOrder: true)
            ->assertCanNotSeeTableRecords([$closed, $hidden])
            ->assertSee('Dış kullanıcı');
        $actions = Livewire::test(QuickActions::class)->assertSee('Hedef tarihi geçen talepler')->assertDontSee($other->name);
        $items = (new ReflectionMethod(QuickActions::class, 'getViewData'))->invoke($actions->instance());
        $this->assertSame([1, 1], array_column($items['attention'], 'count'));
        $chart = Livewire::test(SupportActivityChart::class);
        $data = (new ReflectionMethod(SupportActivityChart::class, 'getData'))->invoke($chart->instance());
        $this->assertCount(7, $data['labels']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 2], $data['datasets'][0]['data']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $data['datasets'][1]['data']);
        SupportAgentApp::where('user_id', $agent->id)->delete();
        $this->assertSame(['Yanıt bekleyen talepler' => 0], $this->stats());
        $chart->call('updateChartData');
        $data = (new ReflectionMethod(SupportActivityChart::class, 'getData'))->invoke($chart->instance());
        $this->assertSame(0, array_sum($data['datasets'][0]['data']));
    }

    public function test_application_filter_and_alert_links_open_filtered_lists(): void
    {
        $first = App::factory()->create();
        $second = App::factory()->create();
        $this->operator(['ViewAny:SupportTicket', 'View:SupportTicket', 'ViewAllApps:SupportTicket']);
        $one = $this->ticket($first, ['due_at' => now()->subMinute()]);
        $two = $this->ticket($second);
        Livewire::test(SupportQueue::class)->filterTable('app_id', $second->id)
            ->assertCanSeeTableRecords([$two])->assertCanNotSeeTableRecords([$one]);
        $actions = Livewire::test(QuickActions::class);
        $items = (new ReflectionMethod(QuickActions::class, 'getViewData'))->invoke($actions->instance());
        $this->get($items['attention'][0]['url'])->assertOk();
        parse_str(parse_url($items['attention'][0]['url'], PHP_URL_QUERY), $params);
        Livewire::withQueryParams($params)->test(ListSupportTickets::class)
            ->assertCanSeeTableRecords([$one])->assertCanNotSeeTableRecords([$two]);
    }

    public function test_store_and_purchase_errors_only_appear_for_permitted_operators(): void
    {
        Purchase::factory()->create(['check_failures' => 1]);
        StoreNotification::factory()->create(['status' => 'failed']);
        $this->operator(['ViewAny:StoreNotification']);
        Livewire::test(QuickActions::class)->assertSee('İnceleme bekleyen mağaza bildirimleri')->assertDontSee('Doğrulama hatası olan satın alımlar');
        $user = $this->operator(['ViewAny:Purchase']);
        $actions = Livewire::test(QuickActions::class)->assertSee('Doğrulama hatası olan satın alımlar')->assertDontSee('İnceleme bekleyen mağaza bildirimleri');
        $user->removeRole('panel_user');
        $actions->call('$refresh')->assertForbidden();
    }
}
