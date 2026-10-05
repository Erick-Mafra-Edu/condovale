<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permissão do Spatie, reexportada no namespace da aplicação.
 *
 * Existe por duas razões: o HandlePaginationAction só é seguro sobre models
 * que declaram `$hidden`, e um model de vendor não pode declará-lo; e o
 * projeto mantém os seus models em app/Models, onde quem procura vai olhar.
 */
class Permission extends SpatiePermission
{
    /**
     * Nada a esconder: o nome da permissão é o próprio contrato com o
     * frontend. A declaração existe porque a Action de listagem só libera as
     * colunas que o model não esconde.
     *
     * @var list<string>
     */
    protected $hidden = [];
}
