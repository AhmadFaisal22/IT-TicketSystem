<?php

namespace Tests\Feature;

use App\Models\ResourceFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceFileTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'onboarding_template';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function uploadAs(User $actor, ?UploadedFile $file = null)
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/resources/' . self::KEY, [
            'file' => $file ?? UploadedFile::fake()->create('IT Resource Application.xlsx', 120),
        ]);
    }

    public function test_admin_can_upload_a_resource_file(): void
    {
        $this->uploadAs($this->admin())
            ->assertCreated()
            ->assertJsonPath('key', self::KEY)
            ->assertJsonPath('original_name', 'IT Resource Application.xlsx');

        $resource = ResourceFile::where('key', self::KEY)->firstOrFail();
        Storage::disk('local')->assertExists($resource->path);
    }

    public function test_replacing_updates_row_and_deletes_old_file(): void
    {
        $this->uploadAs($this->admin())->assertCreated();
        $old = ResourceFile::where('key', self::KEY)->firstOrFail()->path;

        $this->uploadAs($this->admin(), UploadedFile::fake()->create('New Template.xlsx', 80))
            ->assertOk()
            ->assertJsonPath('original_name', 'New Template.xlsx');

        $this->assertSame(1, ResourceFile::where('key', self::KEY)->count());
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists(ResourceFile::where('key', self::KEY)->firstOrFail()->path);
    }

    public function test_non_admin_cannot_upload(): void
    {
        $this->uploadAs($this->user())->assertStatus(403);

        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $this->postJson('/api/resources/' . self::KEY, [
            'file' => UploadedFile::fake()->create('x.xlsx', 10),
        ])->assertStatus(403);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        $this->uploadAs($this->admin(), UploadedFile::fake()->create('evil.exe', 10))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_malformed_key(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/resources/Bad%20Key!', [
            'file' => UploadedFile::fake()->create('x.xlsx', 10),
        ])->assertStatus(404);
    }

    public function test_any_logged_in_user_can_read_metadata_and_download(): void
    {
        $this->uploadAs($this->admin())->assertCreated();

        Sanctum::actingAs($this->user());

        $this->getJson('/api/resources/' . self::KEY)
            ->assertOk()
            ->assertJsonPath('original_name', 'IT Resource Application.xlsx');

        $this->get('/api/resources/' . self::KEY . '/download')
            ->assertOk()
            ->assertDownload('IT Resource Application.xlsx');
    }

    public function test_metadata_and_download_return_404_when_not_uploaded(): void
    {
        Sanctum::actingAs($this->user());

        $this->getJson('/api/resources/' . self::KEY)->assertNotFound();
        $this->getJson('/api/resources/' . self::KEY . '/download')->assertNotFound();
    }

    public function test_guests_cannot_access_resources(): void
    {
        $this->getJson('/api/resources/' . self::KEY)->assertUnauthorized();
        $this->getJson('/api/resources/' . self::KEY . '/download')->assertUnauthorized();
        $this->postJson('/api/resources/' . self::KEY)->assertUnauthorized();
    }

    public function test_resource_list_is_admin_only(): void
    {
        $this->uploadAs($this->admin())->assertCreated();

        $this->getJson('/api/resources')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($this->user());
        $this->getJson('/api/resources')->assertStatus(403);
    }
}
