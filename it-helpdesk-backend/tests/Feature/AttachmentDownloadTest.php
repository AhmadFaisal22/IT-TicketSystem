<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(User $creator): Ticket
    {
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);

        return Ticket::create([
            'title'         => 'Broken laptop',
            'description'   => 'It will not boot',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $creator->id,
        ]);
    }

    /** Put a file on the given fake disk and create the Attachment row pointing at it. */
    private function attachFile(Ticket|Asset $parent, User $uploader, string $disk = 'local'): Attachment
    {
        $path = 'ticket-attachments/secret.pdf';
        Storage::disk($disk)->put($path, 'pdf-bytes');

        return $parent->attachments()->create([
            'user_id'       => $uploader->id,
            'filename'      => 'secret.pdf',
            'original_name' => 'Q3 report.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 9,
            'path'          => $path,
        ]);
    }

    public function test_guest_cannot_download_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $attachment = $this->attachFile($this->makeTicket($creator), $creator);

        $this->getJson("/api/attachments/{$attachment->id}/download")->assertStatus(401);
    }

    public function test_ticket_creator_can_download_with_forced_download_headers(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $attachment = $this->attachFile($this->makeTicket($creator), $creator);

        Sanctum::actingAs($creator);
        $response = $this->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        $this->assertSame('pdf-bytes', $response->streamedContent());
    }

    public function test_unrelated_user_cannot_download_ticket_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $attachment = $this->attachFile($this->makeTicket($creator), $creator);

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertStatus(403);
    }

    public function test_it_staff_can_download_any_ticket_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $attachment = $this->attachFile($this->makeTicket($creator), $creator);

        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_regular_user_cannot_download_asset_attachment(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->attachFile(Asset::factory()->create(), $staff);

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertStatus(403);
    }

    public function test_it_staff_can_download_asset_attachment(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->attachFile(Asset::factory()->create(), $staff);

        Sanctum::actingAs($staff);
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_new_ticket_attachments_are_stored_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->post('/api/tickets', [
            'title'         => 'Broken laptop',
            'description'   => 'It will not boot',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'attachments'   => [UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')],
        ])->assertStatus(201);

        $path = Attachment::sole()->path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_new_asset_attachments_are_stored_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $asset = Asset::factory()->create();

        $this->post("/api/assets/{$asset->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
        ])->assertStatus(201);

        $path = Attachment::sole()->path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_legacy_attachment_left_on_public_disk_still_downloads(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['role' => 'user']);
        $attachment = $this->attachFile($this->makeTicket($creator), $creator, 'public');

        Sanctum::actingAs($creator);
        $response = $this->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        $this->assertSame('pdf-bytes', $response->streamedContent());
    }
}
