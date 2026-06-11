<?php

namespace App\Http\Controllers\Api;

use App\Exports\AssetsExport;
use App\Http\Controllers\Controller;
use App\Imports\AssetsImport;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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

        $sortable = ['asset_tag', 'last_name', 'first_name', 'category', 'status', 'created_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = Asset::with(['assignee', 'department'])->orderBy($sort, $dir);

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
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        return response()->json(
            $asset->load(['assignee', 'department', 'histories.user', 'attachments', 'tickets'])
        );
    }

    public function meta(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $counts = Asset::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $statusCounts = [];
        foreach (AssetCategories::STATUSES as $s) {
            $statusCounts[$s] = (int) ($counts[$s] ?? 0);
        }

        return response()->json([
            'categories'    => \App\Models\AssetCategory::orderBy('name')->pluck('name'),
            'statuses'      => AssetCategories::STATUSES,
            'status_counts' => $statusCounts,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeItStaff($request);

        $query = Asset::query()->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Excel::download(new AssetsExport($query), 'assets.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new AssetsImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'created'  => $import->created,
            'rejected' => $import->rejected,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'asset_tag'       => 'required|string|max:255|unique:assets,asset_tag',
            'name'            => 'nullable|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:255',
            'category'        => 'required|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number',
            'status'          => 'nullable|' . AssetCategories::statusRule(),
            'assigned_to'     => 'nullable|exists:users,id',
            'department_id'   => 'nullable|exists:departments,id',
            'location'        => 'nullable|exists:asset_locations,name',
            'assign_date'     => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'purchase_link'   => 'nullable|string|max:2048',
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
            'asset_tag'       => 'sometimes|string|max:255|unique:assets,asset_tag,' . $asset->id,
            'name'            => 'nullable|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:255',
            'category'        => 'sometimes|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number,' . $asset->id,
            'location'        => 'nullable|exists:asset_locations,name',
            'assign_date'     => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'purchase_link'   => 'nullable|string|max:2048',
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
            // History stores holder names (not IDs) so the trail reads naturally.
            $previousHolderName = $asset->assignee?->name;

            if (!empty($data['assigned_to'])) {
                if ((int) $data['assigned_to'] === (int) $asset->assigned_to) {
                    // Re-assigning the same holder: sync department if given, no history noise.
                    if (array_key_exists('department_id', $data)) {
                        $asset->department_id = $data['department_id'];
                        $asset->save();
                    }
                    return;
                }
                $newHolder = User::findOrFail($data['assigned_to']);
                $asset->assigned_to = $newHolder->id;
                if (array_key_exists('department_id', $data)) {
                    $asset->department_id = $data['department_id'];
                }
                $asset->status = 'assigned';
                $asset->save();
                $asset->logHistory($request->user()->id, 'assigned', 'assigned_to', $previousHolderName, $newHolder->name);
            } else {
                if ($asset->assigned_to === null && $asset->status === 'in_stock') {
                    return; // already in stock — nothing to do
                }
                $asset->assigned_to = null;
                $asset->status = 'in_stock';
                $asset->save();
                $asset->logHistory($request->user()->id, 'returned', 'assigned_to', $previousHolderName, null);
            }
        });

        return response()->json($asset->fresh()->load(['assignee', 'department']));
    }

    public function storeAttachments(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $request->validate([
            'attachments'   => 'required|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        $created = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('asset-attachments', 'public');
            $created[] = $asset->attachments()->create([
                'user_id'       => $request->user()->id,
                'filename'      => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'path'          => $path,
            ]);
        }

        return response()->json($created, 201);
    }

    public function destroyAttachment(Request $request, Asset $asset, Attachment $attachment): JsonResponse
    {
        $this->authorizeItStaff($request);
        abort_unless(
            $attachment->attachable_type === Asset::class && $attachment->attachable_id === $asset->id,
            404
        );

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
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
