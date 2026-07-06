<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Trigger the built-in broker (stores token, sends notification)
        Password::sendResetLink($request->only('email'));

        // Always respond with success to prevent email enumeration
        return response()->json([
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => ['required', 'string', 'min:8', 'confirmed', 'regex:/^\S+$/'],
            'password_confirmation' => 'required|string',
        ], [
            'password.regex' => 'Password must not contain spaces.',
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                // Invalidate all existing Sanctum tokens so old sessions cannot be reused
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => match ($status) {
                    Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
                    Password::INVALID_USER  => 'No account found with that email address.',
                    default                 => 'Unable to reset password. Please try again.',
                },
            ], 422);
        }

        return response()->json(['message' => 'Password has been reset successfully. You can now sign in.']);
    }
}
