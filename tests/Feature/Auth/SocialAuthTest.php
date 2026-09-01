<?php

use App\Events\User\UserRegistered;
use App\Models\Doctor;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;

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

    Socialite::shouldReceive('driver->stateless->user')
        ->once()
        ->andReturn($socialUser);
}

beforeEach(function () {
    Event::fake();
    config(['services.frontend.url' => 'https://frontend.test']);
});

it('generates redirect url successfully', function () {
    $response = get(route('google.redirect'));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['url'],
        ]);
});

it('creates a new doctor user and social account on first login', function () {

    mockSocialiteUser(
        id: 'google-unique-id',
        email: 'new-doctor@example.com',
        name: 'Menna Baligh'
    );

    $response = get(route('google.callback'));

    $location = $response->headers->get('Location');
    expect($location)->toContain('https://frontend.test/auth/callback?code=');

    $user = User::whereContact('new-doctor@example.com')->first();

    expect($user)
        ->not->toBeNull()
        ->name->toBe('Menna Baligh')
        ->type->toBe('doctor')
        ->doctor->not->toBeNull();

    assertDatabaseHas('doctors', [
        'user_id' => $user->id,
    ]);
    assertDatabaseHas('user_social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-unique-id',
    ]);

    Event::assertDispatched(UserRegistered::class);
});

it('logs in doctor directly if social account already exists', function () {

    $user = User::factory()->create([
        'contact' => 'existing-social@example.com',
        'type' => 'doctor'
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

    $response = get(route('google.callback'));

    $location = $response->headers->get('Location');
    expect($location)->toContain('https://frontend.test/auth/callback?code=');
    expect(User::count())->toBe(1);
});

it('denies login if the account is not a doctor', function () {
    User::factory()->create([
        'contact' => 'patient@example.com',
        'type' => 'patient',
    ]);

    mockSocialiteUser(
        id: 'patient-google-id',
        email: 'patient@example.com'
    );

    $response = get(route('google.callback'));

    $location = $response->headers->get('Location');
    expect($location)->toContain('message=auth_failed');
});

it('successfully exchanges code for user data and auth token', function () {
    $user = User::factory()->create(['type' => 'doctor']);
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

it('fails to exchange an expired, invalid, or already-used code', function () {
    $response = $this->postJson(route('google.exchange'), [
        'code' => 'non-existent-or-expired-code',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid or expired exchange code.',
        ]);
});
