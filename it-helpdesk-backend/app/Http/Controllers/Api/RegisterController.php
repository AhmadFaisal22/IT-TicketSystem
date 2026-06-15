<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /** Verification links are valid for this many minutes. */
    private const TOKEN_TTL_MINUTES = 60;

    /** Email domains allowed to self-register, e.g. ['segsolar.com', 'helpdesk.local']. */
    private function allowedDomains(): array
    {
        return (array) config('app.registration_domains', ['segsolar.com']);
    }

    /**
     * Public department list + allowed email domains for the registration form (no auth).
     */
    public function departments(): JsonResponse
    {
        $departments = Department::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_zh']);

        return response()->json([
            'departments' => $departments,
            'domains'     => $this->allowedDomains(),
        ]);
    }

    /**
     * Self-registration. Creates an unverified user and emails a verification link.
     */
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $endsWith = 'ends_with:' . implode(',', array_map(fn ($d) => '@' . $d, $this->allowedDomains()));

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'email'         => ['required', 'email', $endsWith, 'unique:users,email'],
            'password'      => 'required|string|min:8',
            'department_id' => 'required|integer|exists:departments,id',
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => $data['password'], // hashed via model cast
            'role'          => 'user',
            'department_id' => $data['department_id'],
            'active'        => true,
            'email_verified_at' => null,
        ]);

        $this->sendVerificationLink($user);

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 201);
    }

    /**
     * Confirm an email-verification token issued by register().
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->email_verified_at) {
            return response()->json(['message' => 'Email already verified. You can sign in.']);
        }

        $record = DB::table('email_verification_tokens')->where('email', $data['email'])->first();

        if (!$record || !Hash::check($data['token'], $record->token)) {
            return response()->json(['message' => 'This verification link is invalid or has expired.'], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(self::TOKEN_TTL_MINUTES)->isPast()) {
            DB::table('email_verification_tokens')->where('email', $data['email'])->delete();
            return response()->json(['message' => 'This verification link is invalid or has expired.'], 422);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('email_verification_tokens')->where('email', $data['email'])->delete();

        return response()->json(['message' => 'Email verified successfully. You can now sign in.']);
    }

    /**
     * Issue a fresh token and email the verification link.
     */
    private function sendVerificationLink(User $user): void
    {
        $plainToken = Str::random(64);

        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($plainToken), 'created_at' => now()],
        );

        $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $url = $frontend . '/verify-email?token=' . $plainToken . '&email=' . urlencode($user->email);

        $user->notify(new VerifyEmailNotification($url));
    }
}
