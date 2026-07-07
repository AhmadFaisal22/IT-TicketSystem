<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('department')->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('search')) {
            $like = '%' . mb_strtolower(trim($request->search)) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        return response()->json($query->paginate(50));
    }

    public function itStaff(): JsonResponse
    {
        // Tickets are assigned by role (it_staff/admin), whatever department they sit in.
        return response()->json(
            User::whereIn('role', ['it_staff', 'admin'])
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'avatar'])
        );
    }

    /** Search active users for asset assignment (IT staff only). */
    public function assignable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search'      => 'nullable|string|max:100',
            'limit'       => 'nullable|integer|min:1|max:50',
            'selected_id' => 'nullable|integer|exists:users,id',
        ]);

        $limit = (int) ($data['limit'] ?? 25);
        $search = trim((string) ($data['search'] ?? ''));

        $query = User::where('active', true);

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(role) LIKE ?', [$like]);
            });
        }

        $users = $query
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role', 'avatar']);

        if (!empty($data['selected_id']) && !$users->contains('id', (int) $data['selected_id'])) {
            $selectedUser = User::where('active', true)
                ->whereKey($data['selected_id'])
                ->first(['id', 'name', 'email', 'role', 'avatar']);

            if ($selectedUser) {
                $users->prepend($selectedUser);
            }
        }

        return response()->json($users->values());
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['role' => 'required|in:admin,it_staff,user']);
        $user->update($data);
        return response()->json($user);
    }

    public function updateDepartment(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['department_id' => 'nullable|exists:departments,id']);
        $user->update($data);
        return response()->json($user->load('department'));
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $user->update(['active' => !$user->active]);
        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'role'          => 'required|in:admin,it_staff,user',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'department_id' => $data['department_id'] ?? null,
            'active'        => true,
            'email_verified_at' => now(), // admin-created accounts are trusted, no self-verification needed
        ]);

        return response()->json($user->load('department'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'password'      => 'nullable|string|min:8',
            'role'          => 'required|in:admin,it_staff,user',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $updateData = [
            'name'          => $data['name'],
            'email'         => $data['email'],
            'role'          => $data['role'],
            'department_id' => $data['department_id'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);
        return response()->json($user->load('department'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Cannot delete your own account.');

        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }
}
