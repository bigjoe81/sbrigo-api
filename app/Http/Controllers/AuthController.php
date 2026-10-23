<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                ['name' => $validated['name']],
            );

            if ($user->wasRecentlyCreated) {
                $user->assignRole(Role::findOrCreate('user', 'web'));
            }

            return $user;
        });

        $this->issueOtp($user);

        return response()->json([
            'message' => 'Se l’indirizzo può essere usato, riceverai un codice per completare l’accesso.',
        ], 202);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $this->issueOtp($user);
        }

        return response()->json([
            'message' => 'Se l’indirizzo è associato a un account, riceverai un codice di accesso.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $storedHash = Cache::get($this->otpCacheKey($validated['email']));
        $submittedHash = hash_hmac('sha256', $validated['code'], (string) config('app.key'));

        if (
            ! $user
            || ! is_string($storedHash)
            || ! hash_equals($storedHash, $submittedHash)
        ) {
            throw ValidationException::withMessages([
                'code' => ['Codice non valido o scaduto.'],
            ]);
        }

        Cache::forget($this->otpCacheKey($validated['email']));

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->fresh()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout effettuato.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    private function issueOtp(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = $this->otpCacheKey($user->email);

        Cache::put(
            $cacheKey,
            hash_hmac('sha256', $code, (string) config('app.key')),
            now()->addMinutes(5),
        );

        try {
            Notification::route('mail', $user->email)
                ->notify(new LoginOtpNotification($code));
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    private function otpCacheKey(string $email): string
    {
        return 'auth:otp:'.hash('sha256', $email);
    }
}
