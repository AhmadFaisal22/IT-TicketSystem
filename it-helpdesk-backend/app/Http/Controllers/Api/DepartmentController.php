<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Department::where('active', true)->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:departments',
            'name_zh' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        return response()->json(Department::create($data), 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100|unique:departments,name,' . $department->id,
            'name_zh' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $department->update($data);
        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->update(['active' => false]);
        return response()->json(null, 204);
    }
}
