<?php

namespace App\Actions;

use App\Events\User\UserRegistered;
use App\Helpers\Authentication;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\DB;

class RegisterDoctorAction
{
    public function __construct(
        protected Otp $otp
    ) {}

    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['type'] = 'doctor';
            $data['is_active'] = true;

            $user = User::create($data);
            $doctor = $user->doctor()->create();

            $token = Authentication::getToken($user);
            $otpCode = Authentication::generateOtp($user->contact, $this->otp);

            UserRegistered::dispatch($user, $otpCode);

            return [
                'user' => $user,
                'doctor_id' => $doctor->id,
                'token' => $token,
            ];
        });
    }
}
