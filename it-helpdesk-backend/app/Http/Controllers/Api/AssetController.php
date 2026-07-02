<?php

namespace App\Http\Controllers\Api;

use App\Exports\AssetsExport;
use App\Http\Controllers\Controller;
use App\Imports\AssetsImport;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate($this->filterRules());

        $sortable = ['asset_tag', 'last_name', 'first_name', 'category', 'status', 'created_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = Asset::with(['assignee', 'department'])->orderBy($sort, $dir);
        $this->applyFilters($query, $f);

        return response()->json($query->paginate(15));
    }

    /** Validation rules for the asset list/export filters (shared by index + export). */
    private function filterRules(): array
    {
        return [
            'status'        => 'nullable|' . AssetCategories::statusRule(),
            'category'      => 'nullable|' . AssetCategories::categoryRule(),
            'department_id' => 'nullable|integer|exists:departments,id',
            'assigned_to'   => 'nullable|integer|exists:users,id',
            'search'        => 'nullable|string|max:255',
        ];
    }

    /** Apply the shared list/export filters to an asset query. */
    private function applyFilters(Builder $query, array $f): void
    {
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
            $like = "%{$search}%";
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', 'like', $like)
                  ->orWhere('asset_tag', 'like', $like)
                  ->orWhere('serial_number', 'like', $like)
                  ->orWhere('last_name', 'like', $like)
                  ->orWhere('first_name', 'like', $like)
                  // Also match a full name typed as one term, in either order.
                  // `||` + COALESCE works on both PostgreSQL and SQLite.
                  ->orWhereRaw("COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') like ?", [$like])
                  ->orWhereRaw("COALESCE(last_name, '') || ' ' || COALESCE(first_name, '') like ?", [$like]);
            });
        }
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        return response()->json(
            $asset->load(['assignee', 'department', 'histories.user', 'attachments', 'tickets'])
        );
    }

    public function meta(Request $request): JsonResponse
    {
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
        $f = $request->validate($this->filterRules());

        $query = Asset::query()->orderByDesc('created_at');
        $this->applyFilters($query, $f);

        return Excel::download(new AssetsExport($query), 'assets.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new AssetsImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'created'  => $import->created,
            'rejected' => $import->rejected,
        ]);
    }

    /**
     * Suggest the next asset tag: take the most recent tag ending in a number,
     * then increment the highest numeric suffix among tags sharing its prefix.
     * Padding keeps the width of the current highest suffix (US02-...-038 -> 039)
     * and grows naturally past it (999 -> 1000).
     */
    public function nextTag(): JsonResponse
    {
        $latest = Asset::orderByDesc('id')
            ->pluck('asset_tag')
            ->first(fn ($tag) => preg_match('/\d+$/', (string) $tag));

        if (!$latest) {
            return response()->json(['suggested' => null]);
        }

        preg_match('/^(.*?)(\d+)$/', $latest, $m);
        $prefix = $m[1];

        $max = 0;
        $width = strlen($m[2]);
        foreach (Asset::where('asset_tag', 'like', $prefix . '%')->pluck('asset_tag') as $tag) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $tag, $mm) && (int) $mm[1] > $max) {
                $max = (int) $mm[1];
                $width = strlen($mm[1]);
            }
        }

        return response()->json([
            'suggested' => $prefix . str_pad($max + 1, $width, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Create N assets sharing common fields, with tags counted up consecutively
     * from the starting tag. All-or-nothing: any tag collision rejects the batch.
     * Per-unit fields (serial number, assignee) are intentionally not accepted —
     * they are filled in per asset afterwards.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_tag'       => ['required', 'string', 'max:255', 'regex:/\d+$/'],
            'quantity'        => 'required|integer|min:1|max:50',
            'name'            => 'nullable|string|max:255',
            'category'        => 'required|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|exists:manufacturers,name',
            'model'           => 'nullable|string|max:255',
            'department_id'   => 'nullable|exists:departments,id',
            'location'        => 'nullable|exists:asset_locations,name',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'purchase_link'   => 'nullable|string|max:2048',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        preg_match('/^(.*?)(\d+)$/', $data['asset_tag'], $m);
        $prefix = $m[1];
        $start = (int) $m[2];
        $width = strlen($m[2]);

        $tags = [];
        for ($i = 0; $i < $data['quantity']; $i++) {
            $tags[] = $prefix . str_pad($start + $i, $width, '0', STR_PAD_LEFT);
        }

        $taken = Asset::whereIn('asset_tag', $tags)->pluck('asset_tag');
        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'asset_tag' => ['Asset tags already in use: ' . $taken->implode(', ')],
            ]);
        }

        $common = collect($data)->except(['asset_tag', 'quantity'])->all();

        $assets = DB::transaction(function () use ($tags, $common, $request) {
            return collect($tags)->map(function ($tag) use ($common, $request) {
                $asset = Asset::create($common + ['asset_tag' => $tag]);
                $asset->logHistory($request->user()->id, 'created');
                return $asset;
            });
        });

        return response()->json([
            'created'   => $assets->count(),
            'first_tag' => $tags[0],
            'last_tag'  => end($tags),
            'ids'       => $assets->pluck('id'),
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_tag'       => 'required|string|max:255|unique:assets,asset_tag',
            'name'            => 'nullable|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:255',
            'category'        => 'required|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|exists:manufacturers,name',
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
        $data = $request->validate([
            'version'         => 'required|integer',
            'asset_tag'       => 'sometimes|string|max:255|unique:assets,asset_tag,' . $asset->id,
            'name'            => 'nullable|string|max:255',
            'last_name'       => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:255',
            'category'        => 'sometimes|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|exists:manufacturers,name',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number,' . $asset->id,
            'location'        => 'nullable|exists:asset_locations,name',
            'assign_date'     => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'purchase_link'   => 'nullable|string|max:2048',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        $expectedVersion = $data['version'];
        unset($data['version']);

        DB::transaction(function () use ($asset, $data, $expectedVersion, $request) {
            $asset->optimisticUpdate($data, $expectedVersion);
            $asset->logHistory($request->user()->id, 'updated');
        });

        return response()->json($asset->load(['assignee', 'department']));
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();
        return response()->json(null, 204);
    }

    public function assign(Request $request, Asset $asset): JsonResponse
    {
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
                $asset->assign_date = now()->toDateString();
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
        $request->validate([
            'attachments'   => 'required|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        $created = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('asset-attachments', 'local');
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
        abort_unless(
            $attachment->attachable_type === Asset::class && $attachment->attachable_id === $asset->id,
            404
        );

        // Legacy rows may still live on the public disk
        Storage::disk('local')->delete($attachment->path);
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Asset $asset): JsonResponse
    {
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
