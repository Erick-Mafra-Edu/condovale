<?php

namespace App\Enums;

/**
 * A que título o morador ocupa a unidade.
 *
 * Não muda permissão nenhuma — quem decide o que o morador pode fazer é o
 * papel (UserRole). O tipo existe para o síndico saber com quem falar sobre a
 * unidade e para os relatórios distinguirem proprietário de inquilino.
 */
enum OccupantType: string
{
    case Owner = 'owner';
    case Tenant = 'tenant';
    case Dependent = 'dependent';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Tenant => 'Inquilino',
            self::Dependent => 'Dependente',
        };
    }
}
