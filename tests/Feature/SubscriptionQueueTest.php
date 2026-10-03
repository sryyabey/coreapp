<?php

namespace Tests\Feature;

use App\Http\ProcessStoreNotification;
use App\Http\RefreshPurchase;
use App\Http\StoreVerifier;
use App\Jobs\ProcessStoreNotificationJob;
use App\Jobs\RefreshPurchaseJob;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionQueueTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): Purchase
    {
        $purchase = Purchase::factory()->create();
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);

        return $purchase;
    }

    private function verified(Purchase $purchase, string $status, mixed $expires): array
    {
        return ['identity' => $purchase->identity, 'environment' => $purchase->environment, 'product_id' => $purchase->storeProduct->product_id,
            'base_plan_id' => $purchase->storeProduct->base_plan_id, 'proof' => $purchase->proof, 'status' => $status, 'expires_at' => $expires];
    }

    public function test_command_selects_due_production_purchases(): void
    {
        Queue::fake();
        $due = Purchase::factory()->create(['next_check_at' => now()->subMinute()]);
        Purchase::factory()->create(['next_check_at' => now()->addHour()]);
        Purchase::factory()->create(['environment' => 'sandbox']);
        Purchase::factory()->create(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        $this->artisan('billing:check-subscriptions')->assertSuccessful();
        Queue::assertPushed(RefreshPurchaseJob::class, fn ($job): bool => $job->purchaseId === $due->id && $job->queue === 'billing');
        Queue::assertPushed(RefreshPurchaseJob::class, 1);
    }

    public function test_check_refreshes_expiry_and_clears_previous_failure(): void
    {
        $purchase = $this->purchase();
        $purchase->update(['check_failures' => 2, 'last_check_error' => 'OldError']);
        $expires = now()->addYear();
        $mock = $this->mock(StoreVerifier::class);
        $mock->shouldReceive('verify')->once()->andReturn($this->verified($purchase, 'active', $expires));
        $mock->shouldReceive('acknowledge')->once();
        app(RefreshPurchase::class)->handle($purchase->id);
        $fresh = $purchase->fresh();
        $this->assertSame(0, $fresh->check_failures);
        $this->assertNull($fresh->last_check_error);
        $this->assertEquals($expires->getTimestamp(), $fresh->expires_at->getTimestamp());
        $this->assertTrue($fresh->next_check_at->between(now()->addHours(5), now()->addHours(7)));
    }

    public function test_expiration_boundary_schedules_early_check_without_extending_paid_time(): void
    {
        $purchase = $this->purchase();
        $expires = now()->addHour();
        $originalTimestamp = $expires->getTimestamp();
        $mock = $this->mock(StoreVerifier::class);
        $mock->shouldReceive('verify')->andReturn($this->verified($purchase, 'canceled', $expires));
        $mock->shouldReceive('acknowledge');
        app(RefreshPurchase::class)->handle($purchase->id);
        $this->assertSame($originalTimestamp, $purchase->fresh()->expires_at->getTimestamp());
        $this->assertSame($originalTimestamp + 300, $purchase->fresh()->next_check_at->getTimestamp());
    }

    public function test_store_failure_preserves_current_rights_and_delays_next_check(): void
    {
        $purchase = $this->purchase();
        $this->mock(StoreVerifier::class)->shouldReceive('verify')->andThrow(new \RuntimeException('sensitive-proof'));
        try {
            app(RefreshPurchase::class)->handle($purchase->id);
        } catch (\RuntimeException $exception) {
        }
        $this->assertTrue($purchase->fresh()->hasPaidAccess());
        $this->assertSame(1, $purchase->fresh()->check_failures);
        $this->assertSame('RuntimeException', $purchase->fresh()->last_check_error);
        $this->assertTrue($purchase->fresh()->next_check_at->isFuture());
    }

    public function test_verified_expiry_removes_access_and_checks_daily(): void
    {
        $purchase = $this->purchase();
        $mock = $this->mock(StoreVerifier::class);
        $mock->shouldReceive('verify')->andReturn($this->verified($purchase, 'expired', now()->subMinute()));
        $mock->shouldReceive('acknowledge');
        app(RefreshPurchase::class)->handle($purchase->id);
        $this->assertFalse($purchase->fresh()->hasPaidAccess());
        $this->assertTrue($purchase->fresh()->next_check_at->gt(now()->addHours(23)));
    }

    public function test_exhausted_events_are_not_scheduled_or_reprocessed(): void
    {
        Queue::fake();
        $event = StoreNotification::factory()->create(['status' => 'exhausted', 'attempts' => 20, 'updated_at' => now()->subHour()]);
        $this->artisan('billing:retry-notifications')->assertSuccessful();
        Queue::assertNothingPushed();
        app(ProcessStoreNotification::class)->handle($event->id);
        $this->assertSame(20, $event->fresh()->attempts);
    }

    public function test_billing_jobs_have_unique_keys_and_safe_timeout(): void
    {
        $check = new RefreshPurchaseJob(12);
        $event = new ProcessStoreNotificationJob(34);
        $this->assertSame('12', $check->uniqueId());
        $this->assertSame('34', $event->uniqueId());
        $this->assertSame('billing', $event->queue);
        $this->assertLessThan(config('queue.connections.database.retry_after'), $check->timeout);
        $this->assertCount(4, $check->backoff());
    }
}
