<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentApprovalTest extends TestCase
{
    public function test_same_equipment_approvals_serialize_on_two_mysql_connections(): void
    {
        $database = getenv('EMM_CONCURRENCY_DATABASE');
        if (! $database) {
            $this->markTestSkipped('Set EMM_CONCURRENCY_DATABASE to a dedicated MySQL or MariaDB database ending in _test.');
        }
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+_test$/', $database);

        $originalConnection = DB::getDefaultConnection();
        $connection = array_replace(config('database.connections.mysql'), [
            'url' => null,
            'host' => getenv('EMM_CONCURRENCY_HOST') ?: '127.0.0.1',
            'port' => getenv('EMM_CONCURRENCY_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('EMM_CONCURRENCY_USERNAME') ?: 'root',
            'password' => getenv('EMM_CONCURRENCY_PASSWORD') ?: '',
            'unix_socket' => '',
        ]);
        config(['database.connections.concurrency' => $connection]);
        DB::setDefaultConnection('concurrency');

        $worker = $department = $manager = $borrowers = null;

        try {
            $this->artisan('migrate', ['--database' => 'concurrency', '--force' => true])->assertExitCode(0);

            $department = Department::factory()->create();
            $lab = Lab::factory()->for($department)->create();
            $item = Equipment::factory()->for($lab)->create();
            $manager = User::factory()->labManager($lab)->create();
            $borrowers = User::factory()->student()->count(2)->create();
            $start = now()->addDay()->setTime(9, 0);
            $window = ['starts_at' => $start, 'ends_at' => $start->copy()->addHours(2)];
            $first = Reservation::factory()->for($item)->for($borrowers[0], 'user')->create($window);
            $second = Reservation::factory()->for($item)->for($borrowers[1], 'user')->create($window);

            DB::beginTransaction();
            app(ReservationService::class)->approve($first, $manager);

            $worker = new Process([PHP_BINARY, base_path('tests/Support/approve-reservation.php'), (string) $second->id, (string) $manager->id], base_path(), [
                'EMM_WORKER_CONNECTION' => json_encode($connection, JSON_THROW_ON_ERROR),
            ], timeout: 15);
            $worker->start();
            $this->assertTrue($worker->waitUntil(fn () => preg_match('/READY (\d+)\R/', $worker->getOutput()) === 1));
            preg_match('/READY (\d+)\R/', $worker->getOutput(), $ready);
            $this->assertTrue($this->workerReachedEquipmentLock((int) $ready[1], $worker), 'The competing approval must wait on the equipment row before the first approval commits.');

            DB::commit();

            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString("OVERLAP\n", $worker->getOutput());
            $this->assertSame(ReservationStatus::Approved, $first->fresh()->status);
            $this->assertSame(ReservationStatus::Pending, $second->fresh()->status);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->stop();
            $department?->delete();
            $manager?->delete();
            if ($borrowers !== null) {
                User::destroy($borrowers->modelKeys());
            }
            DB::disconnect('concurrency');
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function workerReachedEquipmentLock(int $connectionId, Process $worker): bool
    {
        $deadline = microtime(true) + 5;

        do {
            foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) $process->Id === $connectionId
                    && str_contains(strtolower($process->Info ?? ''), 'from `equipment`')
                    && str_contains(strtolower($process->Info ?? ''), 'for update')) {
                    return true;
                }
            }

            usleep(20_000);
        } while (microtime(true) < $deadline && $worker->isRunning());

        return false;
    }
}
