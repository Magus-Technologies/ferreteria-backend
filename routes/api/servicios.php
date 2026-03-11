<?php

use App\Http\Controllers\ServicioController;
use Illuminate\Support\Facades\Route;

Route::apiResource('servicios', ServicioController::class);
