<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    public function redirectToMicrosoft(): JsonResponse
    {
        $url = Socialite::driver('azure')->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    public function handleGoogleCallback(): JsonResponse
    {
        $socialUser = Socialite::driver('google')->stateless()->user();
        return $this->loginOrRegister('google_id', $socialUser);
    }

    public function handleMicrosoftCallback(): JsonResponse
    {
        $socialUser = Socialite::driver('azure')->stateless()->user();
        return $this->loginOrRegister('microsoft_id', $socialUser);
    }

    private function loginOrRegister(string $field, $socialUser): JsonResponse
    {
        $user = User::firstOrCreate(
            [$field => $socialUser->getId()],
            [
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'active' => true,
                'email_verified_at' => now(), // SSO provider already verified the address
            ]
        );

        if (!$user->active) {
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('department'),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Please verify your email address before signing in. Check your inbox for the verification link.',
            ], 403);
        }

        if (!$user->active) {
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->load('department'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('department'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $request->validate(['locale' => 'required|in:en,zh']);
        $request->user()->update(['locale' => $request->locale]);
        return response()->json(['locale' => $request->locale]);
    }
}
