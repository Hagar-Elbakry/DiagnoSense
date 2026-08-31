<?php

namespace App\Services\Auth;

use App\Events\User\UserRegistered;
use App\Helpers\Authentication;
use App\Mail\EmailVerificationMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Notifications\EmailVerificationSMSNotification;
use App\Notifications\ResetPasswordSMSNotification;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthenticationService
{
    public function __construct(
        protected Otp $otp
    ) {}

    public function register(array $data): array
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

    public function login(array $data, string $type): ?array
    {
        $user = $this->authenticate($data['contact'], $data['password']);
        if (! $user || $user->type !== $type) {
            return null;
        }

        $token = Authentication::getToken($user);
        $userId = $type == 'doctor' ? $user->doctor->id : $user->patient->id;

        return compact('user', 'token', 'userId');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function forgetPassword(array $data): void
    {
        $user = $this->getUser($data['contact']);

        $otpCode = Authentication::generateOtp($user->contact, $this->otp);

        $this->sendOtp($user, $otpCode, isPasswordReset: true);
    }

    public function verifyOtp(array $data): ?string
    {
        $user = $this->getUser($data['contact']);

        if (! $this->validateOtp($user->contact, $data['otp'])) {
            return null;
        }

        $token = $user->createToken('password_reset_'.$user->id, ['reset-password'],
            now()->addMinutes(15))->plainTextToken;

        return $token;
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
        ]);

        $user->tokens()->delete();
    }

    public function verifyContact(array $data, User $user): bool
    {
        return DB::transaction(function () use ($data, $user) {

            if (! $this->validateOtp($user->contact, $data['otp'])) {
                return false;
            }

            $user->update([
                'contact_verified_at' => now(),
            ]);

            return true;
        });
    }

    public function resendOtp(User $user): bool
    {
        if ($user->contact_verified_at) {
            return false;
        }

        $otpCode = Authentication::generateOtp($user->contact, $this->otp);

        $this->sendOtp($user, $otpCode);

        return true;
    }

    private function authenticate(string $contact, string $password): ?User
    {
        $user = $this->getUser($contact);
        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function getUser(string $contact): ?User
    {
        return User::where('contact', $contact)->first();
    }

    private function sendOtp(User $user, string $otp, bool $isPasswordReset = false): void
    {
        $isEmail = filter_var($user->contact, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            $mailable = $isPasswordReset
                ? new ResetPasswordMail($user, $otp)
                : new EmailVerificationMail($user, $otp);

            Mail::to($user->contact)->send($mailable);
        } else {
            $notification = $isPasswordReset
                ? new ResetPasswordSMSNotification($otp)
                : new EmailVerificationSMSNotification($otp);

            $user->notify($notification);
        }
    }

    private function validateOtp(string $contact, string $otp): bool
    {
        return $this->otp->validate($contact, $otp)->status;
    }
}
