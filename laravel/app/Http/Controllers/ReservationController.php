<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\UseCase;
use App\Http\Actions\CancelReservationAction;
use App\Http\Actions\CreateReservationAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\ReviewReservationAction;
use App\Http\Actions\ScopeReservationsAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\ReservationStoreRequest;
use App\Http\Requests\ReservationUpdateRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF03 — reservations of common areas: UC15 (request), UC16 (approve or
 * reject), UC17 (my reservations), UC18 (cancel) and UC19 (manage).
 */
class ReservationController extends Controller
{
    private const AREA = 'commonArea:id,name';

    /**
     * Who booked, for whoever manages the agenda: name and unit, which is what
     * identifies the resident at the front desk. E-mail and document stay out.
     */
    private const MORADOR = ['resident:id,name,unit_id', 'resident.unit:id,code'];

    /**
     * UC19 — every reservation of every area, for the administration. Filters,
     * sorting and paging follow the listing contract (`filters=status:=:pending`,
     * `filters=common_area_id:=:3`, `filters=date:>=:2026-11-01`...).
     */
    public function index(DefaultPaginationRequest $request)
    {
        $reservations = self::listar($request)
            ->with([self::AREA, ...self::MORADOR])
            ->paginate(self::limite($request));

        return MessageService::success('Reservas retornadas.', self::linhas($reservations), true);
    }

    /**
     * UC17 — the reservations of the session user, and only theirs: the list
     * never takes a resident from the query string.
     */
    public function mine(DefaultPaginationRequest $request)
    {
        $reservations = self::listar($request)
            ->where('resident_id', AuthUtil::id())
            ->with(self::AREA)
            ->paginate(self::limite($request));

        return MessageService::success('Suas reservas retornadas.', self::linhas($reservations), true);
    }

    /**
     * One reservation, for its resident or for the administration. Someone
     * else's reservation answers 404, the same as one that does not exist, so
     * ids cannot be probed.
     */
    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $user = AuthUtil::user();

        $reservation = ScopeReservationsAction::execute(Reservation::query(), $user)
            ->with(self::podeVerMorador($user) ? [self::AREA, ...self::MORADOR] : [self::AREA])
            ->find($id);

        if (! $reservation) {
            return MessageService::error("Reserva {$id} não encontrada.", 404);
        }

        return MessageService::success("Reserva {$reservation->id} encontrada.", self::linha($reservation));
    }

    /**
     * UC16 — approves or rejects a pending request. The Action checks the
     * request again against the area and the agenda as they are now (RN01,
     * RN02) and records the decision in the audit trail.
     */
    public function updateStatus(ReservationUpdateRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $reservation = Reservation::find($id);

        if (! $reservation) {
            return MessageService::error("Reserva {$id} não encontrada.", 404);
        }

        $status = ReservationStatus::from($item->status);
        $reason = trim((string) ($item->rejection_reason ?? ''));

        try {
            $reservation = ReviewReservationAction::execute($reservation, $status, $reason === '' ? null : $reason);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $reservation->load([self::AREA, ...self::MORADOR]);
        $outcome = $status === ReservationStatus::Approved ? 'aprovada' : 'reprovada';

        return MessageService::success(
            CreateReservationAction::describe($reservation, $reservation->commonArea, $outcome),
            self::linha($reservation)
        );
    }

    /**
     * The resident is always the session user: the payload names the area and
     * the period, never who is booking. RN01 and RN02 are enforced by the
     * Action, which also opens the transaction and writes the audit log.
     */
    public function store(ReservationStoreRequest $request)
    {
        $attributes = $request->validated();
        $commonArea = CommonArea::find($attributes['common_area_id']);

        if (! $commonArea) {
            return MessageService::error("Área comum {$attributes['common_area_id']} não encontrada.", 404);
        }

        try {
            $reservation = CreateReservationAction::execute(AuthUtil::user(), $commonArea, $attributes);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success(
            CreateReservationAction::describe($reservation, $commonArea),
            $reservation->load('commonArea:id,name')
        );
    }

    /**
     * RN08 — cancels and releases the period at once: a cancelled reservation
     * no longer blocks the agenda. The resident cancels their own; the
     * administration, any. Which is which is the Action's decision.
     */
    public function cancel(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $reservation = Reservation::find($id);

        if (! $reservation) {
            return MessageService::error("Reserva {$id} não encontrada.", 404);
        }

        try {
            $reservation = CancelReservationAction::execute($reservation, AuthUtil::user());
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $reservation->load('commonArea:id,name');
        $description = CreateReservationAction::describe($reservation, $reservation->commonArea, 'cancelada');

        return MessageService::success("{$description} O período voltou a ficar disponível.", $reservation);
    }

    /**
     * The listing contract, without `relations`: what comes nested is decided
     * here, or a resident could ask for `resident` and receive the whole
     * account of whoever booked. Newest first unless a sort is asked for.
     */
    private static function listar(DefaultPaginationRequest $request): Builder
    {
        $request->query->remove('relations');

        // The pagination Action always adds the id as tie breaker, in the
        // same direction: the newest booking of the same day comes first.
        if (! $request->filled('sort')) {
            $request->query->add(['sort' => 'date', 'direction' => 'desc']);
        }

        return HandlePaginationAction::execute($request, new Reservation);
    }

    /**
     * The reservation screen loads the list in one request, so a page holds
     * the most the listing contract allows unless the client asks for less.
     */
    private static function limite(DefaultPaginationRequest $request): int
    {
        return (int) ($request->validated()['limit'] ?? DefaultPaginationRequest::LIMITE_MAXIMO);
    }

    private static function podeVerMorador(User $user): bool
    {
        return $user->hasUseCase(UseCase::ManageReservations) || $user->hasUseCase(UseCase::ApproveOrRejectReservation);
    }

    /**
     * The same row as the reservation report: the label of the status and
     * whether it is a full day booking, which has no times.
     *
     * @return array<string, mixed>
     */
    private static function linha(Reservation $reservation): array
    {
        return $reservation->toArray() + [
            'status_label' => $reservation->status->label(),
            'full_day' => $reservation->isFullDay(),
        ];
    }

    private static function linhas(LengthAwarePaginator $page): LengthAwarePaginator
    {
        return $page->through(fn (Reservation $reservation) => self::linha($reservation));
    }
}
