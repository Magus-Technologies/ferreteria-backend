<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Enviar código de verificación al email
     */
    public function sendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email inválido',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar si el usuario existe
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No existe una cuenta con este correo electrónico'
            ], 404);
        }

        // Generar código de 6 dígitos
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Eliminar códigos anteriores no usados del mismo email
        PasswordResetCode::where('email', $request->email)
            ->where('used', false)
            ->delete();

        // Crear nuevo código con expiración de 15 minutos
        PasswordResetCode::create([
            'email' => $request->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
            'used' => false,
        ]);

        // Enviar email
        try {
            Mail::send('emails.password-reset-code', ['code' => $code, 'user' => $user], function ($message) use ($request, $user) {
                $message->to($request->email, $user->name)
                    ->subject('Código de Recuperación de Contraseña - GRUPO MI REDENTOR');
            });

            return response()->json([
                'success' => true,
                'message' => 'Hemos enviado un código de verificación a tu correo electrónico'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar código de verificación
     */
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Buscar el código
        $resetCode = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->first();

        if (!$resetCode) {
            return response()->json([
                'success' => false,
                'message' => 'Código inválido o ya utilizado'
            ], 404);
        }

        // Verificar si el código ha expirado
        if ($resetCode->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'El código ha expirado. Solicita uno nuevo'
            ], 410);
        }

        return response()->json([
            'success' => true,
            'message' => 'Código verificado correctamente'
        ]);
    }

    /**
     * Restablecer contraseña
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Buscar el código
        $resetCode = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->first();

        if (!$resetCode) {
            return response()->json([
                'success' => false,
                'message' => 'Código inválido o ya utilizado'
            ], 404);
        }

        // Verificar si el código ha expirado
        if ($resetCode->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'El código ha expirado. Solicita uno nuevo'
            ], 410);
        }

        // Buscar el usuario
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->password);
        $user->save();

        // Marcar el código como usado
        $resetCode->markAsUsed();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida exitosamente'
        ]);
    }
}
