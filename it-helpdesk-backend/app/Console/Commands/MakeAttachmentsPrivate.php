<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MakeAttachmentsPrivate extends Command
{
    protected $signature = 'attachments:make-private';

    protected $description = 'Move attachment files from the public disk to the private (local) disk';

    public function handle(): int
    {
        $moved = $skipped = $missing = 0;

        foreach (Attachment::cursor() as $attachment) {
            if (Storage::disk('local')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
                $skipped++;
                continue;
            }

            if (!Storage::disk('public')->exists($attachment->path)) {
                $this->warn("Missing file for attachment #{$attachment->id}: {$attachment->path}");
                $missing++;
                continue;
            }

            Storage::disk('local')->writeStream(
                $attachment->path,
                Storage::disk('public')->readStream($attachment->path)
            );
            Storage::disk('public')->delete($attachment->path);
            $moved++;
        }

        $this->info("Moved: {$moved}, already private: {$skipped}, missing: {$missing}");

        return self::SUCCESS;
    }
}
