<?php

/**
 * One of the competing requests of OccurrenceRaceTest.
 *
 * Usage:
 *   php occurrence-race-worker.php <start at> finalizar <occurrence id> <user id>
 *   php occurrence-race-worker.php <start at> analisar <occurrence id> <user id>
 *
 * Boots the application in its own process, loads the ticket before the start
 * (the same possibly outdated model a real request holds), waits for the
 * instant every worker shares and acts inside a transaction, as the controller
 * does. Prints "ok", "refused:<http code>" or "error:<class>:<message>".
 */

use App\Enums\OccurrenceStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\FinishOccurrenceAction;
use App\Http\Actions\UpdateOccurrenceStatusAction;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $action, $occurrenceId, $userId] = $argv;

$user = User::findOrFail((int) $userId);
$occurrence = Occurrence::findOrFail((int) $occurrenceId);
Auth::setUser($user);

$run = match ($action) {
    'finalizar' => fn () => FinishOccurrenceAction::execute($occurrence, $user, true, 'Atendimento concluído.'),
    'analisar' => fn () => UpdateOccurrenceStatusAction::execute($occurrence, $user, OccurrenceStatus::Analysis),
};

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

try {
    DB::transaction($run);
    echo 'ok';
} catch (BusinessRuleException $e) {
    echo 'refused:'.$e->getCode();
} catch (Throwable $e) {
    echo 'error:'.$e::class.':'.$e->getMessage();
}
