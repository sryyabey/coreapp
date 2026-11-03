<?php

namespace Tests\Feature;

use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestSupportTest extends TestCase
{
    use RefreshDatabase;

    private function url(App $app): string
    {
        return '/api/v1/apps/'.$app->slug.'/support/guest';
    }

    public function test_installation_credentials_are_retryable_and_cannot_be_replaced(): void
    {
        $app = App::factory()->create(['slug' => 'minimal-fasting']);
        $identity = ['external_user_id' => (string) Str::uuid(), 'secret' => bin2hex(random_bytes(32))];
        $this->postJson($this->url($app).'/session', $identity)->assertOk();
        $this->postJson($this->url($app).'/session', $identity)->assertOk();
        $this->assertDatabaseCount('support_guest_sessions', 1);
        $this->assertDatabaseHas('support_guest_sessions', ['token_hash' => hash('sha256', $identity['secret'])]);
        $this->postJson($this->url($app).'/session', array_replace($identity, ['secret' => bin2hex(random_bytes(32))]))->assertUnauthorized();
        $url = $this->url($app).'/users/'.$identity['external_user_id'].'/tickets';
        $payload = ['name' => 'Minimal Fasting', 'subject' => 'Help', 'body' => 'Question', 'locale' => 'en', 'client_request_id' => (string) Str::uuid()];
        $this->postJson($url, $payload)->assertUnauthorized();
        $id = $this->withToken($identity['secret'])->postJson($url, $payload)->assertCreated()->json('data.id');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertHeader('Cache-Control', 'no-store, private');
        $other = ['external_user_id' => (string) Str::uuid(), 'secret' => bin2hex(random_bytes(32))];
        $this->postJson($this->url($app).'/session', $other)->assertOk();
        $otherUrl = $this->url($app).'/users/'.$other['external_user_id'].'/tickets';
        $this->getJson($otherUrl)->assertUnauthorized();
        $this->withToken($other['secret'])->getJson($otherUrl.'/'.$id)->assertNotFound();
        $this->getJson($otherUrl)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/integrations/'.$app->slug.'/support/users/'.$other['external_user_id'].'/tickets')->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_registration_is_only_available_for_enabled_active_apps(): void
    {
        $app = App::factory()->create();
        $identity = ['external_user_id' => (string) Str::uuid(), 'secret' => bin2hex(random_bytes(32))];
        $this->postJson($this->url($app).'/session', $identity)->assertNotFound();
        $app->update(['slug' => 'minimal-fasting', 'is_active' => false]);
        $this->postJson($this->url($app).'/session', $identity)->assertNotFound();
        $app->update(['is_active' => true]);
        $this->postJson($this->url($app).'/session', ['external_user_id' => 'invalid', 'secret' => 'short'])->assertUnprocessable();
        $this->assertDatabaseCount('support_guest_sessions', 0);
    }
}
