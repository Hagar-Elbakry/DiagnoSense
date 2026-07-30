<?php 

namespace App\Services\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Auth;
use App\Events\User\UserRegistered;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Hash;
use App\Mail\ResetPasswordMail;
use App\Mail\EmailVerificationMail;
use App\Notifications\ResetPasswordSMSNotification;
use App\Notifications\EmailVerificationSMSNotification;
use Illuminate\Support\Facades\Mail;

class AuthenticationService
{
    public function __construct(
        protected Otp $otp
    ) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->doctor()->create();

            $token = Auth::getToken($user);
            $userId = $user->doctor->id;
            $otpCode = Auth::generateOtp($user->contact, $this->otp);

            UserRegistered::dispatch($user, $otpCode);

            return compact('user', 'token', 'userId');
        });
    }

    public function login(array $data, string $type): ?array
    {
        $user = $this->authenticate($data['contact'], $data['password']);
        if (! $user || $user->type !== $type) {
            return null;
        }

        $token = Auth::getToken($user);
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

        $otpCode = Auth::generateOtp($user->contact, $this->otp);

        $this->sendOtp($user, $otpCode, isPasswordReset: true);
    }

    public function verifyOtp(array $data): ?string
    {
        $user = $this->getUser($data['contact']);

        if (!$this->validateOtp($user->contact, $data['otp'])) {
            return null;
        }

        $token = $user->createToken('password_reset_'.$user->id, ['reset-password'],
            now()->addMinutes(15))->plainTextToken;

        return $token;
    }

    public function resetPassword(User $user, string $password) : void
    {
        $user->update([
            'password' => $password
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

        $otpCode = Auth::generateOtp($user->contact, $this->otp);

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