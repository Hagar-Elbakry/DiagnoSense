<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\PayPerUseActivated;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(PlanSeeder::class);
    $this->premiumPlan = Plan::where('name', 'Premium')->first();
    $this->user = createDoctorWithBilling(balance: 80000);
    $this->doctor = $this->user->doctor;
    $this->wallet = $this->doctor->wallet;
    $this->actingAs($this->user, 'sanctum');
});

it('returns all available plans successfully', function () {

    $response = $this->getJson(route('subscriptions.plans'));

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Available plans retrieved successfully')
        ->assertJsonCount(3, 'data');
});

it('allows a doctor to subscribe to a plan successfully', function () {
    $response = $this->postJson(route('subscriptions.subscribe', ['plan' => $this->premiumPlan->id]));
    $response->assertStatus(201);
});

it('switches doctor to pay per use mode successfully and cancels all active subscriptions', function () {
    Subscription::create([
        'doctor_id' => $this->doctor->id,
        'plan_id' => $this->premiumPlan->id,
        'status' => 'active',
        'started_at' => now(),
        'expires_at' => now()->addMonth(),
        'used_summaries' => 0,
    ]);

    $response = $this->postJson(route('subscriptions.pay-per-use'));

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Switched to Pay-Per-Use mode. 20EGP will be charged per file.');

    $this->assertDatabaseHas('subscriptions', [
        'doctor_id' => $this->doctor->id,
        'status' => 'cancelled',
    ]);

    expect($this->doctor->fresh()->billing_mode)->toBe('pay-per-use');

    $this->assertDatabaseHas('subscriptions', [
        'doctor_id' => $this->doctor->id,
        'status' => 'cancelled',
    ]);

    Notification::assertSentTo($this->doctor, PayPerUseActivated::class);
});

it('returns the correct current subscription metrics and features', function () {
    $this->doctor->update(['billing_mode' => 'subscription']);
    $this->wallet->update(['balance' => 300.00]);
    $this->doctor->subscriptions()->create([
        'plan_id' => $this->premiumPlan->id,
        'status' => 'active',
        'started_at' => now(),
        'expires_at' => now()->addDays(30),
        'used_summaries' => 0,
    ]);

    $response = $this->getJson(route('subscriptions.current'));
    $response->assertStatus(200)->assertJsonPath('data.billing_mode', 'subscription');
});

it('cancels the active subscription and returns helpful UX message', function () {
    $this->doctor->update(['billing_mode' => 'subscription']);
    $subscription = $this->doctor->subscriptions()->create([
        'plan_id' => $this->premiumPlan->id,
        'status' => 'active',
        'started_at' => now(),
        'expires_at' => now()->addDays(30),
        'used_summaries' => 0,
    ]);

    $response = $this->patchJson(route('subscriptions.cancel'));
    $response->assertStatus(200);
    $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'cancelled']);
});
