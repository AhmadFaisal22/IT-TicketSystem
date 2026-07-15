<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourceFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-managed downloadable files (e.g. the onboarding IT Resource
 * Application template). Files are addressed by a stable key so the
 * frontend never hardcodes a filename — admins replace the file and
 * every download link stays valid.
 */
class ResourceFileController extends Controller
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function index(): JsonResponse
    {
        return response()->json(
            ResourceFile::with('uploader:id,name')->orderBy('key')->get()
        );
    }

    public function show(string $key): JsonResponse
    {
        return response()->json($this->find($key));
    }

    public function download(string $key): StreamedResponse
    {
        $resource = $this->find($key);
        abort_unless(Storage::disk('local')->exists($resource->path), 404);

        return Storage::disk('local')->download(
            $resource->path,
            $resource->original_name,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    public function store(Request $request, string $key): JsonResponse
    {
        abort_unless(preg_match(self::KEY_PATTERN, $key), 404);

        $request->validate([
            'file' => [
                'required', 'file', 'max:20480',
                'mimes:xlsx,xls,csv,doc,docx,pdf,ppt,pptx,txt,zip',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('resources', 'local');

        $existing = ResourceFile::where('key', $key)->first();
        if ($existing && Storage::disk('local')->exists($existing->path)) {
            Storage::disk('local')->delete($existing->path);
        }

        $resource = ResourceFile::updateOrCreate(
            ['key' => $key],
            [
                'original_name' => $file->getClientOriginalName(),
                'path'          => $path,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $request->user()->id,
            ]
        );

        return response()->json($resource->load('uploader:id,name'), $existing ? 200 : 201);
    }

    private function find(string $key): ResourceFile
    {
        return ResourceFile::with('uploader:id,name')->where('key', $key)->firstOrFail();
    }
}
