<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailPasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Test User', 'email' => 'test@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password',
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'platform' => 'ios', 'device_name' => 'iPhone',
        ], $overrides);
    }

    public function test_registration_hashes_password_and_login_normalizes_email(): void
    {
        $app = App::factory()->create();
        $base = '/api/v1/apps/'.$app->slug.'/auth/';
        $this->postJson($base.'register', $this->payload(['email' => ' TEST@Example.com ']))
            ->assertCreated()->assertJsonPath('data.user.email', 'test@example.com');
        $this->assertTrue(Hash::check('secure-password', User::sole()->password));
        $response = $this->postJson($base.'login', $this->payload(['email' => ' TEST@Example.com ']));
        $response->assertOk()->assertJsonPath('data.token_type', 'Bearer')->assertJsonMissingPath('data.user.password');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_duplicate_email_and_invalid_passwords_do_not_create_users(): void
    {
        $app = App::factory()->create();
        User::factory()->create(['email' => 'test@example.com']);
        $url = '/api/v1/apps/'.$app->slug.'/auth/register';
        $this->postJson($url, $this->payload(['email' => 'TEST@example.com']))->assertUnprocessable()->assertJsonValidationErrors('email');
        foreach (['short', str_repeat('a', 73), str_repeat('ş', 37)] as $password) {
            $this->postJson($url, $this->payload(['email' => 'new@example.com', 'password' => $password, 'password_confirmation' => $password]))
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson($url, $this->payload(['email' => 'new@example.com', 'password_confirmation' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('app_users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_credentials_return_the_same_error_without_creating_membership(): void
    {
        $app = App::factory()->create();
        User::factory()->create(['email' => 'test@example.com']);
        $url = '/api/v1/apps/'.$app->slug.'/auth/login';
        $known = $this->postJson($url, $this->payload(['password' => 'wrong-password']))->assertUnprocessable();
        $unknown = $this->postJson($url, $this->payload(['email' => 'unknown@example.com']))->assertUnprocessable();
        $this->assertSame($known->json('errors.email'), $unknown->json('errors.email'));
        $this->postJson($url, $this->payload(['email' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('app_users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
