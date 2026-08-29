<?php

use App\Models\Patient;
use App\Models\User;

beforeEach(function () {

    $this->user = User::factory()->create([
        'contact' => 'old@example.com',
    ]);

    $this->patient = Patient::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'sanctum');
});

it('updates profile successfully', function () {

    $payload = [
        'contact' => 'new@example.com',
    ];

    $response = $this->patchJson(
        route('profile.update'),
        $payload
    );

    $response->assertStatus(200)
        ->assertJsonPath(
            'message',
            'Profile updated successfully'
        )
        ->assertJsonPath(
            'data.contact',
            'new@example.com'
        );

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'contact' => 'new@example.com',
    ]);
});

it('returns validation error when contact already exists', function () {

    User::factory()->create([
        'contact' => 'existing@example.com',
    ]);

    $payload = [
        'contact' => 'existing@example.com',
    ];

    $response = $this->patchJson(
        route('profile.update'),
        $payload
    );

    $response->assertStatus(422);
});