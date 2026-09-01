<?php

use App\Events\User\UserRegistered;
use App\Models\Doctor;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

function generateValidEncryptedState(?string $clientNonce = null): string
{
    return Crypt::encryptString(json_encode([
        'server_nonce' => Str::random(32),
        'client_nonce' => $clientNonce,
        'expires_at' => now()->addMinutes(10)->timestamp,
    ]));
}
function mockSocialiteUser(
    string $id = '12345',
    string $email = 'doctor@example.com',
    string $name = 'Dr. Tareq',
    bool $emailVerified = true
): void {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->allows([
        'getId' => $id,
        'getEmail' => $email,
        'getName' => $name,
        'getRaw' => ['email_verified' => $emailVerified],
    ]);

    $providerMock = Mockery::mock();
    $providerMock->shouldReceive('stateless')->andReturnSelf();
    $providerMock->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($providerMock);
}

beforeEach(function () {
    Event::fake();
    config(['services.frontend.url' => 'https://frontend.test']);
});

it('generates redirect url successfully', function () {
    $response = $this->getJson(route('google.redirect', ['client_nonce' => 'test-nonce-123']));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['url'],
        ]);
});

it('creates a new doctor user and redirects with an exchange code', function () {
    $validState = generateValidEncryptedState('client-test-nonce');

    mockSocialiteUser(
        id: 'google-unique-id',
        email: 'new-doctor@example.com',
        name: 'Menna Baligh'
    );

    $response = $this->getJson(route('google.callback', ['state' => $validState]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('https://frontend.test/auth/callback?code=');

    $user = User::whereContact('new-doctor@example.com')->first();

    expect($user)
        ->not->toBeNull()
        ->name->toBe('Menna Baligh')
        ->type->toBe('doctor')
        ->doctor->not->toBeNull();

    $this->assertDatabaseHas('doctors', ['user_id' => $user->id]);
    $this->assertDatabaseHas('user_social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-unique-id',
    ]);

    Event::assertDispatched(UserRegistered::class);
});

it('logs in doctor directly if social account already exists without dispatching registration event', function () {
    $validState = generateValidEncryptedState();

    $user = User::factory()->create([
        'contact' => 'existing-social@example.com'
    ]);
    Doctor::factory()->create(['user_id' => $user->id]);

    UserSocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'existing-social-id',
    ]);

    mockSocialiteUser(
        id: 'existing-social-id',
        email: 'existing-social@example.com'
    );

    $response = $this->getJson(route('google.callback', ['state' => $validState]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('https://frontend.test/auth/callback?code=');

    expect(User::count())->toBe(1);
    Event::assertNotDispatched(UserRegistered::class);
});

it('denies login if the account is not a doctor', function () {
    $validState = generateValidEncryptedState();

    User::factory()->create([
        'contact' => 'patient@example.com',
        'type' => 'patient',
    ]);

    mockSocialiteUser(
        id: 'patient-google-id',
        email: 'patient@example.com'
    );

    $response = $this->getJson(route('google.callback', ['state' => $validState]));

    $location = $response->headers->get('Location');
    expect($location)->toContain('message=auth_failed');
});

it('fails and redirects to auth_failed if state is missing or invalid', function () {
    $response = $this->getJson(route('google.callback'));
    expect($response->headers->get('Location'))->toContain('message=auth_failed');

    $invalidResponse = $this->getJson(route('google.callback', ['state' => 'forged-state-string']));
    expect($invalidResponse->headers->get('Location'))->toContain('message=auth_failed');
});

it('successfully exchanges code for doctor', function () {
    $user = User::factory()->create([
        'is_active' => true,
    ]);
    Doctor::factory()->create(['user_id' => $user->id]);

    $token = 'dummy-sanctum-token-123';
    $code = 'valid-test-exchange-code-456';

    Cache::put("social_exchange_{$code}", [
        'user_id' => $user->id,
        'token' => $token,
    ], now()->addSeconds(60));

    $response = $this->postJson(route('google.exchange'), [
        'code' => $code,
    ]);
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'contact' ,
                    'type' ,
                    'created_at',
                    'updated_at',
                ],
                'doctor_id' ,
                'token'
            ],
        ]);
});

it('fails to exchange an expired or invalid code', function () {
    $response = $this->postJson(route('google.exchange'), [
        'code' => 'non-existent-code',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid or expired exchange code.',
        ]);
});
