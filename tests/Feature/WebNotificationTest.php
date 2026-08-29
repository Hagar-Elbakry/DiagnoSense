<?php

beforeEach(function () {
    $this->user = createUserWithType('doctor', 'menna@gmail.com');
    $this->doctor = $this->user->doctor;
    $this->actingAs($this->user, 'sanctum');
    $this->notification = createNotification($this->doctor);
    createNotification($this->doctor);
    createNotification($this->doctor, read: true);
});

it('returns notifications list', function () {
    $this->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data',
        ]);
});

it('returns unread notifications count', function () {
    $this->getJson(route('notifications.unreadCount'))
        ->assertOk()
        ->assertJsonFragment([
            'unread_count' => 2,
        ]);
});

it('marks notification as read', function () {
    $this->patchJson(route('notifications.read', $this->notification->id))
        ->assertOk();

    expect($this->notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $this->patchJson(route('notifications.readAll'))
        ->assertOk();

    expect($this->doctor->unreadNotifications()->count())
        ->toBe(0);
});

it('deletes all notifications', function () {
    $this->deleteJson(route('notifications.clearAll'))
        ->assertOk();

    expect($this->doctor->notifications()->count())
        ->toBe(0);
});
