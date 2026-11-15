<?php

use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.concurrency' => json_decode(getenv('EMM_WORKER_CONNECTION'), true, flags: JSON_THROW_ON_ERROR)]);
DB::setDefaultConnection('concurrency');

$reservation = Reservation::findOrFail($argv[1]);
$manager = User::findOrFail($argv[2]);
$id = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
echo "READY {$id}\n";
flush();

try {
    app(ReservationService::class)->approve($reservation, $manager);
    echo "APPROVED\n";
} catch (ValidationException $exception) {
    if (! array_key_exists('starts_at', $exception->errors())) {
        throw $exception;
    }

    echo "OVERLAP\n";
}
