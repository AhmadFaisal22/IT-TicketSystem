<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\AssetCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    /** Reject anyone who is not IT staff/admin. Called at the top of every action. */
    private function authorizeItStaff(Request $request): void
    {
        abort_unless($request->user()->isItStaff(), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $f = $request->validate([
            'status'        => 'nullable|' . AssetCategories::statusRule(),
            'category'      => 'nullable|' . AssetCategories::categoryRule(),
            'department_id' => 'nullable|integer|exists:departments,id',
            'assigned_to'   => 'nullable|integer|exists:users,id',
            'search'        => 'nullable|string|max:255',
        ]);

        $query = Asset::with(['assignee', 'department'])->orderByDesc('created_at');

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (!empty($f['category'])) {
            $query->where('category', $f['category']);
        }
        if (!empty($f['department_id'])) {
            $query->where('department_id', $f['department_id']);
        }
        if (!empty($f['assigned_to'])) {
            $query->where('assigned_to', $f['assigned_to']);
        }
        if (!empty($f['search'])) {
            $search = $f['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        return response()->json(
            $asset->load(['assignee', 'department', 'histories.user', 'attachments', 'tickets'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'category'        => 'required|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number',
            'status'          => 'nullable|' . AssetCategories::statusRule(),
            'assigned_to'     => 'nullable|exists:users,id',
            'department_id'   => 'nullable|exists:departments,id',
            'location'        => 'nullable|string|max:255',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        $asset = DB::transaction(function () use ($data, $request) {
            $asset = Asset::create($data);
            $asset->logHistory($request->user()->id, 'created');
            return $asset;
        });

        return response()->json($asset->load(['assignee', 'department']), 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'category'        => 'sometimes|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number,' . $asset->id,
            'location'        => 'nullable|string|max:255',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $asset->update($data);
            $asset->logHistory($request->user()->id, 'updated');
        });

        return response()->json($asset->load(['assignee', 'department']));
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $asset->delete();
        return response()->json(null, 204);
    }

    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'assigned_to'   => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $previousHolder = $asset->assigned_to;

            if (!empty($data['assigned_to'])) {
                $asset->assigned_to = $data['assigned_to'];
                if (array_key_exists('department_id', $data)) {
                    $asset->department_id = $data['department_id'];
                }
                $asset->status = 'assigned';
                $asset->save();
                $asset->logHistory($request->user()->id, 'assigned', 'assigned_to', (string) $previousHolder, (string) $data['assigned_to']);
            } else {
                $asset->assigned_to = null;
                $asset->status = 'in_stock';
                $asset->save();
                $asset->logHistory($request->user()->id, 'returned', 'assigned_to', (string) $previousHolder, null);
            }
        });

        return response()->json($asset->fresh()->load(['assignee', 'department']));
    }

    public function updateStatus(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate(['status' => 'required|' . AssetCategories::statusRule()]);
        $old = $asset->status;

        DB::transaction(function () use ($asset, $data, $old, $request) {
            $asset->status = $data['status'];
            // Returning to stock via status also clears the holder.
            if ($data['status'] === 'in_stock') {
                $asset->assigned_to = null;
            }
            $asset->save();
            $asset->logHistory($request->user()->id, 'status_changed', 'status', $old, $data['status']);
        });

        return response()->json($asset->fresh()->load(['assignee', 'department']));
    }
}
