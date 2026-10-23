<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_email_otp_before_authenticating(): void
    {
        Notification::fake();

        $response = $this->postFromSpa('/api/register', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('user')
            ->assertJsonPath(
                'message',
                'Se l’indirizzo può essere usato, riceverai un codice per completare l’accesso.',
            );

        $this->assertGuest('web');
        $this->assertDatabaseHas('users', [
            'email' => 'mario@example.com',
            'email_verified_at' => null,
            'password' => null,
        ]);
        $this->assertSame('user', User::where('email', 'mario@example.com')->firstOrFail()->getRoleNames()->sole());
        Notification::assertSentOnDemandOnce(LoginOtpNotification::class);

        $code = $this->sentOtp();
        $cachedCode = Cache::get('auth:otp:'.hash('sha256', 'mario@example.com'));
        $this->assertNotSame($code, $cachedCode);
        $this->assertSame(
            hash_hmac('sha256', $code, (string) config('app.key')),
            $cachedCode,
        );

        $this->verifyOtp('mario@example.com', $code);

        $this->assertAuthenticatedAs(
            User::where('email', 'mario@example.com')->firstOrFail(),
            'web',
        );
        $this->assertNotNull(
            User::where('email', 'mario@example.com')->firstOrFail()->email_verified_at,
        );
    }

    public function test_existing_user_can_request_and_use_a_one_time_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mario@example.com']);

        $this->postFromSpa('/api/otp/send', ['email' => 'MARIO@example.com'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Se l’indirizzo è associato a un account, riceverai un codice di accesso.',
            );

        $this->verifyOtp('mario@example.com', $this->sentOtp())
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user, 'web');
        $this->getFromSpa('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->postFromSpa('/api/otp/verify', [
            'email' => $user->email,
            'code' => $this->sentOtp(),
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_otp_send_does_not_disclose_unknown_email_addresses(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $knownResponse = $this->postFromSpa('/api/otp/send', [
            'email' => 'known@example.com',
        ]);
        $unknownResponse = $this->postFromSpa('/api/otp/send', [
            'email' => 'unknown@example.com',
        ]);

        $knownResponse->assertOk();
        $unknownResponse
            ->assertOk()
            ->assertExactJson($knownResponse->json());
        Notification::assertSentOnDemandOnce(LoginOtpNotification::class);
    }

    public function test_registration_response_does_not_disclose_existing_accounts(): void
    {
        Notification::fake();
        User::factory()->create([
            'name' => 'Nome originale',
            'email' => 'mario@example.com',
        ]);

        $existingAccountResponse = $this->postFromSpa('/api/register', [
            'name' => 'Nome nuovo',
            'email' => 'mario@example.com',
        ]);
        $newAccountResponse = $this->postFromSpa('/api/register', [
            'name' => 'Giulia Rossi',
            'email' => 'giulia@example.com',
        ]);

        $existingAccountResponse->assertAccepted();
        $newAccountResponse->assertAccepted();
        $this->assertSame($newAccountResponse->json(), $existingAccountResponse->json());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', [
            'name' => 'Nome originale',
            'email' => 'mario@example.com',
        ]);
        Notification::assertSentOnDemandTimes(LoginOtpNotification::class, 2);
    }

    public function test_otp_sending_is_rate_limited_per_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'mario@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postFromSpa('/api/otp/send', [
                'email' => 'mario@example.com',
            ])->assertOk();
        }

        $this->postFromSpa('/api/otp/send', [
            'email' => 'mario@example.com',
        ])->assertTooManyRequests();

        Notification::assertSentOnDemandTimes(LoginOtpNotification::class, 3);
    }

    public function test_otp_verification_is_rate_limited_per_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mario@example.com']);
        $this->postFromSpa('/api/otp/send', ['email' => $user->email])->assertOk();

        $code = $this->sentOtp();
        $wrongCode = $code === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->verifyOtp($user->email, $wrongCode)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('code');
        }

        $this->verifyOtp($user->email, $wrongCode)->assertTooManyRequests();
    }

    public function test_expired_otp_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mario@example.com']);
        $this->postFromSpa('/api/otp/send', ['email' => $user->email])->assertOk();
        $code = $this->sentOtp();

        $this->travel(6)->minutes();

        $this->verifyOtp($user->email, $code)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_cors_allows_the_configured_spa_origin_with_credentials(): void
    {
        $this->configureSpaOrigin();
        config([
            'cors.allowed_origins' => [
                'http://localhost:4200',
                'http://127.0.0.1:4200',
            ],
        ]);

        foreach (['http://localhost:4200', 'http://127.0.0.1:4200'] as $origin) {
            $this->withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
            ])
                ->options('/api/otp/send')
                ->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', $origin)
                ->assertHeader('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function test_public_auth_mutations_reject_untrusted_origins(): void
    {
        Notification::fake();

        foreach ([
            ['/api/register', ['name' => 'Mario Rossi', 'email' => 'mario@example.com']],
            ['/api/otp/send', ['email' => 'mario@example.com']],
            ['/api/otp/verify', ['email' => 'mario@example.com', 'code' => '123456']],
        ] as [$uri, $payload]) {
            $this->withHeader('Origin', 'https://untrusted.example')
                ->postJson($uri, $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_protected_endpoints_reject_guests_and_bearer_tokens(): void
    {
        $this->getFromSpa('/api/me')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getFromSpa('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mario@example.com']);
        $this->postFromSpa('/api/otp/send', ['email' => $user->email])->assertOk();
        $this->verifyOtp($user->email, $this->sentOtp())->assertOk();

        $this->postFromSpa('/api/logout')->assertOk();
        $this->assertGuest('web');
        $this->getFromSpa('/api/me')->assertUnauthorized();
    }

    public function test_manage_users_permission_is_required_for_admin_user_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->getFromSpa('/api/admin/users')
            ->assertForbidden();

        $permission = Permission::findOrCreate('manage-users', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->actingAs($user, 'web')
            ->getFromSpa('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', $user->email);
    }

    private function postFromSpa(string $uri, array $data = []): TestResponse
    {
        $this->configureSpaOrigin();

        return $this->withHeader('Origin', 'http://localhost:4200')
            ->postJson($uri, $data);
    }

    private function getFromSpa(string $uri): TestResponse
    {
        $this->configureSpaOrigin();

        return $this->withHeader('Origin', 'http://localhost:4200')
            ->getJson($uri);
    }

    private function verifyOtp(string $email, string $code): TestResponse
    {
        return $this->postFromSpa('/api/otp/verify', [
            'email' => $email,
            'code' => $code,
        ]);
    }

    private function sentOtp(): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            LoginOtpNotification::class,
            function (LoginOtpNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );

        $this->assertIsString($code);

        return $code;
    }

    private function configureSpaOrigin(): void
    {
        config(['sanctum.stateful' => ['localhost:4200']]);
    }
}
