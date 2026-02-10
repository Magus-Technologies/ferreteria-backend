<?php
// Archivo temporal para limpiar OPcache
// ELIMINAR DESPUÉS DE USAR

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully!";
} else {
    echo "OPcache is not enabled";
}

if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo " | APC cache cleared!";
}

echo "\n\nNow try your request again.";
