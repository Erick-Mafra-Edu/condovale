<?php

namespace App\Enums;

/**
 * RNF04 — every operation the audit trail records, stored in logs.action.
 *
 * The value names the record and what happened to it ("reservation.approved"),
 * the convention the frontend already uses in AuditLog.action. Values are
 * contract once written: rename the case if needed, never the value.
 */
enum AuditAction: string
{
    case Login = 'auth.login';
    case Logout = 'auth.logout';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeactivated = 'user.deactivated';
    case UserProfileUpdated = 'user.profile_updated';
    case UserPasswordChanged = 'user.password_changed';
    case UserPermissionGranted = 'user.permission_granted';
    case UserPermissionRevoked = 'user.permission_revoked';

    case UnitCreated = 'unit.created';
    case UnitUpdated = 'unit.updated';
    case UnitDeactivated = 'unit.deactivated';

    case ResidentLinked = 'unit_occupancy.created';
    case ResidentUnlinked = 'unit_occupancy.ended';

    case CommonAreaCreated = 'common_area.created';
    case CommonAreaUpdated = 'common_area.updated';
    case CommonAreaDeleted = 'common_area.deleted';

    case ReservationCreated = 'reservation.created';
    case ReservationApproved = 'reservation.approved';
    case ReservationRejected = 'reservation.rejected';
    case ReservationCancelled = 'reservation.cancelled';

    case OccurrenceCreated = 'occurrence.created';
    case OccurrenceAssigned = 'occurrence.assigned';
    case OccurrenceReassigned = 'occurrence.reassigned';
    case OccurrenceStatusUpdated = 'occurrence.status_updated';
    case OccurrenceProgressNoted = 'occurrence.progress_noted';
    case OccurrenceCompleted = 'occurrence.completed';
    case OccurrenceAttachmentAdded = 'occurrence.attachment_added';
    case OccurrenceAttachmentRemoved = 'occurrence.attachment_removed';

    case OccurrenceTypeCreated = 'occurrence_type.created';
    case OccurrenceTypeUpdated = 'occurrence_type.updated';
    case OccurrenceTypeDeleted = 'occurrence_type.deleted';

    case NoticeDrafted = 'notice.drafted';
    case NoticePublished = 'notice.published';
    case NoticeDeleted = 'notice.deleted';

    case DataSeeded = 'system.seeded';

    /**
     * The category the entry is filed under. CreateLogAction refuses an
     * action logged under another type, which would file it under the wrong
     * entity and give its subject_id the wrong meaning.
     */
    public function typeLog(): TypeLogEnum
    {
        return match ($this) {
            self::Login => TypeLogEnum::LOGIN,
            self::Logout => TypeLogEnum::LOGOUT,
            self::UserCreated, self::UserUpdated, self::UserDeactivated, self::UserProfileUpdated,
            self::UserPasswordChanged, self::UserPermissionGranted, self::UserPermissionRevoked => TypeLogEnum::USER,
            self::UnitCreated, self::UnitUpdated, self::UnitDeactivated => TypeLogEnum::UNIT,
            self::ResidentLinked, self::ResidentUnlinked => TypeLogEnum::UNIT_OCCUPANCY,
            self::CommonAreaCreated, self::CommonAreaUpdated, self::CommonAreaDeleted => TypeLogEnum::COMMON_AREA,
            self::ReservationCreated, self::ReservationApproved, self::ReservationRejected,
            self::ReservationCancelled => TypeLogEnum::RESERVATION,
            self::OccurrenceCreated, self::OccurrenceAssigned, self::OccurrenceReassigned, self::OccurrenceStatusUpdated,
            self::OccurrenceProgressNoted, self::OccurrenceCompleted, self::OccurrenceAttachmentAdded,
            self::OccurrenceAttachmentRemoved => TypeLogEnum::OCCURRENCE,
            self::OccurrenceTypeCreated, self::OccurrenceTypeUpdated, self::OccurrenceTypeDeleted => TypeLogEnum::OCCURRENCE_TYPE,
            self::NoticeDrafted, self::NoticePublished, self::NoticeDeleted => TypeLogEnum::NOTICE,
            self::DataSeeded => TypeLogEnum::SYSTEM,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Entrada no sistema',
            self::Logout => 'Saída do sistema',
            self::UserCreated => 'Usuário cadastrado',
            self::UserUpdated => 'Usuário editado',
            self::UserDeactivated => 'Usuário inativado',
            self::UserProfileUpdated => 'Cadastro próprio atualizado',
            self::UserPasswordChanged => 'Senha alterada',
            self::UserPermissionGranted => 'Caso de uso concedido',
            self::UserPermissionRevoked => 'Caso de uso revogado',
            self::UnitCreated => 'Unidade cadastrada',
            self::UnitUpdated => 'Unidade editada',
            self::UnitDeactivated => 'Unidade inativada',
            self::ResidentLinked => 'Morador vinculado à unidade',
            self::ResidentUnlinked => 'Vínculo com a unidade encerrado',
            self::CommonAreaCreated => 'Área comum cadastrada',
            self::CommonAreaUpdated => 'Área comum editada',
            self::CommonAreaDeleted => 'Área comum excluída',
            self::ReservationCreated => 'Reserva registrada',
            self::ReservationApproved => 'Reserva aprovada',
            self::ReservationRejected => 'Reserva reprovada',
            self::ReservationCancelled => 'Reserva cancelada',
            self::OccurrenceCreated => 'Ocorrência aberta',
            self::OccurrenceAssigned => 'Ocorrência encaminhada',
            self::OccurrenceReassigned => 'Ocorrência repassada',
            self::OccurrenceStatusUpdated => 'Status da ocorrência alterado',
            self::OccurrenceProgressNoted => 'Andamento registrado',
            self::OccurrenceCompleted => 'Ocorrência encerrada',
            self::OccurrenceAttachmentAdded => 'Imagem anexada à ocorrência',
            self::OccurrenceAttachmentRemoved => 'Imagem removida da ocorrência',
            self::OccurrenceTypeCreated => 'Tipo de ocorrência cadastrado',
            self::OccurrenceTypeUpdated => 'Tipo de ocorrência editado',
            self::OccurrenceTypeDeleted => 'Tipo de ocorrência excluído',
            self::NoticeDrafted => 'Comunicado salvo como rascunho',
            self::NoticePublished => 'Comunicado publicado',
            self::NoticeDeleted => 'Comunicado excluído',
            self::DataSeeded => 'Carga de dados aplicada',
        };
    }
}
