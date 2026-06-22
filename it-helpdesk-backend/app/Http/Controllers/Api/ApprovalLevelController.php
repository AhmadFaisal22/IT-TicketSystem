<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLevel;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalLevelController extends Controller
{
    // Returns all levels grouped by department_id
    public function index(): JsonResponse
    {
        $levels = ApprovalLevel::with(['department', 'approver'])
            ->orderBy('department_id')
            ->orderBy('level_order')
            ->get();

        return response()->json($levels);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'department_id' => 'required|exists:departments,id',
            'level_order'   => 'required|integer|min:1|max:10',
            'approver_id'   => 'required|exists:users,id',
            'is_active'     => 'boolean',
        ]);

        $level = ApprovalLevel::create($data);

        return response()->json($level->load(['department', 'approver']), 201);
    }

    public function update(Request $request, ApprovalLevel $approvalLevel): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'level_order' => 'sometimes|integer|min:1|max:10',
            'approver_id' => 'sometimes|exists:users,id',
            'is_active'   => 'sometimes|boolean',
        ]);

        $approvalLevel->update($data);

        return response()->json($approvalLevel->load(['department', 'approver']));
    }

    public function destroy(Request $request, ApprovalLevel $approvalLevel): JsonResponse
    {
        $approvalLevel->delete();
        return response()->json(null, 204);
    }

    // Reorder: accepts [{id, level_order}]
    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'levels'              => 'required|array',
            'levels.*.id'         => 'required|exists:approval_levels,id',
            'levels.*.level_order' => 'required|integer|min:1',
        ])['levels'];

        foreach ($items as $item) {
            ApprovalLevel::where('id', $item['id'])->update(['level_order' => $item['level_order']]);
        }

        return response()->json(['ok' => true]);
    }
}
