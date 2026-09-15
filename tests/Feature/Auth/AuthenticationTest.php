<?php

beforeEach(function () {
    $doctorWithEmail = createUserWithType(type: 'doctor', contact: 'testDoctor@gmail.com');
    $patientWithEmail = createUserWithType(type: 'patient', contact: 'testPatient@gmail.com');
    $doctorWithPhone = createUserWithType(type: 'doctor', contact: '01012345678');
    $patientWithPhone = createUserWithType(type: 'patient', contact: '01012345679');

    $this->validData = [
        'doctor' => [
            'email' => [
                'contact' => $doctorWithEmail->contact,
                'password' => 'password',
            ],
            'phone' => [
                'contact' => $doctorWithPhone->contact,
                'password' => 'password',
            ],
        ],
        'patient' => [
            'email' => [
                'contact' => $patientWithEmail->contact,
                'password' => 'password',
            ],
            'phone' => [
                'contact' => $patientWithPhone->contact,
                'password' => 'password',
            ],
        ],
    ];
});

dataset('user_types', ['doctor', 'patient']);

dataset('invalid_credentials', [
    "email that doesn't exist" => [['contact' => 'nonExist@gmail.com']],
    "phone that doesn't exist" => [['contact' => '01012345677']],
    'wrong password' => [['password' => 'wrongPassword']],
]);

dataset('invalid_data', [
    'empty contact' => [['contact' => null], ['contact' => ['Contact is required.']]],
    'empty password' => [['password' => null], ['password' => ['The password field is required.']]],
]);

it('allow user to login', function (string $userType) {
    $dataSet = getDataSets($userType, $this);
    foreach ($dataSet as $data) {
        $response = $this->postJson(route('auth.login', $userType), $data);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'token',
            ],
        ]);
    }
})->with('user_types');

it('fails login user with invalid credentials', function (string $userType, array $invalidCredentials) {
    $dataSet = getDataSets($userType, $this);
    foreach ($dataSet as $data) {
        $response = $this->postJson(route('auth.login', $userType), array_merge($data, $invalidCredentials));
        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
    }
})->with('user_types', 'invalid_credentials');

it('fails login user with invalid data', function (string $userType, array $invalidData, array $expectedErrors) {
    $dataSet = getDataSets($userType, $this);
    foreach ($dataSet as $data) {
        $response = $this->postJson(route('auth.login', $userType), array_merge($data, $invalidData));
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation Errors',
            'data' => $expectedErrors,
        ]);
    }
})->with('user_types', 'invalid_data');

it('fails to login if user account is deactivated', function (string $userType) {
    $user = createUserWithType(type: $userType, contact: 'inactive@test.com', isActive: false);

    $response = $this->postJson(route('auth.login', $userType), [
        'contact' => 'inactive@test.com',
        'password' => 'password',
    ]);
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
})->with('user_types');

it('fails login if user credentials match but route type is mismatched', function () {
    $doctor = createUserWithType('doctor', 'doctor.mismatch@gmail.com');
    $response = $this->postJson(route('auth.login', 'patient'), [
        'contact' => 'doctor.mismatch@gmail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

it('rejects login request with invalid user type parameter in route', function () {
    $response = $this->postJson(route('auth.login', 'admin'), [
        'contact' => 'test@admin.com',
        'password' => 'password',
    ]);
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid user type.',
        ]);
});

it('allow user to logout', function (string $userType) {
    $dataSet = getDataSets($userType, $this);
    foreach ($dataSet as $data) {
        $response = $this->postJson(route('auth.login', $userType), $data);
        $token = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('auth.logout'))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);

        auth()->forgetGuards();

        $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('auth.logout'));
        $response2->assertStatus(401);
    }
})->with('user_types');
