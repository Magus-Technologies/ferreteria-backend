<?php

namespace App\Services\CierreCaja;

use App\Exceptions\SupervisorRequeridoException;
use App\Models\User;

class ValidadorSupervisorCaja
{
    public function validar(?int $supervisorId): void
    {
        if (!$supervisorId) {
            throw new SupervisorRequeridoException();
        }

        $supervisor = User::find($supervisorId);

        if (!$supervisor || !$supervisor->hasRole('supervisor')) {
            throw new SupervisorRequeridoException('El usuario no tiene permisos de supervisor');
        }
    }
}
