<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'contact_verified_at' => null,
    ]);
});

it('can verify contact', function () {
    insertOtp($this->user->contact);
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('verify-contact'), ['otp' => '123456']);
    $response->assertStatus(200);
    $this->assertNotNull($this->user->fresh()->contact_verified_at);
    $this->assertDatabaseHas('otps', ['valid' => 0]);
    $response->assertJson([
        'success' => true,
        'message' => 'User verified successfully.',
    ]);
});

it('fails to verify contact with expired otp', function () {
    insertOtp($this->user->contact, true);
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('verify-contact'), ['otp' => '123456']);
    $response->assertStatus(401);
    $response->assertJson([
        'success' => false,
        'message' => 'Invalid or expired OTP.',
    ]);
});

it('resend contact verification otp', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('resend-otp'));
    $response->assertStatus(200);
    $this->assertDatabaseHas('otps', ['identifier' => $this->user->contact]);
    $response->assertJson([
        'success' => true,
        'message' => 'OTP sent successfully.',
    ]);
});

it('fails to resend contact verification otp with verified contact', function () {
    $this->user->update([
        'contact_verified_at' => now(),
    ]);
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('resend-otp'));
    $response->assertStatus(409);
    $response->assertJson([
        'success' => false,
        'message' => 'User already verified.',
    ]);
});
