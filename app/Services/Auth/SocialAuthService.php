<?php

namespace App\Services\Auth;

use App\Helpers\Authentication;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Models\UserSocialAccount;
use Exception;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

class SocialAuthService
{
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

        $this->validateSocialPayload($provider, $socialUser);

        return DB::transaction(function () use ($provider, $socialUser) {
            $providerId = (string) $socialUser->getId();
            $email = $socialUser->getEmail();
            $name = $socialUser->getName() ?? 'Doctor';

            $socialAccount = UserSocialAccount::with('user.doctor')
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if ($socialAccount) {
                return [
                    'user' => $socialAccount->user,
                    'token' => Authentication::getToken($socialAccount->user),
                ];
            }

            $user = User::where('contact', $email)->first();

            if ($user) {
                $existingProviderAccount = $user->socialAccounts()
                    ->where('provider', $provider)
                    ->first();

                if ($existingProviderAccount && $existingProviderAccount->provider_id !== $providerId) {
                    throw new Exception('Account already linked to a different '.$provider.' identity.');
                }

                $user->socialAccounts()->updateOrCreate(
                    ['provider' => $provider],
                    ['provider_id' => $providerId]
                );

                if (!$user->contact_verified_at) {
                    $user->forceFill(['contact_verified_at' => now()])->save();
                }
            } else {
                $user = User::create([
                    'name' => $name,
                    'contact' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'type' => 'doctor',
                    'is_active' => true,
                    'contact_verified_at' => now(),
                ]);

                $user->doctor()->create();

                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ]);

                Mail::to($user->contact)->queue(new WelcomeMail($user));
            }
            return [
                'user' => $user,
                'token' => Authentication::getToken($user),
            ];
        });
    }

    protected function validateSocialPayload(string $provider, SocialiteUser $socialUser): void
    {
        if (! $socialUser->getId() || ! $socialUser->getEmail()) {
            throw new Exception('Incomplete payload received from '.$provider);
        }

        if ($provider === 'google') {
            $raw = $socialUser->getRaw();
            $isEmailVerified = $raw['email_verified'] ?? false;

            if ($isEmailVerified !== true && $isEmailVerified !== 'true') {
                throw new Exception('Google email is not verified.');
            }
        }
    }
}
