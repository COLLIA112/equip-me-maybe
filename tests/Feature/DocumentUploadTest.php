<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function setUpLab(): array
    {
        Storage::fake('local');
        $lab = Lab::factory()->create();

        return [User::factory()->labManager($lab)->create(), Equipment::factory()->for($lab)->create()];
    }

    private function payload(UploadedFile $file): array
    {
        return ['title' => 'Operating manual', 'type' => 'manual', 'is_required' => 1, 'file' => $file];
    }

    public function test_valid_pdf_is_stored_on_the_private_disk(): void
    {
        [$manager, $item] = $this->setUpLab();

        $this->actingAs($manager)
            ->post(route('documents.store', $item), $this->payload(UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')))
            ->assertSessionHasNoErrors();

        $document = $item->documents()->firstOrFail();
        $this->assertTrue($document->is_required);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        [$manager, $item] = $this->setUpLab();

        $this->actingAs($manager)
            ->post(route('documents.store', $item), $this->payload(UploadedFile::fake()->create('photo.png', 100, 'image/png')))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_file_over_5mb_is_rejected(): void
    {
        [$manager, $item] = $this->setUpLab();

        $this->actingAs($manager)
            ->post(route('documents.store', $item), $this->payload(UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')))
            ->assertSessionHasErrors('file');
    }

    public function test_manager_of_another_lab_cannot_upload(): void
    {
        [, $item] = $this->setUpLab();
        $outsider = User::factory()->labManager(Lab::factory()->create())->create();

        $this->actingAs($outsider)
            ->post(route('documents.store', $item), $this->payload(UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')))
            ->assertForbidden();
    }
}
