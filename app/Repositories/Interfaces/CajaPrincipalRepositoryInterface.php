<?php

namespace App\Repositories\Interfaces;

use App\Models\CajaPrincipal;
use Illuminate\Database\Eloquent\Collection;

interface CajaPrincipalRepositoryInterface
{
    public function findById(int $id): ?CajaPrincipal;
    
    public function findByCodigo(string $codigo): ?CajaPrincipal;
    
    public function findByUserId(string $userId): ?CajaPrincipal;
    
    public function getAll(): Collection;
    
    public function create(array $data): CajaPrincipal;
    
    public function update(int $id, array $data): CajaPrincipal;
    
    public function delete(int $id): bool;
    
    public function generarSiguienteCodigo(): string;
    
    public function existeCodigoParaUsuario(string $userId): bool;
}
