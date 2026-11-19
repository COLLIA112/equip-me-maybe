<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role): array
    {
        $dept = Department::factory()->create();
        $lab = Lab::factory()->for($dept)->create();
        $item = Equipment::factory()->for($lab)->create();
        $user = User::factory()->create(['role' => Role::from($role), 'department_id' => $dept->id,
            'lab_id' => $role === 'lab_manager' ? $lab->id : null]);

        return [$user, $item, $lab, $dept];
    }

    public static function accessCases(): array
    {
        $cases = [];
        foreach (Role::cases() as $role) {
            $value = $role->value;
            $borrower = in_array($value, ['student', 'faculty'], true);
            $manager = $value === 'lab_manager';
            $head = $value === 'department_head';
            $auditor = $value === 'security_auditor';
            $expectations = [
                'dashboard' => $auditor ? 403 : ($head ? 302 : 200),
                'profile.edit' => $auditor ? 403 : 200,
                'documents.checklist' => $borrower ? 200 : 403,
                'equipment.index' => $auditor ? 403 : 200,
                'equipment.create' => $manager || $head ? 200 : 403,
                'reservations.index' => $borrower ? 200 : 403,
                'reservations.queue' => $manager ? 200 : 403,
                'maintenance.index' => $manager || $head ? 200 : 403,
                'department.index' => $head ? 200 : 403,
                'department.users' => $head ? 200 : 403,
                'equipment.show' => $auditor ? 403 : 200,
                'equipment.edit' => $manager || $head ? 200 : 403,
                'documents.index' => $manager ? 200 : 403,
                'reservations.create' => $borrower ? 200 : 403,
            ];
            foreach ($expectations as $route => $status) {
                $cases[$value.' '.$route] = [$value, $route, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('accessCases')]
    public function test_all_five_roles_have_expected_page_access(string $role, string $route, int $expected): void
    {
        [$user, $item] = $this->fixture($role);
        $parameter = str_starts_with($route, 'equipment.') && in_array($route, ['equipment.show', 'equipment.edit'], true)
            || in_array($route, ['documents.index', 'reservations.create'], true) ? $item : [];
        $this->actingAs($user)->get(route($route, $parameter))->assertStatus($expected);
    }

    public static function mutationRoles(): array
    {
        $cases = [];
        foreach (Role::cases() as $role) {
            $cases[$role->value] = [$role->value];
        }

        return $cases;
    }

    #[DataProvider('mutationRoles')]
    public function test_equipment_creation_requires_management_and_valid_input(string $role): void
    {
        [$user, $item, $lab] = $this->fixture($role);
        $allowed = in_array($role, ['lab_manager', 'department_head'], true);
        $response = $this->actingAs($user)->post(route('equipment.store'), ['lab_id' => $lab->id, 'name' => '', 'serial_number' => '']);
        if ($allowed) {
            $response->assertSessionHasErrors(['name', 'serial_number']);
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseCount('equipment', 1);
    }

    #[DataProvider('mutationRoles')]
    public function test_reservation_creation_rejects_bad_dates_and_other_roles(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $response = $this->actingAs($user)->post(route('reservations.store', $item), [
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => 'not a date',
        ]);
        if (in_array($role, ['student', 'faculty'], true)) {
            $response->assertSessionHasErrors(['starts_at', 'ends_at']);
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    #[DataProvider('mutationRoles')]
    public function test_only_own_lab_manager_can_approve_valid_request(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->create();
        $response = $this->actingAs($user)->patch(route('reservations.approve', $reservation));
        if ($role === 'lab_manager') {
            $response->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'approved', 'approved_by' => $user->id]);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
        }
    }

    public function test_head_cannot_assign_an_unknown_role_or_foreign_lab(): void
    {
        [$head, $item, $lab] = $this->fixture('department_head');
        $newcomer = User::factory()->student()->create();
        $foreign = Lab::factory()->create();
        $this->actingAs($head)->patch(route('department.users.update', $newcomer), ['role' => 'administrator'])
            ->assertSessionHasErrors('role');
        $this->patch(route('department.users.update', $newcomer), ['role' => 'lab_manager', 'lab_id' => $foreign->id])
            ->assertSessionHasErrors('lab_id');
        $this->assertSame(Role::Student, $newcomer->fresh()->role);
        $this->assertNull($newcomer->fresh()->department_id);
    }

    public function test_head_can_assign_an_unassigned_student_to_own_lab_manager_role(): void
    {
        [$head, $item, $lab] = $this->fixture('department_head');
        $newcomer = User::factory()->student()->create();
        $this->actingAs($head)->patch(route('department.users.update', $newcomer), ['role' => 'lab_manager', 'lab_id' => $lab->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $newcomer->id, 'role' => 'lab_manager', 'lab_id' => $lab->id, 'department_id' => $head->department_id]);
    }

    public function test_head_cannot_reassign_another_departments_user_or_another_head(): void
    {
        [$head] = $this->fixture('department_head');
        $foreign = User::factory()->departmentHead()->create();
        $foreignStudent = User::factory()->student()->create(['department_id' => $foreign->department_id]);
        $this->actingAs($head)->patch(route('department.users.update', $foreign), ['role' => 'student'])->assertForbidden();
        $this->patch(route('department.users.update', $foreignStudent), ['role' => 'faculty'])->assertForbidden();
    }

    public function test_uploads_reject_invalid_types_and_oversized_files_without_writing_records(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$manager, $item] = $this->fixture('lab_manager');
        $this->actingAs($manager)->post(route('documents.store', $item), [
            'title' => 'Invalid document', 'type' => 'manual', 'file' => UploadedFile::fake()->create('evil.html', 1, 'text/html'),
        ])->assertSessionHasErrors('file');
        $this->post(route('documents.store', $item), [
            'title' => 'Oversized document', 'type' => 'manual', 'file' => UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->put(route('equipment.update', $item), ['name' => $item->name, 'serial_number' => $item->serial_number,
            'photo' => UploadedFile::fake()->image('huge.png')->size(2049)])
            ->assertSessionHasErrors('photo');
        $this->assertNull($item->fresh()->photo_path);
    }

    public function test_pending_request_cannot_be_cancelled_by_another_borrower(): void
    {
        [$student, $item] = $this->fixture('student');
        $reservation = Reservation::factory()->for($item)->create();
        $this->actingAs($student)->patch(route('reservations.cancel', $reservation))->assertForbidden();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
    }

    public static function lifecycleCases(): array
    {
        $cases = [];
        foreach (Role::cases() as $role) {
            foreach (['reject', 'handover', 'return', 'cancel'] as $action) {
                $cases[$role->value.' '.$action] = [$role->value, $action];
            }
        }

        return $cases;
    }

    #[DataProvider('lifecycleCases')]
    public function test_all_roles_have_expected_lifecycle_action_permissions(string $role, string $action): void
    {
        [$user, $item] = $this->fixture($role);
        $before = match ($action) {
            'handover' => 'approved', 'return' => 'checked_out', default => 'pending'
        };
        $after = match ($action) {
            'handover' => 'checked_out', 'return' => 'returned', 'reject' => 'rejected', 'cancel' => 'cancelled'
        };
        $allowed = $action === 'cancel' ? in_array($role, ['student', 'faculty'], true) : $role === 'lab_manager';
        $window = $action === 'handover' ? ['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()] : [];
        $reservation = Reservation::factory()->for($item)->for($user, 'user')->create(['status' => $before] + $window);
        $response = $this->actingAs($user)->patch(route('reservations.'.$action, $reservation));
        if ($allowed) {
            $response->assertSessionHasNoErrors()->assertRedirect();
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => $allowed ? $after : $before]);
    }

    #[DataProvider('mutationRoles')]
    public function test_document_download_permissions_for_all_roles(string $role): void
    {
        Storage::fake('local');
        [$user, $item] = $this->fixture($role);
        Storage::disk('local')->put('documents/qa.pdf', '%PDF-1.4');
        $document = Document::factory()->for($item)->create(['file_path' => 'documents/qa.pdf']);
        $response = $this->actingAs($user)->get(route('documents.download', $document));
        if ($role === 'security_auditor') {
            $response->assertForbidden();
        } else {
            $response->assertDownload($document->original_name);
        }
    }
}
