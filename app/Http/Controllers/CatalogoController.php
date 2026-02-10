<?php

namespace App\Http\Controllers;

use App\Models\CatalogoEstadoCivil;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Obtener lista de estados civiles
     * GET /api/catalogos/estados-civiles
     */
    public function estadosCiviles(): JsonResponse
    {
        $estadosCiviles = CatalogoEstadoCivil::activos()
            ->ordenado()
            ->get(['id', 'codigo', 'descripcion', 'orden']);

        return response()->json([
            'data' => $estadosCiviles
        ]);
    }

    /**
     * Obtener lista de roles del sistema
     * GET /api/roles
     */
    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name', 'asc')
            ->get(['id', 'name', 'descripcion']);

        return response()->json([
            'data' => $roles
        ]);
    }

    /**
     * Obtener lista de tipos de documento
     * GET /api/catalogos/tipos-documento
     */
    public function tiposDocumento(): JsonResponse
    {
        $tiposDocumento = [
            ['codigo' => 'DNI', 'descripcion' => 'DNI - Documento Nacional de Identidad'],
            ['codigo' => 'RUC', 'descripcion' => 'RUC - Registro Único de Contribuyentes'],
            ['codigo' => 'CE', 'descripcion' => 'CE - Carné de Extranjería'],
            ['codigo' => 'PASAPORTE', 'descripcion' => 'Pasaporte'],
        ];

        return response()->json([
            'data' => $tiposDocumento
        ]);
    }

    /**
     * Obtener lista de géneros
     * GET /api/catalogos/generos
     */
    public function generos(): JsonResponse
    {
        $generos = [
            ['codigo' => 'M', 'descripcion' => 'Masculino'],
            ['codigo' => 'F', 'descripcion' => 'Femenino'],
            ['codigo' => 'O', 'descripcion' => 'Otro'],
        ];

        return response()->json([
            'data' => $generos
        ]);
    }

    /**
     * Obtener lista de roles del sistema (para formularios)
     * GET /api/catalogos/roles-sistema
     * 
     * NOTA: Este endpoint devuelve el mapeo de roles del sistema
     * En el futuro, cuando se elimine el campo rol_sistema, este endpoint
     * será reemplazado por el endpoint /api/roles
     */
    public function rolesSistema(): JsonResponse
    {
        $rolesSistema = [
            ['codigo' => 'ADMINISTRADOR', 'descripcion' => 'Administrador', 'role_id' => 1],
            ['codigo' => 'VENDEDOR', 'descripcion' => 'Vendedor', 'role_id' => 2],
            ['codigo' => 'ALMACENERO', 'descripcion' => 'Almacenero', 'role_id' => 3],
            ['codigo' => 'CONTADOR', 'descripcion' => 'Contador', 'role_id' => 4],
            ['codigo' => 'DESPACHADOR', 'descripcion' => 'Despachador', 'role_id' => 9],
            ['codigo' => 'CONDUCTOR', 'descripcion' => 'Conductor', 'role_id' => 2], // Usa rol vendedor
        ];

        return response()->json([
            'data' => $rolesSistema
        ]);
    }

    /**
     * Obtener lista de cargos/ocupaciones
     * GET /api/catalogos/cargos
     */
    public function cargos(): JsonResponse
    {
        $cargos = [
            ['codigo' => 'ADMINISTRADOR GERENCIA', 'descripcion' => 'Administrador Gerencia'],
            ['codigo' => 'GERENTE GENERAL GERENCIA', 'descripcion' => 'Gerente General Gerencia'],
            ['codigo' => 'VENDEDOR', 'descripcion' => 'Vendedor'],
            ['codigo' => 'ASISTENTE CONTABLE', 'descripcion' => 'Asistente Contable'],
            ['codigo' => 'ALMACENERO', 'descripcion' => 'Almacenero'],
            ['codigo' => 'CONDUCTOR MOTO-OBRERO', 'descripcion' => 'Conductor Moto-Obrero'],
            ['codigo' => 'OBRERO-CONDUCTOR', 'descripcion' => 'Obrero-Conductor'],
            ['codigo' => 'AYUDANTE DE CAMION', 'descripcion' => 'Ayudante de Camión'],
        ];

        return response()->json([
            'data' => $cargos
        ]);
    }
}
