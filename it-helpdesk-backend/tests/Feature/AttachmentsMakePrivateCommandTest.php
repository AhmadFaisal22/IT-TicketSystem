<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsMakePrivateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttachment(string $path): Attachment
    {
        $creator = User::factory()->create(['role' => 'user']);
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $ticket = Ticket::create([
            'title'         => 'Broken laptop',
            'description'   => 'It will not boot',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $creator->id,
        ]);

        return $ticket->attachments()->create([
            'user_id'       => $creator->id,
            'filename'      => basename($path),
            'original_name' => 'report.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 9,
            'path'          => $path,
        ]);
    }

    public function test_moves_public_files_to_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $attachment = $this->makeAttachment('ticket-attachments/legacy.pdf');
        Storage::disk('public')->put($attachment->path, 'pdf-bytes');

        $this->artisan('attachments:make-private')->assertSuccessful();

        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertMissing($attachment->path);
        $this->assertSame('pdf-bytes', Storage::disk('local')->get($attachment->path));
    }

    public function test_skips_files_already_private_and_reports_missing_ones(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $private = $this->makeAttachment('ticket-attachments/already.pdf');
        Storage::disk('local')->put($private->path, 'pdf-bytes');
        $this->makeAttachment('ticket-attachments/gone.pdf');

        $this->artisan('attachments:make-private')->assertSuccessful();

        Storage::disk('local')->assertExists($private->path);
    }
}
