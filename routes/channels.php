<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.Doctor.{doctorId}', function ($user, $doctorId) {
    return $user->doctor && (int) $user->doctor->id === (int) $doctorId;
});