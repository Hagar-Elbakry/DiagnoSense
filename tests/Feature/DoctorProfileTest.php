<?php

use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = createUserWithType('doctor', 'menna@diagno.com');
    $this->user->update([
        'name' => 'Dr. Menna',
        'password' => Hash::make('Old_Password_123'),
    ]);
    $this->doctor = $this->user->doctor;

    $this->actingAs($this->user, 'sanctum');
});

it('retrieves doctor profile data', function () {
    $response = $this->getJson(route('doctor.profile.edit'));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'id',
            'name',
            'contact',
            'speciality',
        ],
    ]);
});

it('allows updating both name and specialization', function () {
    $newData = [
        'name' => 'Dr. Menna Baligh (PhD)',
        'specialization' => 'Cardiology Expert',
    ];

    $response = $this->patchJson(route('doctor.profile.update'), $newData);
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => $newData['name'],
    ]);
    $this->assertDatabaseHas('doctors', [
        'id' => $this->doctor->id,
        'specialization' => $newData['specialization'],
    ]);
});

it('allows doctor to delete his account', function () {
    $response = $this->deleteJson(route('doctor.profile.destroy'), [
        'password' => 'Old_Password_123',
        'password_confirmation' => 'Old_Password_123',
    ]);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
    ]);
});

it('fails to delete account with wrong password', function () {
    $response = $this->deleteJson(route('doctor.profile.destroy'), [
        'password' => 'wrong-password',
        'password_confirmation' => 'wrong-password',
    ]);
    $response->assertStatus(422);
    $response->assertJsonStructure([
        'success',
        'message',
    ]);
});

it('successfully changes password and invalidates tokens', function () {
    $response = $this->patchJson(route('doctor.password.update'), [
        'current_password' => 'Old_Password_123',
        'new_password' => 'New_Strong_Pass_456',
        'new_password_confirmation' => 'New_Strong_Pass_456',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);

    expect(Hash::check('New_Strong_Pass_456', $this->user->refresh()->password))->toBeTrue();

    expect($this->user->tokens()->count())->toBe(0);
});

it('fails password change with invalid data', function (array $payload, array $expectedErrors) {
    $response = $this->patchJson(route('doctor.password.update'), $payload);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validation Errors',
            'data' => $expectedErrors,
        ]);

    expect(Hash::check('Old_Password_123', $this->user->refresh()->password))->toBeTrue();

})->with([
    'incorrect current password' => [
        ['current_password' => 'Wrong_Pass', 'new_password' => 'NewPass123', 'new_password_confirmation' => 'NewPass123'],
        ['current_password' => ['The current password is incorrect.']],
    ],
    'password confirmation mismatch' => [
        ['current_password' => 'Old_Password_123', 'new_password' => 'NewPass123', 'new_password_confirmation' => 'DifferentPass'],
        ['new_password' => ['The new password field confirmation does not match.']],
    ],
    'password too short' => [
        ['current_password' => 'Old_Password_123', 'new_password' => 'short', 'new_password_confirmation' => 'short'],
        ['new_password' => ['The new password field must be at least 8 characters.']],
    ],
]);
