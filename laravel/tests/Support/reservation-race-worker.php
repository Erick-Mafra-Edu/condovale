<?php

/**
 * One of the competing requests of ReservationRaceTest.
 *
 * Usage:
 *   php reservation-race-worker.php <start at> reservar <area id> <resident id> <date> <start|-> <end|->
 *   php reservation-race-worker.php <start at> cancelar <reservation id> <user id>
 *   php reservation-race-worker.php <start at> aprovar <reservation id> <user id>
 *
 * Boots the application in its own process — its own connection, its own
 * transaction —, loads what it needs before the start (so it holds the same
 * possibly outdated models a real request would), waits for the instant every
 * worker shares, so the requests really overlap, and acts. Prints "created",
 * "ok", "refused:<http code>" or "error:<class>:<message>".
 */

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\CancelReservationAction;
use App\Http\Actions\CreateReservationAction;
use App\Http\Actions\ReviewReservationAction;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $action] = $argv;
$args = array_slice($argv, 3);

$run = match ($action) {
    'reservar' => (function () use ($args) {
        [$areaId, $residentId, $date, $start, $end] = $args;
        $resident = User::findOrFail((int) $residentId);
        $commonArea = CommonArea::findOrFail((int) $areaId);
        Auth::setUser($resident);

        return function () use ($resident, $commonArea, $date, $start, $end) {
            CreateReservationAction::execute($resident, $commonArea, [
                'date' => $date,
                'start_time' => $start === '-' ? null : $start,
                'end_time' => $end === '-' ? null : $end,
            ]);

            return 'created';
        };
    })(),
    'cancelar', 'aprovar' => (function () use ($args, $action) {
        [$reservationId, $userId] = $args;
        $user = User::findOrFail((int) $userId);
        $reservation = Reservation::with('commonArea')->findOrFail((int) $reservationId);
        Auth::setUser($user);

        return function () use ($action, $reservation, $user) {
            $action === 'cancelar'
                ? CancelReservationAction::execute($reservation, $user)
                : ReviewReservationAction::execute($reservation, ReservationStatus::Approved);

            return 'ok';
        };
    })(),
};

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

try {
    echo $run();
} catch (BusinessRuleException $e) {
    echo 'refused:'.$e->getCode();
} catch (Throwable $e) {
    echo 'error:'.$e::class.':'.$e->getMessage();
}
