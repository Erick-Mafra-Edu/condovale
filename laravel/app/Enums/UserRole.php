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
                UseCase::UpdateOwnProfile,
                UseCase::ViewAssignedOccurrences,
                UseCase::UpdateOccurrenceProgress,
                UseCase::FinishOccurrence,
                // Repassa o próprio chamado a outro funcionário — o encanador
                // que descobre que o problema é elétrico. Só o que é dele, e
                // sempre com justificativa.
                UseCase::HandOverOccurrence,
            ],
            self::Syndic => [
                UseCase::Login,
                UseCase::UpdateOwnProfile,
                // RN03 — as alterações estruturais de unidades são do síndico
                // e do administrador. O cadastro de pessoas não: esse é só do
                // administrador, por manage-residents.
                UseCase::ManageUnits,
                // O morador abre o proprio chamado; o sindico abre no lugar de
                // quem nao conseguir, e responde pela lista de tipos.
                UseCase::OpenOccurrenceForResident,
                UseCase::ManageOccurrenceTypes,
                // Analisa e encaminha, como a administração. A análise vem
                // junto porque o fluxo a exige antes do encaminhamento: sem
                // ela o síndico só encaminharia o que o administrador já
                // tivesse analisado.
                UseCase::AnalyzeOccurrences,
                UseCase::AssignOccurrence,
                // Dá baixa em qualquer chamado, de qualquer tipo. O
                // funcionário só encerra o que lhe foi atribuído, e só quando
                // o tipo permite (occurrence_types.employee_can_close).
                UseCase::CloseAnyOccurrence,
                UseCase::PublishNotices,
                UseCase::GenerateReports,
            ],
            self::Admin => [
                UseCase::Login,
                UseCase::UpdateOwnProfile,
                UseCase::ManageUnits,
                UseCase::ManageResidents,
                UseCase::LinkResidentsToUnits,
                UseCase::OpenOccurrenceForResident,
                UseCase::ManageOccurrenceTypes,
                UseCase::AnalyzeOccurrences,
                UseCase::AssignOccurrence,
                UseCase::CloseAnyOccurrence,
                UseCase::PublishNotices,
                UseCase::ManageReservations,
                UseCase::ApproveOrRejectReservation,
                UseCase::GenerateReports,
                UseCase::ViewAuditReports,
            ],
        };
    }

    /**
     * Consulta a matriz **declarada**, e não a autorização em vigor.
     *
     * Quem decide em tempo de execução é User::hasUseCase(), que lê as tabelas
     * do Spatie — é lá que moram as concessões individuais, que esta matriz
     * por definição não conhece. Este método serve para o teste de paridade
     * com o frontend e para alimentar o seeder, sem precisar de banco.
     */
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
