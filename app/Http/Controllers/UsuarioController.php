<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsuarioController extends Controller
{
    /**
     * Listar todos los usuarios
     * GET /api/usuarios
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['empresa']);

        // Filtro por búsqueda (nombre, email, documento)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        // Filtro por empresa
        if ($request->has('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        // Filtro por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado === 'true' || $request->estado === '1');
        }

        $usuarios = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $usuarios
        ]);
    }

    /**
     * Crear un nuevo usuario
     * POST /api/usuarios
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Campos obligatorios
            'name' => 'required|string|max:191',
            'email' => 'required|email|unique:user,email|max:191',
            'password' => 'required|string|min:6|confirmed',
            'empresa_id' => 'required|integer|exists:empresa,id',
            
            // Información personal
            'tipo_documento' => 'nullable|in:DNI,RUC,CE,PASAPORTE',
            'numero_documento' => 'nullable|string|max:20|unique:user,numero_documento',
            'telefono' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'genero' => 'nullable|in:M,F,O',
            'estado_civil' => 'nullable|in:SOLTERO,CASADO,DIVORCIADO,VIUDO,CONVIVIENTE',
            'email_corporativo' => 'nullable|email|max:191',
            
            // Dirección
            'direccion_linea1' => 'nullable|string|max:255',
            'direccion_linea2' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'nacionalidad' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            
            // Información de Contrato
            'cargo' => 'nullable|string|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_baja' => 'nullable|date',
            'vacaciones_dias' => 'nullable|integer|min:0',
            'sueldo_boleta' => 'nullable|numeric|min:0',
            'rol_sistema' => 'nullable|in:ADMINISTRADOR,VENDEDOR,ALMACENERO,CONTADOR,CONDUCTOR',
            
            // Otros
            'efectivo' => 'nullable|numeric|min:0',
            'estado' => 'nullable|boolean',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'empresa_id.required' => 'La empresa es obligatoria',
            'empresa_id.exists' => 'La empresa no existe',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'tipo_documento.in' => 'El tipo de documento no es válido',
            'genero.in' => 'El género no es válido',
            'estado_civil.in' => 'El estado civil no es válido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = User::create([
            'id' => $this->generateCuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'empresa_id' => $request->empresa_id,
            
            // Información personal
            'tipo_documento' => $request->tipo_documento ?? 'DNI',
            'numero_documento' => $request->numero_documento,
            'telefono' => $request->telefono,
            'celular' => $request->celular,
            'genero' => $request->genero,
            'estado_civil' => $request->estado_civil,
            'email_corporativo' => $request->email_corporativo,
            
            // Dirección
            'direccion_linea1' => $request->direccion_linea1,
            'direccion_linea2' => $request->direccion_linea2,
            'ciudad' => $request->ciudad,
            'nacionalidad' => $request->nacionalidad ?? 'PERUANA',
            'fecha_nacimiento' => $request->fecha_nacimiento,
            
            // Información de Contrato
            'cargo' => $request->cargo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_baja' => $request->fecha_baja,
            'vacaciones_dias' => $request->vacaciones_dias ?? 15,
            'sueldo_boleta' => $request->sueldo_boleta,
            'rol_sistema' => $request->rol_sistema,
            
            // Otros
            'efectivo' => $request->efectivo ?? 0,
            'estado' => $request->estado ?? true,
        ]);

        $usuario->load('empresa');

        return response()->json([
            'data' => $usuario,
            'message' => 'Usuario creado exitosamente'
        ], 201);
    }

    /**
     * Mostrar un usuario específico
     * GET /api/usuarios/{id}
     */
    public function show(string $id): JsonResponse
    {
        $usuario = User::with(['empresa'])->find($id);

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'data' => $usuario
        ]);
    }

    /**
     * Actualizar un usuario
     * PUT /api/usuarios/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $rules = [
            'name' => 'sometimes|required|string|max:191',
            'email' => 'sometimes|required|email|max:191|unique:user,email,' . $id,
            'password' => 'sometimes|nullable|string|min:6|confirmed',
            'empresa_id' => 'sometimes|required|integer|exists:empresa,id',
            
            // Información personal
            'tipo_documento' => 'nullable|in:DNI,RUC,CE,PASAPORTE',
            'numero_documento' => 'nullable|string|max:20|unique:user,numero_documento,' . $id,
            'telefono' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'genero' => 'nullable|in:M,F,O',
            'estado_civil' => 'nullable|in:SOLTERO,CASADO,DIVORCIADO,VIUDO,CONVIVIENTE',
            'email_corporativo' => 'nullable|email|max:191',
            
            // Dirección
            'direccion_linea1' => 'nullable|string|max:255',
            'direccion_linea2' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'nacionalidad' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            
            // Información de Contrato
            'cargo' => 'nullable|string|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_baja' => 'nullable|date',
            'vacaciones_dias' => 'nullable|integer|min:0',
            'sueldo_boleta' => 'nullable|numeric|min:0',
            'rol_sistema' => 'nullable|in:ADMINISTRADOR,VENDEDOR,ALMACENERO,CONTADOR,CONDUCTOR',
            
            // Otros
            'efectivo' => 'nullable|numeric|min:0',
            'estado' => 'nullable|boolean',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'empresa_id.required' => 'La empresa es obligatoria',
            'empresa_id.exists' => 'La empresa no existe',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Actualizar campos básicos
        if ($request->has('name')) {
            $usuario->name = $request->name;
        }
        if ($request->has('email')) {
            $usuario->email = $request->email;
        }
        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }
        if ($request->has('empresa_id')) {
            $usuario->empresa_id = $request->empresa_id;
        }

        // Actualizar información personal
        if ($request->has('tipo_documento')) {
            $usuario->tipo_documento = $request->tipo_documento;
        }
        if ($request->has('numero_documento')) {
            $usuario->numero_documento = $request->numero_documento;
        }
        if ($request->has('telefono')) {
            $usuario->telefono = $request->telefono;
        }
        if ($request->has('celular')) {
            $usuario->celular = $request->celular;
        }
        if ($request->has('genero')) {
            $usuario->genero = $request->genero;
        }
        if ($request->has('estado_civil')) {
            $usuario->estado_civil = $request->estado_civil;
        }
        if ($request->has('email_corporativo')) {
            $usuario->email_corporativo = $request->email_corporativo;
        }

        // Actualizar dirección
        if ($request->has('direccion_linea1')) {
            $usuario->direccion_linea1 = $request->direccion_linea1;
        }
        if ($request->has('direccion_linea2')) {
            $usuario->direccion_linea2 = $request->direccion_linea2;
        }
        if ($request->has('ciudad')) {
            $usuario->ciudad = $request->ciudad;
        }
        if ($request->has('nacionalidad')) {
            $usuario->nacionalidad = $request->nacionalidad;
        }
        if ($request->has('fecha_nacimiento')) {
            $usuario->fecha_nacimiento = $request->fecha_nacimiento;
        }

        // Actualizar información de contrato
        if ($request->has('cargo')) {
            $usuario->cargo = $request->cargo;
        }
        if ($request->has('fecha_inicio')) {
            $usuario->fecha_inicio = $request->fecha_inicio;
        }
        if ($request->has('fecha_baja')) {
            $usuario->fecha_baja = $request->fecha_baja;
        }
        if ($request->has('vacaciones_dias')) {
            $usuario->vacaciones_dias = $request->vacaciones_dias;
        }
        if ($request->has('sueldo_boleta')) {
            $usuario->sueldo_boleta = $request->sueldo_boleta;
        }
        if ($request->has('rol_sistema')) {
            $usuario->rol_sistema = $request->rol_sistema;
        }

        // Actualizar otros
        if ($request->has('efectivo')) {
            $usuario->efectivo = $request->efectivo;
        }
        if ($request->has('estado')) {
            $usuario->estado = $request->estado;
        }

        $usuario->save();
        $usuario->load('empresa');

        return response()->json([
            'data' => $usuario,
            'message' => 'Usuario actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar un usuario (soft delete - cambiar estado)
     * DELETE /api/usuarios/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Verificar que no sea el usuario actual
        if (auth()->id() === $id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propio usuario'
            ], 403);
        }

        // Soft delete: cambiar estado a inactivo
        $usuario->estado = false;
        $usuario->save();

        // Si quieres hacer un hard delete, usa:
        // $usuario->delete();

        return response()->json([
            'message' => 'Usuario desactivado exitosamente'
        ]);
    }

    /**
     * Generar un CUID compatible con Prisma
     */
    private function generateCuid(): string
    {
        // Generar un CUID simple (compatible con Prisma)
        // Formato: c + timestamp + random
        $timestamp = base_convert(time(), 10, 36);
        $random = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 16);
        return 'c' . $timestamp . $random;
    }
}
