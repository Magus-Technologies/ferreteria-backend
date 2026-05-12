<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal para clientes - todos los usuarios autenticados pueden escuchar
Broadcast::channel('clientes', function ($user) {
    return true; // Todos los usuarios autenticados pueden escuchar
});
