<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SlaPolicy::with('department')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isItStaff(), 403);

        $data = $request->validate([
            'department_id'    => 'nullable|exists:departments,id',
            'priority'         => 'required|in:low,medium,high,critical',
            'response_hours'   => 'required|integer|min:1',
            'resolution_hours' => 'required|integer|min:1',
        ]);

        $policy = SlaPolicy::updateOrCreate(
            ['department_id' => $data['department_id'] ?? null, 'priority' => $data['priority']],
            $data
        );

        return response()->json($policy->load('department'), 201);
    }

    public function destroy(SlaPolicy $slaPolicy): JsonResponse
    {
        abort_unless(request()->user()->isItStaff(), 403);
        $slaPolicy->delete();
        return response()->json(null, 204);
    }
}
