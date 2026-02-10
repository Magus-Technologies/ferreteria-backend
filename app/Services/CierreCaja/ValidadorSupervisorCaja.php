<?php

namespace App\Services\CierreCaja;

use App\Exceptions\SupervisorRequeridoException;
use App\Models\User;

class ValidadorSupervisorCaja
{
    public function validar(?string $supervisorId): void
    {
        if (!$supervisorId) {
            throw new SupervisorRequeridoException('Se requiere autorización de supervisor');
        }

        $supervisor = User::find($supervisorId);

        // Validar que el usuario existe y es supervisor
        if (!$supervisor || !$supervisor->es_supervisor) {
            throw new SupervisorRequeridoException('El usuario no tiene permisos de supervisor');
        }

        // Validar que tiene contraseña de supervisor configurada
        if (!$supervisor->supervisor_password) {
            throw new SupervisorRequeridoException('El supervisor no tiene contraseña configurada');
        }
    }
}
