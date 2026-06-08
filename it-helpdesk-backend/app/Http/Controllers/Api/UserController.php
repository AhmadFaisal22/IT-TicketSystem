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
        abort_unless($request->user()->isAdmin(), 403);

        $query = User::with('department')->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return response()->json($query->paginate(50));
    }

    public function itStaff(): JsonResponse
    {
        return response()->json(
            User::where('role', 'it_staff')->where('active', true)->get(['id', 'name', 'avatar'])
        );
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate(['role' => 'required|in:admin,it_staff,user']);
        $user->update($data);
        return response()->json($user);
    }

    public function updateDepartment(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate(['department_id' => 'nullable|exists:departments,id']);
        $user->update($data);
        return response()->json($user->load('department'));
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $user->update(['active' => !$user->active]);
        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

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
        ]);

        return response()->json($user->load('department'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

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
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->id === $request->user()->id, 422, 'Cannot delete your own account.');

        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }
}
