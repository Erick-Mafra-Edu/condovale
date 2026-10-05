<?php

namespace App\Enums;

enum UserRole: string
{
    case Resident = 'resident';
    case Employee = 'employee';
    case Syndic = 'syndic';
    case Admin = 'admin';

    /** @return list<UseCase> */
    public function useCases(): array
    {
        return match ($this) {
            self::Resident => [
                UseCase::Login,
                UseCase::UpdateOwnProfile,
                UseCase::ViewNotices,
                UseCase::CreateOccurrence,
                UseCase::TrackOwnOccurrences,
                UseCase::ViewCommonAreas,
                UseCase::RequestReservation,
                UseCase::ViewOwnReservations,
                UseCase::CancelOwnReservation,
            ],
            self::Employee => [
                UseCase::Login,
                UseCase::ViewAssignedOccurrences,
                UseCase::UpdateOccurrenceProgress,
                UseCase::FinishOccurrence,
            ],
            self::Syndic => [
                UseCase::Login,
                UseCase::PublishNotices,
                UseCase::GenerateReports,
            ],
            self::Admin => [
                UseCase::Login,
                UseCase::ManageUnits,
                UseCase::ManageResidents,
                UseCase::ManageUsers,
                UseCase::LinkResidentsToUnits,
                UseCase::AnalyzeOccurrences,
                UseCase::AssignOccurrence,
                UseCase::PublishNotices,
                UseCase::ManageReservations,
                UseCase::ApproveOrRejectReservation,
                UseCase::GenerateReports,
                UseCase::ViewAuditReports,
            ],
        };
    }

    public function can(UseCase $useCase): bool
    {
        return in_array($useCase, $this->useCases(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Resident => 'Morador',
            self::Employee => 'Funcionário',
            self::Syndic => 'Síndico',
            self::Admin => 'Administrador',
        };
    }
}
