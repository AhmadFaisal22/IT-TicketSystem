<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-managed dropdown options for assets (categories and locations),
 * mirroring how departments are managed.
 */
class AssetOptionController extends Controller
{
    private function authorizeItStaff(Request $request): void
    {
        abort_unless($request->user()->isItStaff(), 403);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }

    // ── Categories ───────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);
        return response()->json(AssetCategory::orderBy('name')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:asset_categories,name',
            'name_zh' => 'nullable|string|max:100',
        ]);
        return response()->json(AssetCategory::create($data), 201);
    }

    public function updateCategory(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100|unique:asset_categories,name,' . $assetCategory->id,
            'name_zh' => 'nullable|string|max:100',
        ]);
        $assetCategory->update($data);
        return response()->json($assetCategory);
    }

    public function destroyCategory(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $this->authorizeAdmin($request);
        $assetCategory->delete();
        return response()->json(null, 204);
    }

    // ── Locations ────────────────────────────────────────────────

    public function locations(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);
        return response()->json(AssetLocation::orderBy('name')->get());
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:asset_locations,name',
            'name_zh' => 'nullable|string|max:100',
        ]);
        return response()->json(AssetLocation::create($data), 201);
    }

    public function updateLocation(Request $request, AssetLocation $assetLocation): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100|unique:asset_locations,name,' . $assetLocation->id,
            'name_zh' => 'nullable|string|max:100',
        ]);
        $assetLocation->update($data);
        return response()->json($assetLocation);
    }

    public function destroyLocation(Request $request, AssetLocation $assetLocation): JsonResponse
    {
        $this->authorizeAdmin($request);
        $assetLocation->delete();
        return response()->json(null, 204);
    }
}
