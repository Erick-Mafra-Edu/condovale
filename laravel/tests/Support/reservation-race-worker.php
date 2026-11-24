<?php

/**
 * One of the competing requests of ReservationRaceTest.
 *
 * Usage: php reservation-race-worker.php <area id> <resident id> <date> <start|-> <end|-> <start at>
 *
 * Boots the application in its own process — its own connection, its own
 * transaction —, waits for the instant every worker shares, so the requests
 * really overlap, and tries to book. Prints "created", "refused:<http code>"
 * or "error:<class>:<message>".
 */

use App\Exceptions\BusinessRuleException;
use App\Http\Actions\CreateReservationAction;
use App\Models\CommonArea;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $areaId, $residentId, $date, $start, $end, $startAt] = $argv;

$resident = User::findOrFail((int) $residentId);
$commonArea = CommonArea::findOrFail((int) $areaId);
Auth::setUser($resident);

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

try {
    CreateReservationAction::execute($resident, $commonArea, [
        'date' => $date,
        'start_time' => $start === '-' ? null : $start,
        'end_time' => $end === '-' ? null : $end,
    ]);

    echo 'created';
} catch (BusinessRuleException $e) {
    echo 'refused:'.$e->getCode();
} catch (Throwable $e) {
    echo 'error:'.$e::class.':'.$e->getMessage();
}
