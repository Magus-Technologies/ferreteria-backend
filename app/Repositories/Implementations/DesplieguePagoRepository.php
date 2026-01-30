<?php

namespace App\Repositories\Implementations;

use App\Models\DespliegueDePago;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DesplieguePagoRepository implements DesplieguePagoRepositoryInterface
{
    public function findById(string $id): ?DespliegueDePago
    {
        return DespliegueDePago::with('metodoDePago')->find($id);
    }

    public function getAll(): Collection
    {
        return DespliegueDePago::with('metodoDePago')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllMostrar(): Collection
    {
        return DespliegueDePago::with('metodoDePago')
            ->where('mostrar', 1)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): DespliegueDePago
    {
        // Generar UUID si no se proporciona un ID
        if (!isset($data['id'])) {
            $data['id'] = (string) \Illuminate\Support\Str::uuid();
        }
        
        // Establecer valores por defecto
        $data['mostrar'] = $data['mostrar'] ?? true;
        $data['activo'] = $data['activo'] ?? true;
        $data['requiere_numero_serie'] = $data['requiere_numero_serie'] ?? false;
        
        // Si no se proporciona metodo_de_pago_id, crear primero el MetodoDePago
        if (!isset($data['metodo_de_pago_id'])) {
            $metodoDePago = \App\Models\MetodoDePago::create([
                'id' => $data['id'],
                'name' => $data['name'],
                'cuenta_bancaria' => $data['cuenta_bancaria'] ?? null,
                'nombre_titular' => $data['nombre_titular'] ?? null,
                'monto' => $data['monto_inicial'] ?? 0,
                'monto_inicial' => $data['monto_inicial'] ?? 0,
                'subcaja_id' => $data['subcaja_id'] ?? null,
                'activo' => $data['activo'],
            ]);
            
            $data['metodo_de_pago_id'] = $metodoDePago->id;
        }
        
        return DespliegueDePago::create($data);
    }

    public function update(string $id, array $data): ?DespliegueDePago
    {
        $desplieguePago = DespliegueDePago::find($id);
        
        if (!$desplieguePago) {
            return null;
        }

        $desplieguePago->update($data);
        
        return $desplieguePago->fresh(['metodoDePago']);
    }

    public function delete(string $id): bool
    {
        $desplieguePago = DespliegueDePago::find($id);
        
        if (!$desplieguePago) {
            return false;
        }

        return $desplieguePago->delete();
    }
}
