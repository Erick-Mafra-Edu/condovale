<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Papel do Spatie, reexportado no namespace da aplicação.
 *
 * Mesma razão do App\Models\Permission: o HandlePaginationAction só é seguro
 * sobre models que declaram `$hidden`, e os models do projeto ficam todos em
 * app/Models.
 */
class Role extends SpatieRole
{
    /** @var list<string> */
    protected $hidden = [];
}
