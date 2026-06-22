<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\Manufacturer;
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

    // ── Manufacturers ────────────────────────────────────────────

    public function manufacturers(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);
        return response()->json(Manufacturer::orderBy('name')->get());
    }

    public function storeManufacturer(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateManufacturer($request);
        $data['status'] ??= 'active';
        return response()->json(Manufacturer::create($data), 201);
    }

    public function updateManufacturer(Request $request, Manufacturer $manufacturer): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateManufacturer($request, $manufacturer->id);
        $manufacturer->update($data);
        return response()->json($manufacturer);
    }

    public function destroyManufacturer(Request $request, Manufacturer $manufacturer): JsonResponse
    {
        $this->authorizeAdmin($request);
        $manufacturer->delete();
        return response()->json(null, 204);
    }

    /** @return array<string,mixed> */
    private function validateManufacturer(Request $request, ?int $ignoreId = null): array
    {
        $nameRule = 'string|max:255|unique:manufacturers,name' . ($ignoreId ? ",{$ignoreId}" : '');

        return $request->validate([
            'name'              => ($ignoreId ? 'sometimes|' : 'required|') . $nameRule,
            'short_name'        => 'nullable|string|max:255',
            'contact'           => 'nullable|string|max:255',
            'support_phone'     => 'nullable|string|max:255',
            'support_email'     => 'nullable|email|max:255',
            'country_of_origin' => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:10000',
            'status'            => 'nullable|in:active,inactive',
        ]);
    }
}
