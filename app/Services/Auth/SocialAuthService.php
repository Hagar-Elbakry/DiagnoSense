<?php

namespace App\Services\Auth;

use App\Helpers\Authentication;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Models\UserSocialAccount;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

class SocialAuthService
{
    public function __construct(
        protected Otp $otp
    ) {}

    public function getRedirectUrl(string $provider): string
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function handleProviderCallback(string $provider): array
    {
        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        return DB::transaction(function () use ($provider, $socialUser) {
            $account = UserSocialAccount::with('user')
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($account) {
                $user = $account->user;
            } else {
                $user = User::where('contact', $socialUser->getEmail())->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $socialUser->getName(),
                        'contact' => $socialUser->getEmail(),
                        'password' => Hash::make(Str::random(16)),
                        'type' => 'doctor',
                        'is_active' => true,
                    ]);

                    $user->doctor()->create();
                    $user->contact_verified_at = now();
                    $user->save();
                    Mail::to($user->contact)->queue(new WelcomeMail($user));
                }

                $user->socialAccounts()->updateOrCreate(
                    ['provider' => $provider],
                    ['provider_id' => $socialUser->getId()]
                );
            }

            return [
                'user' => $user,
                'token' => Authentication::getToken($user),
            ];
        });
    }
}
