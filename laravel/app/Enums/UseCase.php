<?php

namespace App\Enums;

/**
 * Casos de uso do diagrama funcional do CondoVale (documento M1, seção 8.1).
 *
 * Os valores são idênticos aos do frontend em app/domain/permissions.ts: a
 * autorização real acontece aqui, e o frontend apenas esconde o que o papel
 * não pode executar.
 */
enum UseCase: string
{
    case Login = 'login';
    case UpdateOwnProfile = 'update-own-profile';
    case ViewNotices = 'view-notices';
    case CreateOccurrence = 'create-occurrence';
    case TrackOwnOccurrences = 'track-own-occurrences';
    case ViewCommonAreas = 'view-common-areas';
    case RequestReservation = 'request-reservation';
    case ViewOwnReservations = 'view-own-reservations';
    case CancelOwnReservation = 'cancel-own-reservation';
    case ManageUnits = 'manage-units';
    case ManageResidents = 'manage-residents';
    case LinkResidentsToUnits = 'link-residents-to-units';
    case AnalyzeOccurrences = 'analyze-occurrences';
    case AssignOccurrence = 'assign-occurrence';
    case PublishNotices = 'publish-notices';
    case ManageReservations = 'manage-reservations';
    case ApproveOrRejectReservation = 'approve-or-reject-reservation';
    case ViewAssignedOccurrences = 'view-assigned-occurrences';
    case UpdateOccurrenceProgress = 'update-occurrence-progress';
    case FinishOccurrence = 'finish-occurrence';
    case GenerateReports = 'generate-reports';
    case ViewAuditReports = 'view-audit-reports';
}
