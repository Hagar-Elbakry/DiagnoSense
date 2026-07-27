<?php 

namespace App\Services\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Auth;
use App\Events\User\UserRegistered;
use Ichtrojan\Otp\Otp;

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
}