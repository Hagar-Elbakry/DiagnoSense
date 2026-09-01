<?php

namespace App\Services\Auth;

use App\Events\User\UserRegistered;
use App\Helpers\Authentication;
use App\Models\User;
use App\Models\UserSocialAccount;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

class SocialAuthService
{
    protected const SUPPORTED_PROVIDERS = ['google'];

    protected function validateProvider(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new Exception("Unsupported provider: {$provider}");
        }
    }

    public function getRedirectUrl(string $provider): string
    {
        $this->validateProvider($provider);

        $statePayload = json_encode([
            'nonce' => Str::random(32),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $state = Crypt::encryptString($statePayload);

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    public function handleProviderCallback(string $provider, ?string $state): array
    {
        $this->validateProvider($provider);

        if (! $state) {
            throw new Exception('OAuth state is missing.');
        }

        try {
            $decrypted = json_decode(Crypt::decryptString($state), true);
        } catch (Exception $e) {
            throw new Exception('Invalid OAuth state signature.');
        }

        if (! isset($decrypted['expires_at']) || now()->timestamp > $decrypted['expires_at']) {
            throw new Exception('OAuth state has expired.');
        }

        $nonce = $decrypted['nonce'] ?? null;
        if (! $nonce || ! Cache::add("used_oauth_nonce_{$nonce}", true, now()->addMinutes(15))) {
            throw new Exception('OAuth state has already been used.');
        }

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
                $user = $socialAccount->user;
            } else {
                $user = User::where('contact', $email)->first();

                if ($user) {
                    if ($user->type !== 'doctor') {
                        throw new Exception('Google login is only available for doctors.');
                    }

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

                    if (! $user->contact_verified_at) {
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

                    event(new UserRegistered($user));
                }
            }

            if ($user->type !== 'doctor') {
                throw new Exception('Google login is only available for doctors.');
            }

            return [
                'user' => $user,
                'token' => Authentication::getToken($user),
            ];
        });
    }

    public function createExchangeCode(User $user, string $token): string
    {
        $code = Str::random(40);

        Cache::put("social_exchange_{$code}", [
            'user_id' => $user->id,
            'token' => $token,
        ], now()->addSeconds(60));

        return $code;
    }

    public function exchangeCode(string $code): array
    {
        $lock = Cache::lock("lock_exchange_{$code}", 5);

        if (! $lock->get()) {
            throw new Exception('Exchange code is currently being processed.');
        }

        try {
            $data = Cache::pull("social_exchange_{$code}");

            if (! $data) {
                throw new Exception('Invalid or expired exchange code.');
            }

            $user = User::with('doctor')->find($data['user_id']);

            if (
                ! $user ||
                $user->type !== 'doctor' ||
                ! $user->doctor
            ) {
                throw new Exception('User is not eligible');
            }

            return [
                'user' => $user,
                'doctor_id' => $user->doctor->id,
                'token' => $data['token'],
            ];
        } finally {
            $lock->release();
        }
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
