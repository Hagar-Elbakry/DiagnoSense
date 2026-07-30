<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Notifications\ResetPasswordSMSNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

beforeEach(function() {
    $doctorWithEmail = createUserWithType('doctor', 'testDoctor@gmail.com');
    $patientWithEmail = createUserWithType('patient', 'testPatient@gmail.com');
    $doctorWithPhone = createUserWithType('doctor', '01012345678');
    $patientWithPhone = createUserWithType('patient', '01012345679');

    $this->validData = [
        'doctor' => [
            'email' => [
                'contact' => $doctorWithEmail->contact,
            ],
            'phone' => [
                'contact' => $doctorWithPhone->contact,
            ],
        ],
        'patient' => [
            'email' => [
                'contact' => $patientWithEmail->contact,
            ],
            'phone' => [
                'contact' => $patientWithPhone->contact,
            ],
        ],
    ];
});

dataset('contacts', [
    'doctor with email' => ['doctor', 'email'],
    'doctor with phone' => ['doctor', 'phone'],
    'patient with email' => ['patient', 'email'],
    'patient with phone' => ['patient', 'phone']
]);

dataset('invalid_contacts', [
    'contact doesn\'t exist' => ['doctor','invalidContact@gmail.com'],
    'contact that exists but wrong type' => ['doctor', 'testPatient@gmail.com']
]);

it('sends otp', function (string $type, string $contactType) {
    Mail::fake();
   Notification::fake();
    $data = $this->validData[$type][$contactType];
    $response = $this->postJson(route('password.forget', $type), $data);
    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'OTP has been sent to your registered contact.',
    ]);
    if($contactType == 'email') {
        Mail::assertQueued(ResetPasswordMail::class);
        Notification::assertNothingSent();
    } else {
        $user = User::where('contact', $data['contact'])->first();
        Notification::assertSentTo($user, ResetPasswordSMSNotification::class);
    }
})->with('contacts');

it('fails to send otp', function (string $type, string $contact) {
    $response = $this->postJson(route('password.forget', $type), [
        'contact' => $contact,
    ]);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'data' => [
            'contact' => ['This contact is invalid.']
        ]
    ]);
})->with('invalid_contacts');

it('verifies otp', function (string $type, string $contactType) {
    Mail::fake();
    Notification::fake();
    $data = $this->validData[$type][$contactType];
    $this->postJson(route('password.forget', $type), $data);
    $otp = DB::table('otps')->where('identifier', $data['contact'])->value('token');
    $response = $this->postJson(route('password.verify', $type), [
        'contact' => $data['contact'],
        'otp' => $otp,
    ]);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'reset_token'
        ]
    ]);
})->with('contacts');

it('fails to verify wrong otp', function (string $type, string $contactType) {
    $data = $this->validData[$type][$contactType];
    $this->postJson(route('password.forget', $type), $data);
    $response = $this->postJson(route('password.verify', $type), [
        'contact' => $data['contact'],
        'otp' => '000000'
    ]);
    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Invalid Or Expired OTP.'
    ]);
})->with('contacts');

it('fails to verify expired otp', function (string $type, string $contactType) {
    $data = $this->validData[$type][$contactType];
    insertOtp($data['contact'], true);
    $response = $this->postJson(route('password.verify', $type), [
        'contact' => $data['contact'],
        'otp' => '123456'
    ]);
    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Invalid Or Expired OTP.'
    ]);
})->with('contacts');


it('allow user to reset password', function (string $type, string $contactType) {
    $data = $this->validData[$type][$contactType];
    $this->postJson(route('password.forget', $type), $data);
    $otp = DB::table('otps')->where('identifier', $data['contact'])->value('token');
    $response = $this->postJson(route('password.verify', $type), [
        'contact' => $data['contact'],
        'otp' => $otp,
    ]);
    $token = $response->json('data.reset_token');
    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson(route('password.reset', $type), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]) 
        ->assertStatus(200);
})->with('contacts');

it('fails to reset password if password doesnt match', function (string $type, string $contactType) {
    $data = $this->validData[$type][$contactType];
    $this->postJson(route('password.forget', $type), $data);
    $otp = DB::table('otps')->where('identifier', $data['contact'])->value('token');
    $response = $this->postJson(route('password.verify', $type), [
        'contact' => $data['contact'],
        'otp' => $otp,
    ]);
    $token = $response->json('data.reset_token');
    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson(route('password.reset', $type), [
            'password' => 'password',
            'password_confirmation' => 'wrongPassword',
        ])
        ->assertStatus(422)
        ->assertJson([
            'data' => [
                'password' => ['The password field confirmation does not match.']
            ]
        ]);
})->with('contacts');