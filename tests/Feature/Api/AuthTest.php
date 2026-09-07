<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registering_returns_a_token_that_authenticates_requests(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'correct-horse-battery',
            'device_name' => 'cli',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'sara@example.com')
            ->assertJsonMissingPath('user.password');

        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Sara');

        $this->assertDatabaseHas('users', ['email' => 'sara@example.com']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'cli']);
    }

    #[Test]
    public function registration_validates_its_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['name' => 'Sara', 'email' => 'taken@example.com', 'password' => 'correct-horse-battery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/register', ['name' => 'Sara', 'email' => 'new@example.com', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function a_token_is_issued_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $token = $this->postJson('/api/auth/token', ['email' => $user->email, 'password' => 'correct-horse-battery', 'device_name' => 'cli'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->json('token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function wrong_credentials_are_rejected_without_saying_which_part_was_wrong(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->postJson('/api/auth/token', ['email' => $user->email, 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);

        $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);
    }

    #[Test]
    public function logging_out_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('cli')->plainTextToken;
        $other = $user->createToken('phone')->plainTextToken;

        $this->withToken($current)->deleteJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'phone']);

        // Guards cache the resolved user within one test; reset them as separate requests would.
        $this->app->make('auth')->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertUnauthorized();

        $this->app->make('auth')->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertOk();
    }

    #[Test]
    public function protected_endpoints_require_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->deleteJson('/api/auth/logout')->assertUnauthorized();
    }

    #[Test]
    public function authentication_endpoints_are_throttled(): void
    {
        foreach (range(1, 10) as $_) {
            $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])->assertTooManyRequests();
    }

    #[Test]
    public function api_requests_are_rate_limited_per_user(): void
    {
        config()->set('gold.api_rate_per_minute', 2);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/auth/me')->assertOk();
        $this->getJson('/api/auth/me')->assertOk()->assertHeader('X-RateLimit-Remaining', '0');
        $this->getJson('/api/auth/me')->assertTooManyRequests();
    }
}
