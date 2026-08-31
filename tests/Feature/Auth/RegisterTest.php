<?php

use App\Events\User\UserRegistered;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Event::fake();
    RateLimiter::clear('registration');

    $this->validData = [
        'name' => 'Test User',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
});

it('allows a doctor to register successfully with valid email or phone', function (string $contact) {
    $response = $this->postJson(
        route('auth.register'),
        array_merge($this->validData, ['contact' => $contact])
    );

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'user' => [
                'id',
                'name',
                'contact',
                'type',
                'created_at',
                'updated_at',
            ],
            'doctor_id',
            'token',
        ],
    ]);

    $response->assertJsonMissingPath('data.user.password');
    $response->assertJsonMissingPath('data.user.password_confirmation');

    Event::assertDispatched(UserRegistered::class, function ($event) use ($contact) {
        return $event->user->contact === $contact;
    });

    $userId = $response->json('data.user.id');
    $doctorId = $response->json('data.doctor_id');

    $this->assertDatabaseHas('users', [
        'id' => $userId,
        'contact' => $response->json('data.user.contact'),
        'type' => 'doctor',
    ]);

    $this->assertDatabaseHas('doctors', [
        'id' => $doctorId,
        'user_id' => $userId,
    ]);

    $user = User::find($userId);
    expect(Hash::check('password', $user->password))->toBeTrue();
})->with([
    'email' => [fake()->unique()->safeEmail()],
    'phone' => [fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########')],
]);

it('fails registration if contact is already taken', function () {
    $user = User::factory()->create();

    $response = $this->postJson(
        route('auth.register'),
        array_merge($this->validData, ['contact' => $user->contact])
    );

    Event::assertNotDispatched(UserRegistered::class);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Validation Errors',
        'data' => [
            'contact' => ['The contact has already been taken.'],
        ],
    ]);
});

it('fails registration with invalid data', function (array $invalidField, array $expectedErrors) {
    $response = $this->postJson(
        route('auth.register'),
        array_merge($this->validData, ['contact' => fake()->unique()->safeEmail()], $invalidField)
    );

    Event::assertNotDispatched(UserRegistered::class);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Validation Errors',
        'data' => $expectedErrors,
    ]);
})->with([
    'name missing' => [['name' => null], ['name' => ['The name field is required.']]],
    'contact missing' => [['contact' => null], ['contact' => ['The contact field is required.']]],
    'contact is not valid' => [['contact' => 'not-an-email-or-phone'], ['contact' => ['The contact must be a valid email address or a valid phone number starting with 010, 011, 012, or 015 followed by 8 digits.']]],
    'password not match' => [['password_confirmation' => 'wrongpassword'], ['password' => ['The password field confirmation does not match.']]],
]);

it('blocks excessive registration attempts via rate limiter', function (){
    $contact = fake()->unique()->safeEmail();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(
            route('auth.register'),
            array_merge($this->validData, ['contact' => $contact])
        );
    }

    $response = $this->postJson(
        route('auth.register'),
        array_merge($this->validData, ['contact' => $contact])
    );

    $response->assertStatus(429);
    $response->assertJson([
        'success' => false,
        'message' => 'Too many attempts. Please try again later.',
    ]);
});
